<?php

namespace Tests\Feature;

use App\Mail\GuestInvitation;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventDatesTest extends TestCase
{
    use RefreshDatabase;

    private Event $e;

    protected function setUp(): void
    {
        parent::setUp();
        $this->e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'start_year' => 1966,
            'event_date' => Carbon::parse('2026-12-12 18:00', 'Europe/Prague')->utc(), 'collect_until' => Carbon::parse('2026-12-12 18:00', 'Europe/Prague')->utc(),
            'archive_until' => '2027-06-30', 'plan' => 'premiera', 'limits' => ['guests' => 120, 'photos' => null, 'collect_days' => 90]]);
        $u = User::create(['email' => 'org@example.cz', 'name' => 'org']);
        $this->e->organizers()->attach($u->id, ['role' => 'owner']);
        $this->actingAs($u);
    }

    public function test_organizer_changes_event_date_and_deadline(): void
    {
        $this->postJson('/janinych-60/admin/api/settings', ['event_date' => '2026-12-19T19:30', 'collect_until' => '2026-12-18T22:00'])->assertOk();
        $e = $this->e->fresh();
        $this->assertSame('2026-12-19 19:30', $e->event_date->timezone('Europe/Prague')->format('Y-m-d H:i'));
        $this->assertSame('2026-12-18 22:00', $e->collect_until->timezone('Europe/Prague')->format('Y-m-d H:i'));
        $this->getJson('/janinych-60/admin/api/state')->assertJsonPath('event.event_date', $e->event_date->toIso8601String());
    }

    public function test_event_date_is_optional_deadline_is_required(): void
    {
        $this->postJson('/janinych-60/admin/api/settings', ['event_date' => null, 'collect_until' => null])
            ->assertStatus(422)->assertJsonPath('errors.collect_until.0', 'Uzávěrka sběru příspěvků je povinná.');

        $this->postJson('/janinych-60/admin/api/settings', ['event_date' => null, 'collect_until' => '2026-12-20T20:00'])->assertOk();
        $e = $this->e->fresh();
        $this->assertNull($e->event_date);
        $this->assertSame(60, $e->age()); // věk z uzávěrky
        $this->assertTrue($e->wallOpensAt()->eq($e->collect_until));

        // ostatní nastavení jde ukládat i bez polí s datem
        $this->postJson('/janinych-60/admin/api/settings', ['timeline' => false])->assertOk();
        $this->assertNotNull($this->e->fresh()->collect_until);

        // stránka hosta, Kino data a přehled událostí fungují bez data
        auth()->logout();
        $hh = $e->households()->create(['token' => 'tok12345678', 'label' => 'Petra', 'names' => ['Petra']]);
        $this->get('/janinych-60/g/tok12345678');
        $this->get('/janinych-60')->assertOk()->assertSee('Janiných 60');
        $this->getJson('/janinych-60/api/me')->assertJsonPath('config.party_date', null);

        // pozvánka bez data: {datum} zmizí i se závorkou
        $html = (new GuestInvitation($hh))->render();
        $this->assertStringContainsString('K události „Janiných 60“ připravujeme', $html);
        $this->assertStringNotContainsString('{datum}', $html);
        $this->assertStringNotContainsString('()', $html);
    }

    public function test_dates_cannot_go_past_archive(): void
    {
        $this->postJson('/janinych-60/admin/api/settings', ['event_date' => '2027-07-02T18:00'])->assertStatus(422)->assertJsonPath('error', fn ($m) => str_contains($m, 'Archiv události končí 30. 6. 2027'));
        $this->postJson('/janinych-60/admin/api/settings', ['collect_until' => '2027-07-01T10:00'])->assertStatus(422);
        $this->assertSame('2026-12-12', $this->e->fresh()->event_date->timezone('Europe/Prague')->toDateString());
    }

    public function test_backoffice_allows_empty_event_date(): void
    {
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']));
        $base = ['title' => 'Janiných 60', 'recipient_name' => 'Jana', 'archive_until' => '2027-06-30', 'owner_email' => 'org@example.cz'];
        $r = $this->post("/backoffice/udalosti/{$this->e->slug}", [...$base, 'event_date' => '', 'collect_until' => '']);
        $r->assertSessionHasErrors('collect_until');
        $this->post("/backoffice/udalosti/{$this->e->slug}", [...$base, 'event_date' => '', 'collect_until' => '2026-12-20T20:00'])->assertSessionHasNoErrors();
        $this->assertNull($this->e->fresh()->event_date);
        $this->get('/backoffice')->assertOk();
    }
}
