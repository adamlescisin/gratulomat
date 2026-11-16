<?php

namespace Tests\Feature;

use App\Domain\Orders\StripeGateway;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeStripe;
use Tests\TestCase;

class EventEditingTest extends TestCase
{
    use RefreshDatabase;

    private function setupEvent(): array
    {
        Mail::fake();
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->app->instance(StripeGateway::class, new FakeStripe);
        $op = User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']);
        $this->actingAs($op);
        $this->post('/zalozit', ['manual' => '1', 'email' => 'adam.lescisin@seznam.c.z', 'config' => json_encode([
            'name' => 'František 40', 'slug' => 'frantisek-40', 'occasion' => 'birthday', 'date' => now()->addMonth()->toDateString(),
            'recipient' => 'person', 'recName' => 'František', 'guests' => 20, 'voice' => true,
        ])]);

        return [$op, Event::where('slug', 'frantisek-40')->first(), Order::first()];
    }

    public function test_manual_order_has_no_price_or_lines(): void
    {
        [, , $order] = $this->setupEvent();
        $this->assertSame(0, $order->total_halere);
        $this->assertSame([], $order->lines);
        $this->get("/backoffice/objednavky/{$order->id}")->assertOk()
            ->assertSee('bez platby')->assertDontSee('Celkem s DPH')->assertDontSee('Tarif Párty');
    }

    public function test_operator_fixes_customer_email_and_moves_owner(): void
    {
        [, $event, $order] = $this->setupEvent();
        $this->post("/backoffice/objednavky/{$order->id}/email", ['email' => 'adam.lescisin@seznam.cz', 'move_owner' => '1'])->assertSessionHas('ok');
        $this->assertSame('adam.lescisin@seznam.cz', $order->fresh()->email);
        $this->assertSame(['adam.lescisin@seznam.cz'], $event->organizers()->pluck('email')->all());
    }

    public function test_operator_edits_event_details(): void
    {
        [, $event] = $this->setupEvent();
        $this->get("/backoffice/udalosti/{$event->slug}")->assertOk()->assertSee('Viditelnost (archiv) do');
        $this->post("/backoffice/udalosti/{$event->slug}", [
            'title' => 'Františkových 40', 'recipient_name' => 'Jana', 'event_date' => '2027-03-06T18:30',
            'collect_until' => '2027-03-06T12:00', 'archive_until' => '2028-03-31', 'guest_limit' => 60, 'owner_email' => 'novy@example.cz',
        ])->assertRedirect("/backoffice/udalosti/{$event->slug}");
        $e = $event->fresh();
        $this->assertSame('Františkových 40', $e->title);
        $this->assertSame('Janě', $e->form('dat'));
        $this->assertSame('2027-03-06 17:30', $e->event_date->utc()->format('Y-m-d H:i'));
        $this->assertSame('2028-03-31', $e->archive_until->toDateString());
        $this->assertSame(60, $e->limit('guests'));
        $this->assertSame(['novy@example.cz'], $e->organizers()->pluck('email')->all());
        $this->assertSame('novy@example.cz', Order::first()->email);
    }

    public function test_organizer_renames_event_and_recipient(): void
    {
        [, $event] = $this->setupEvent();
        $org = User::where('email', 'adam.lescisin@seznam.c.z')->first();
        $this->actingAs($org);
        $this->postJson('/frantisek-40/admin/api/settings', ['title' => 'Frantovo 40', 'recipient_name' => 'Petr', 'invite_text' => 'Ahoj {jmena}'])->assertOk();
        $e = $event->fresh();
        $this->assertSame('Frantovo 40', $e->title);
        $this->assertSame('Petrovi', $e->form('dat'));
        $this->postJson('/frantisek-40/admin/api/settings', ['title' => ''])->assertStatus(422);
        // provozovatelská úprava organizátorovi nepatří
        $this->get('/backoffice/udalosti/frantisek-40')->assertNotFound();
    }

    public function test_mp4_is_not_sold_yet(): void
    {
        $q = $this->postJson('/api/quote', ['config' => ['guests' => 5, 'mp4' => true, 'collect' => 14, 'archive' => 1]])->json();
        $this->assertSame('ochutnavka', $q['plan']);
    }

    public function test_default_invite_needs_no_declension(): void
    {
        [, $event] = $this->setupEvent();
        $event->update(['title' => 'Svatba Adama a Evy', 'recipient_name' => 'Adam a Eva']);
        $this->actingAs(User::where('email', 'adam.lescisin@seznam.c.z')->first());
        $text = $this->getJson('/frantisek-40/admin/api/state')->json('event.invite_text');
        $this->assertStringContainsString('K události „Svatba Adama a Evy“ ({datum})', $text);
        $this->assertStringNotContainsString('pro Adam a Eva', $text);

        // uložení beze změny výchozí text neukládá
        $this->postJson('/frantisek-40/admin/api/settings', ['invite_text' => $text])->assertOk();
        $this->assertNull($event->fresh()->setting('invite_text'));
        $this->postJson('/frantisek-40/admin/api/settings', ['invite_text' => 'Vlastní text {odkaz}'])->assertOk();
        $this->assertSame('Vlastní text {odkaz}', $event->fresh()->setting('invite_text'));
    }

    public function test_finale_title_defaults_by_event_type(): void
    {
        [, $event] = $this->setupEvent();
        $this->assertSame('Všechno nejlepší, Františku!', $event->finaleTitle());
        $event->update(['type' => 'firemni-jubileum']);
        $this->assertSame('Děkujeme, že jste součástí příběhu', $event->fresh()->finaleTitle());
        $event->update(['type' => 'smutecni']);
        $this->assertSame('Navždy v našich vzpomínkách', $event->fresh()->finaleTitle());
        $event->update(['type' => 'svatba']);
        $this->assertSame('Ať vám to spolu klape!', $event->fresh()->finaleTitle());

        $this->actingAs(User::where('email', 'adam.lescisin@seznam.c.z')->first());
        $this->postJson('/frantisek-40/admin/api/settings', ['finale_title' => 'Na zdraví novomanželů!'])->assertOk();
        $this->assertSame('Na zdraví novomanželů!', $event->fresh()->finaleTitle());
        $this->getJson('/frantisek-40/api/show')->assertOk()->assertJsonFragment(['finale_title' => 'Na zdraví novomanželů!']);
        $this->postJson('/frantisek-40/admin/api/settings', ['finale_title' => ''])->assertOk();
        $this->assertSame('Ať vám to spolu klape!', $event->fresh()->finaleTitle());
    }
}
