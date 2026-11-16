<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoShareTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attrs = []): Event
    {
        $e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'event_date' => now()->addMonth(),
            'collect_until' => now()->addWeeks(3), 'plan' => 'party', ...$attrs]);
        $u = User::create(['email' => 'org@example.cz', 'name' => 'org']);
        $e->organizers()->attach($u->id, ['role' => 'owner']);
        $e->households()->create(['token' => 'tok12345678', 'label' => 'Petra', 'names' => ['Petra']]);

        return $e;
    }

    public function test_teaser_shows_deadline_and_surprise_only_for_celebrations(): void
    {
        $e = $this->event(['collect_until' => '2027-01-20 10:00:00']);
        $this->get('/janinych-60/g/tok12345678')->assertRedirect();
        $this->get('/janinych-60')->assertSee('zapečetěné až do 20. ledna 2027.')->assertSee('Překvapení musí být!')
            ->assertSee('audiovizuální prezentace s časovou osou');

        $e->update(['type' => 'smutecni']);
        $this->get('/janinych-60')->assertSee('zapečetěné až do 20. ledna 2027.')->assertDontSee('Překvapení musí být!');
    }

    public function test_auto_share_opens_show_after_deadline(): void
    {
        $e = $this->event();
        $this->actingAs(User::first());
        $this->postJson('/janinych-60/admin/api/settings', ['auto_share' => true])->assertOk();
        $this->getJson('/janinych-60/admin/api/state')->assertJson(['show' => ['auto_share' => true, 'is_public' => false]]);
        $this->assertFalse($e->fresh()->showPublic());

        $this->travel(22)->days();
        $this->assertTrue($e->fresh()->showPublic());
        auth()->logout();
        $this->get('/janinych-60/g/tok12345678');
        $this->getJson('/janinych-60/api/me')->assertJson(['config' => ['kino_open' => true]]);
        $this->get('/janinych-60/kino')->assertOk();

        // „Zavřít pro hosty“ vypne i automatické sdílení
        $this->actingAs(User::first());
        $this->postJson('/janinych-60/admin/api/show', ['public_from' => null])->assertOk();
        $this->assertFalse($e->fresh()->showPublic());
    }
}
