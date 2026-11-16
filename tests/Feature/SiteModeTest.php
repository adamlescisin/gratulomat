<?php

namespace Tests\Feature;

use Tests\TestCase;

class SiteModeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.site_mode' => 'coming_soon', 'gratulomat.tester_codes' => ['BETA-1234']]);
    }

    public function test_public_sees_coming_soon(): void
    {
        $this->get('/')->assertOk()->assertSee('Připravujeme', false)->assertSee('Mám přístupový kód');
    }

    public function test_tester_code_unlocks_site(): void
    {
        $this->post('/pristup', ['code' => 'beta-1234'])->assertRedirect('/')->assertCookie('gm_tester', 'BETA-1234');
        $this->withCookie('gm_tester', 'BETA-1234')->get('/')->assertOk()->assertDontSee('Mám přístupový kód');
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->post('/pristup', ['code' => 'NOPE'])->assertSessionHasErrors('code');
    }

    public function test_maintenance_returns_503(): void
    {
        config(['gratulomat.site_mode' => 'maintenance']);
        $this->get('/')->assertStatus(503)->assertSee('Jsme hned zpátky');
    }

    public function test_live_mode_shows_site(): void
    {
        config(['gratulomat.site_mode' => 'live']);
        $this->get('/')->assertOk()->assertDontSee('Mám přístupový kód');
    }
}
