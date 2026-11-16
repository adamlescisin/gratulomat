<?php

namespace Tests\Feature;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestLocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.site_mode' => 'coming_soon']);
        Event::create(['slug' => 'test-akce', 'title' => 'Test', 'recipient_name' => 'Jana', 'event_date' => now()->addWeek(), 'is_test' => true]);
    }

    public function test_default_is_czech(): void
    {
        $this->get('/test-akce')->assertSee('<html lang="cs">', false)->assertSee('Vstoupit');
    }

    public function test_browser_language_is_used_on_first_visit(): void
    {
        $this->get('/test-akce', ['Accept-Language' => 'de-AT,de;q=0.9,en;q=0.5'])
            ->assertSee('<html lang="de">', false)->assertSee('Eintreten');
    }

    public function test_unsupported_browser_language_falls_back_to_event_language(): void
    {
        $this->get('/test-akce', ['Accept-Language' => 'fr-FR'])->assertSee('<html lang="cs">', false);
    }

    public function test_guest_choice_wins_over_browser(): void
    {
        $this->post('/jazyk/en')->assertCookie('gm_locale', 'en');
        $this->withCookie('gm_locale', 'en')->get('/test-akce', ['Accept-Language' => 'de'])
            ->assertSee('<html lang="en">', false)->assertSee('Enter');
    }

    public function test_unknown_locale_is_404(): void
    {
        config(['gratulomat.site_mode' => 'live']);
        $this->post('/jazyk/xx')->assertNotFound();
    }

    public function test_translation_files_have_the_same_keys(): void
    {
        $keys = fn (string $l) => array_keys(json_decode(file_get_contents(lang_path("$l.json")), true));
        foreach (config('gratulomat.locales.guest') as $locale) {
            $this->assertEqualsCanonicalizing($keys('cs'), $keys($locale), "lang/$locale.json neodpovídá cs.json");
        }
    }

    public function test_guest_translation_groups_have_the_same_keys(): void
    {
        $keys = function (array $a, string $prefix = '') use (&$keys): array {
            $out = [];
            foreach ($a as $k => $v) {
                // seznamy otázek se mohou lišit délkou, porovnáváme jen typy událostí
                $out[] = $prefix.$k;
                if (is_array($v) && ! array_is_list($v)) {
                    $out = [...$out, ...$keys($v, $prefix.$k.'.')];
                }
            }

            return $out;
        };
        $cs = $keys(require lang_path('cs/guest.php'));
        foreach (config('gratulomat.locales.guest') as $locale) {
            $this->assertEqualsCanonicalizing($cs, $keys(require lang_path("$locale/guest.php")), "lang/$locale/guest.php neodpovídá cs");
        }
    }
}
