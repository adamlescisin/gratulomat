<?php

namespace Tests\Feature;

use Tests\TestCase;

class GuestLocaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.site_mode' => 'coming_soon']);
    }

    public function test_default_is_czech(): void
    {
        $this->get('/')->assertSee('<html lang="cs">', false)->assertSee('Připravujeme');
    }

    public function test_browser_language_is_used_on_first_visit(): void
    {
        $this->get('/', ['Accept-Language' => 'de-AT,de;q=0.9,en;q=0.5'])
            ->assertSee('<html lang="de">', false)->assertSee('Demnächst');
    }

    public function test_unsupported_browser_language_falls_back_to_czech(): void
    {
        $this->get('/', ['Accept-Language' => 'fr-FR'])->assertSee('<html lang="cs">', false);
    }

    public function test_guest_choice_wins_over_browser(): void
    {
        $this->post('/jazyk/en')->assertCookie('gm_locale', 'en');
        $this->withCookie('gm_locale', 'en')->get('/', ['Accept-Language' => 'de'])
            ->assertSee('<html lang="en">', false)->assertSee('Coming soon');
    }

    public function test_unknown_locale_is_404(): void
    {
        $this->post('/jazyk/xx')->assertNotFound();
    }

    public function test_translation_files_have_the_same_keys(): void
    {
        $keys = fn (string $l) => array_keys(json_decode(file_get_contents(lang_path("$l.json")), true));
        foreach (config('gratulomat.locales.guest') as $locale) {
            $this->assertEqualsCanonicalizing($keys('cs'), $keys($locale), "lang/$locale.json neodpovídá cs.json");
        }
    }
}
