<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_rules_page_and_link_on_guest_page(): void
    {
        $this->get('/pravidla-pro-hosty')->assertOk()->assertSee('Pravidla pro hosty')->assertSee('Co nahrát nesmíte');
        Setting::put('site_mode', 'live');
        $this->get('/pravidla-pro-hosty')->assertOk()->assertSee('Licence.');

        $e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'event_date' => now()->addMonth(), 'plan' => 'party']);
        $e->households()->create(['token' => 'tok12345678', 'label' => 'Petra', 'names' => ['Petra']]);
        $this->get('/janinych-60/g/tok12345678');
        $this->get('/janinych-60')->assertSee('Uložením souhlasíte s', false)->assertSee('/pravidla-pro-hosty', false);
    }

    public function test_operator_edits_guest_rules(): void
    {
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']));
        $this->get('/backoffice/pravni?varianta=live')->assertSee('Pravidla pro hosty')->assertSee('Licence.');
        Setting::put('site_mode', 'coming_soon');
        $this->post('/backoffice/pravni', ['variant' => 'test', 'guest_rules' => '## Testovací pravidla {email}', 'terms' => '', 'privacy' => '']);
        $this->get('/pravidla-pro-hosty')->assertSee('Testovací pravidla')->assertSee('ahoj@gratulomat.cz');
    }
}
