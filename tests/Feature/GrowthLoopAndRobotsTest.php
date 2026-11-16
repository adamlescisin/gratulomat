<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Household;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Growth loop („Vytvořeno v Gratulomatu“) a zákaz indexace stránek událostí. */
class GrowthLoopAndRobotsTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storage = sys_get_temp_dir().'/gm-test-'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
        config(['gratulomat.site_mode' => 'live', 'gratulomat.marketing_url' => 'https://gratulomat.test']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    private function event(array $attrs = []): Event
    {
        $event = Event::create(array_merge([
            'slug' => 'janinych-60', 'type' => 'narozeniny', 'title' => 'Janiných 60', 'recipient_name' => 'Jana',
            'recipient_forms' => ['acc' => 'Janu', 'dat' => 'Janě', 'voc' => 'Jano'], 'start_year' => 1966,
            'event_date' => now()->addMonth(), 'collect_until' => now()->addMonth(), 'status' => 'collecting', 'plan' => 'premiera', 'is_test' => true,
        ], $attrs));
        $event->show()->create([]);

        return $event;
    }

    private function guest(Event $event): Household
    {
        return $event->households()->create(['token' => Household::newToken(), 'label' => 'Petr', 'names' => ['Petr']]);
    }

    public function test_event_pages_are_noindex_in_header_and_meta(): void
    {
        $event = $this->event();
        $h = $this->guest($event);

        $landing = $this->get('/janinych-60');
        $landing->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex')
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

        $this->get('/janinych-60/g/'.$h->token)->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
        $this->get('/janinych-60/kino')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
        $this->getJson('/janinych-60/api/me')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
    }

    public function test_unknown_paths_wizard_and_backoffice_are_noindex(): void
    {
        $this->get('/neexistuje-vubec')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
        $this->get('/zalozit')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
        $this->get('/prihlaseni')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
        $this->get('/backoffice')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
    }

    public function test_public_marketing_pages_stay_indexable(): void
    {
        foreach (['/', '/ochrana-soukromi', '/obchodni-podminky'] as $url) {
            $this->get($url)->assertHeaderMissing('X-Robots-Tag');
        }
    }

    public function test_coming_soon_soft_404_is_noindex(): void
    {
        config(['gratulomat.site_mode' => 'coming_soon']);
        $this->get('/neexistuje-vubec/neco')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive, noimageindex');
    }

    public function test_guest_page_has_made_by_footer_and_hidden_thanks_card(): void
    {
        $event = $this->event();
        $h = $this->guest($event);

        $this->get('/janinych-60/g/'.$h->token);
        $page = $this->get('/janinych-60');
        $page->assertOk()->assertSee('data-loop', false)->assertSee('Vytvořeno v Gratulomatu')
            ->assertSee('https://gratulomat.test/?utm_source=event&amp;utm_medium=guest_loop&amp;utm_content=guest_footer&amp;utm_campaign=narozeniny', false)
            ->assertSee('utm_content=guest_thanks', false);
        // slug ani jméno oslavence v odkazu nejsou
        $this->assertStringNotContainsString('utm_term', $page->getContent());
        $this->assertStringNotContainsString('gratulomat.test/janinych', $page->getContent());
    }

    public function test_funeral_events_have_no_promotion(): void
    {
        $event = $this->event(['slug' => 'rozloucime-se', 'type' => config('gratulomat.funeral.type'), 'title' => 'Na rozloučenou']);
        $h = $this->guest($event);

        $this->get('/rozloucime-se/g/'.$h->token);
        $page = $this->get('/rozloucime-se')->assertOk();
        $page->assertDontSee('Vytvořeno v Gratulomatu')->assertDontSee('data-loop', false)->assertDontSee('utm_source=event', false);
        $this->assertNull($event->loopUrl('kino_finale'));
    }

    public function test_kino_passes_loop_link_to_script(): void
    {
        $event = $this->event(['slug' => 'kino-test']);
        $this->guest($event);
        $organizer = User::create(['email' => 'org@example.com', 'name' => 'Org']);
        $event->organizers()->attach($organizer, ['role' => 'owner']);

        $this->actingAs($organizer)->get('/kino-test/kino')->assertOk()->assertSee('utm_content=kino_finale', false);
    }
}
