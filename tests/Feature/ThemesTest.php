<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemesTest extends TestCase
{
    use RefreshDatabase;

    private function event(string $plan, array $attrs = []): Event
    {
        $e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'event_date' => now()->addMonth(), 'plan' => $plan, ...$attrs]);
        $u = User::firstOrCreate(['email' => 'org@example.cz'], ['name' => 'org']);
        $e->organizers()->attach($u->id, ['role' => 'owner']);

        return $e;
    }

    public function test_guest_pages_use_event_theme_and_admin_uses_brand(): void
    {
        $e = $this->event('party', ['theme' => 'vesela', 'settings' => ['mode' => 'dark']]);
        $this->get('/janinych-60')->assertOk()->assertSee('data-theme="vesela" data-mode="dark"', false)->assertSee('themes.css');
        $this->get('/prihlaseni')->assertOk()->assertSee('data-theme="brand"', false);
        $this->actingAs(User::where('email', 'org@example.cz')->first());
        $this->get('/janinych-60/admin')->assertOk()->assertSee('data-theme="brand"', false);
        $this->get('/janinych-60/kino')->assertOk()->assertSee('data-theme="vesela" data-mode="dark"', false);
    }

    public function test_legacy_kino_theme_is_exclusive_dark(): void
    {
        $e = $this->event('premiera', ['theme' => 'kino']);
        $this->assertSame('exkluzivni', $e->themeKey());
        $this->assertSame('dark', $e->themeMode());
    }

    public function test_organizer_changes_theme_within_plan(): void
    {
        $e = $this->event('ochutnavka');
        $this->actingAs(User::where('email', 'org@example.cz')->first());
        $state = $this->getJson('/janinych-60/admin/api/state')->json('event');
        $this->assertSame('neutralni', $state['theme']);
        $this->assertFalse(collect($state['themes'])->firstWhere('key', 'exkluzivni')['available']);

        $this->postJson('/janinych-60/admin/api/settings', ['theme' => 'exkluzivni', 'mode' => 'light'])->assertStatus(422);
        $this->postJson('/janinych-60/admin/api/settings', ['theme' => 'neutralni', 'mode' => 'dark'])->assertStatus(422);

        $e->update(['plan' => 'party']);
        $this->postJson('/janinych-60/admin/api/settings', ['theme' => 'exkluzivni', 'mode' => 'dark'])->assertOk();
        $e->refresh();
        $this->assertSame('exkluzivni', $e->themeKey());
        $this->assertSame('dark', $e->themeMode());
    }

    public function test_funeral_gets_mourning_theme_free(): void
    {
        $e = $this->event('ochutnavka', ['type' => 'smutecni']);
        $this->actingAs(User::where('email', 'org@example.cz')->first());
        $this->postJson('/janinych-60/admin/api/settings', ['theme' => 'smutecni', 'mode' => 'light'])->assertOk();
        $this->assertSame('smutecni', $e->fresh()->themeKey());
    }

    public function test_wizard_theme_pricing(): void
    {
        $base = ['guests' => 5, 'collect' => 14, 'archive' => 1];
        $this->postJson('/api/quote', ['config' => [...$base, 'theme' => 'neutralni']])->assertJson(['plan' => 'ochutnavka']);
        $this->postJson('/api/quote', ['config' => [...$base, 'theme' => 'vesela']])->assertJson(['plan' => 'party']);
        $this->postJson('/api/quote', ['config' => [...$base, 'theme' => 'neutralni', 'mode' => 'dark']])->assertJson(['plan' => 'party']);
        $this->postJson('/api/quote', ['config' => [...$base, 'occasion' => 'funeral', 'theme' => 'smutecni']])->assertJson(['plan' => 'ochutnavka']);
    }
}
