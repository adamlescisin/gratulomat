<?php

namespace Tests\Feature;

use App\Mail\RecipientHandover;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class HandoverTest extends TestCase
{
    use RefreshDatabase;

    private Event $e;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'recipient_forms' => ['voc' => 'Jano', 'acc' => 'Janu'],
            'event_date' => now()->addMonth(), 'collect_until' => now()->addWeeks(3), 'archive_until' => now()->addMonths(3)->toDateString(), 'plan' => 'party',
            'limits' => ['guests' => 25, 'photos' => null, 'features' => ['wishes', 'offline']]]);
        $u = User::create(['email' => 'org@example.cz', 'name' => 'Petr']);
        $this->e->organizers()->attach($u->id, ['role' => 'owner']);
        $hh = $this->e->households()->create(['token' => 'tok12345678', 'label' => 'Petra', 'names' => ['Petra']]);
        $this->e->memories()->create(['household_id' => $hh->id, 'authors' => ['Petra'], 'title' => 'Chata', 'body' => 'Léto 1998', 'tags' => [],
            'wish_text' => 'Tajné přání jen pro Janu', 'wish_public' => false]);
        $this->e->memories()->create(['household_id' => $hh->id, 'authors' => ['Karel'], 'title' => 'Skrytá', 'tags' => [], 'status' => 'hidden']);
    }

    private function asOrganizer(): void
    {
        $this->actingAs(User::first());
    }

    public function test_organizer_creates_link_and_recipient_sees_everything(): void
    {
        $this->asOrganizer();
        $s = $this->postJson('/janinych-60/admin/api/handover', ['message' => "Všechno nejlepší!\nPetr"])->assertOk()->json('show.handover');
        $this->assertNull($s['sent_at']);
        $this->assertMatchesRegularExpression('#/janinych-60/predani/[A-Za-z0-9]{32}$#', $s['url']);
        auth()->logout();
        $this->flushSession();

        // bez odkazu nic
        $this->getJson('/janinych-60/api/show')->assertForbidden();

        $this->get($s['url'])->assertOk()
            ->assertSee('Tohle je pro tebe, <em>Jano</em>', false)
            ->assertSee('Všechno nejlepší!')->assertSee('Tajné přání jen pro Janu')->assertSee('soukromé přání – jen pro tebe')
            ->assertSee('Stáhnout navždy')->assertDontSee('Skrytá');
        $this->assertNotNull($this->e->fresh()->handover()['opened_at']);

        // Kino včetně soukromých přání, i když prezentace hostům zpřístupněná není
        $this->get('/janinych-60/kino')->assertOk();
        $show = $this->getJson('/janinych-60/api/show')->assertOk()->json();
        $this->assertTrue($show['recipient']);
        $this->assertSame('Tajné přání jen pro Janu', collect($show['memories'])->firstWhere('title', 'Chata')['wish_text']);

        // organizátor v Kině soukromá přání dál nevidí
        $this->asOrganizer();
        $this->flushSession();
        $this->assertNull(collect($this->getJson('/janinych-60/api/show')->json('memories'))->firstWhere('title', 'Chata')['wish_text']);
    }

    public function test_send_by_email(): void
    {
        Mail::fake();
        $this->asOrganizer();
        $this->postJson('/janinych-60/admin/api/handover', ['send' => true])->assertStatus(422);
        $s = $this->postJson('/janinych-60/admin/api/handover', ['email' => 'Jana@Example.cz', 'message' => 'Užij si to', 'send' => true])->assertOk()->json('show.handover');
        $this->assertSame('jana@example.cz', $s['email']);
        $this->assertNotNull($s['sent_at']);
        Mail::assertSent(RecipientHandover::class, function (RecipientHandover $m) {
            $html = $m->render();

            return $m->hasTo('jana@example.cz') && $m->hasReplyTo('org@example.cz')
                && str_contains($html, '1 člověk pro vás poskládal') && str_contains($html, 'Užij si to') && str_contains($html, $this->e->fresh()->handoverUrl());
        });
    }

    public function test_regenerate_and_revoke_invalidate_old_link(): void
    {
        $this->asOrganizer();
        $old = $this->postJson('/janinych-60/admin/api/handover', [])->json('show.handover.url');
        $new = $this->postJson('/janinych-60/admin/api/handover', ['regenerate' => true])->json('show.handover.url');
        $this->assertNotSame($old, $new);
        auth()->logout();

        $this->get($old)->assertNotFound()->assertSee('Tento odkaz už neplatí');
        $this->get($new)->assertOk();
        $this->getJson('/janinych-60/api/show')->assertOk();

        // zrušení okamžitě ukončí i otevřenou relaci
        $this->asOrganizer();
        $this->deleteJson('/janinych-60/admin/api/handover')->assertOk()->assertJsonPath('show.handover', null);
        auth()->logout();
        $this->getJson('/janinych-60/api/show')->assertForbidden();
        $this->get($new)->assertNotFound();
    }

    public function test_recipient_offline_package_has_private_wishes_but_no_guest_list(): void
    {
        $this->asOrganizer();
        $url = $this->postJson('/janinych-60/admin/api/handover', [])->json('show.handover.url');
        auth()->logout();

        $res = $this->get($url.'/offline.zip')->assertOk();
        $zip = new \ZipArchive;
        $zip->open($res->baseResponse->getFile()->getPathname());
        $this->assertFalse($zip->locateName('guests.json'));
        $show = json_decode($zip->getFromName('show.json'), true);
        $this->assertTrue($show['recipient']);
        $this->assertSame(['Chata'], array_column($show['memories'], 'title'));
        $this->assertSame('Tajné přání jen pro Janu', $show['memories'][0]['wish_text']);
        $zip->close();
    }

    public function test_funeral_wording(): void
    {
        $this->e->update(['type' => 'smutecni']);
        $this->asOrganizer();
        $url = $this->postJson('/janinych-60/admin/api/handover', [])->json('show.handover.url');
        auth()->logout();
        // u smutečního rozloučení hosté psali „vzkaz uvidí jen organizátor“ → rodina soukromé vzkazy nevidí
        $this->get($url)->assertOk()->assertSee('Vzpomínky, které jsme sesbírali')->assertSee('1 člověk se podělil')
            ->assertDontSee('pro tebe')->assertDontSee('Tajné přání jen pro Janu')->assertSee('Chata');
        $this->assertNull(collect($this->getJson('/janinych-60/api/show')->json('memories'))->firstWhere('title', 'Chata')['wish_text']);
    }
}
