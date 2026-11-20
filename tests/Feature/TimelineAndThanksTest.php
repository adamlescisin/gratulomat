<?php

namespace Tests\Feature;

use App\Domain\Guest\EventRules;
use App\Domain\Guest\MemoryPresenter;
use App\Domain\Orders\WizardConfig;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineAndThanksTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attrs = []): Event
    {
        $e = Event::create(['slug' => 'dekujeme-trenere', 'title' => 'Díky, trenére', 'recipient_name' => 'Petr', 'event_date' => now()->addMonth(),
            'collect_until' => now()->addWeeks(3), 'plan' => 'party', 'start_year' => 2015,
            'limits' => ['guests' => 25, 'photos' => null, 'features' => ['wishes', 'no_watermark', 'offline', 'all_themes', 'dark_mode']], ...$attrs]);
        $u = User::create(['email' => 'org@example.cz', 'name' => 'org']);
        $e->organizers()->attach($u->id, ['role' => 'owner']);
        $e->households()->create(['token' => 'tok12345678', 'label' => 'Petra', 'names' => ['Petra']]);

        return $e;
    }

    public function test_thanks_is_an_event_type_with_own_texts(): void
    {
        $this->assertSame('podekovani', WizardConfig::OCCASIONS['thanks'][0]);
        $this->assertContains('podekovani', config('gratulomat.event_types'));

        $e = $this->event(['type' => 'podekovani', 'recipient_forms' => ['voc' => 'Petře', 'acc' => 'Petra']]);
        $this->assertSame('Děkujeme, Petře!', $e->defaultFinaleTitle());
        $this->assertContains('Rady a lekce', EventRules::tags($e));

        $this->get('/dekujeme-trenere/g/tok12345678');
        $this->get('/dekujeme-trenere')->assertOk()->assertSee('poskládat poděkování pro <em>Petra</em>', false);

        $this->actingAs(User::first());
        $this->getJson('/dekujeme-trenere/admin/api/state')->assertJsonPath('event.invite_text', fn ($t) => str_contains($t, 'chystáme společné poděkování'));
    }

    public function test_timeline_is_on_by_default(): void
    {
        $e = $this->event();
        $this->assertTrue($e->timeline());
        $this->get('/dekujeme-trenere/g/tok12345678');
        $this->get('/dekujeme-trenere')->assertSee('s časovou osou')->assertSee('data-year-unknown >', false);
        $this->getJson('/dekujeme-trenere/api/me')->assertJsonPath('config.timeline', true);
    }

    public function test_organizer_turns_decade_chapters_off(): void
    {
        $e = $this->event();
        $this->assertTrue($e->decades());
        $this->assertTrue(MemoryPresenter::show($e, '/')['decades']);

        $this->actingAs(User::first());
        $this->postJson('/dekujeme-trenere/admin/api/settings', ['decades' => false])->assertOk();
        $this->getJson('/dekujeme-trenere/admin/api/state')->assertJsonPath('show.decades', false)->assertJsonPath('show.timeline', true);
        $e = $e->fresh();
        $this->assertFalse($e->decades());
        $this->assertTrue($e->timeline()); // roky zůstávají, jen bez kapitol po desetiletích
        $show = MemoryPresenter::show($e, '/');
        $this->assertTrue($show['timeline']);
        $this->assertFalse($show['decades']);

        // bez časové osy nejsou ani desetiletí
        $this->postJson('/dekujeme-trenere/admin/api/settings', ['decades' => true, 'timeline' => false])->assertOk();
        $this->assertFalse($e->fresh()->decades());
    }

    public function test_organizer_turns_timeline_off(): void
    {
        $e = $this->event();
        $old = $e->memories()->create(['household_id' => $e->households()->first()->id, 'authors' => ['Petra'], 'title' => 'Soustředění', 'year' => 2018, 'tags' => []]);

        $this->actingAs(User::first());
        $this->postJson('/dekujeme-trenere/admin/api/settings', ['timeline' => false])->assertOk();
        $this->getJson('/dekujeme-trenere/admin/api/state')->assertJsonPath('show.timeline', false);
        $this->assertFalse($e->fresh()->timeline());
        $this->assertFalse(MemoryPresenter::show($e->fresh(), '/m')['timeline']);
        auth()->logout();

        $this->get('/dekujeme-trenere/g/tok12345678');
        $this->get('/dekujeme-trenere')->assertDontSee('s časovou osou')->assertDontSee('hero__years')
            ->assertSee('<fieldset class="step"  hidden >', false);
        $this->getJson('/dekujeme-trenere/api/me')->assertJsonPath('config.timeline', false);

        // nová vzpomínka bez roku i kdyby ho klient poslal; úprava starší nezmaže dřív zadaný rok
        $new = $this->postJson('/dekujeme-trenere/api/memories', ['title' => 'Turnaj', 'year' => 2020, 'authors' => ['Petra']])->assertOk()->json();
        $this->assertNull($new['year']);
        $this->postJson("/dekujeme-trenere/api/memories/{$old->id}", ['title' => 'Soustředění v Krkonoších', 'authors' => ['Petra']])->assertOk();
        $this->assertSame(2018, $old->fresh()->year);
    }
}
