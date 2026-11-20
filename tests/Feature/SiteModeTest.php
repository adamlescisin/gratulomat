<?php

namespace Tests\Feature;

use App\Mail\WaitlistSignup;
use App\Models\Event;
use App\Models\Setting;
use App\Models\TesterCode;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SiteModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushCache();
        config(['gratulomat.site_mode' => 'coming_soon', 'gratulomat.tester_codes' => ['BETA-1234']]);
    }

    public function test_coming_soon_button_opens_demo_video_dialog(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('data-demo', false)
            ->assertSee('data-demo-dialog', false)
            ->assertSee('Ukázku právě připravujeme');
        // soubor ukázky se nesmí v režimu Připravujeme nahradit stránkou coming soon
        $this->get('/ukazka/demo-aaaaaaaaaaaaaaaaaaaaaaaa.mp4')->assertNotFound();
    }

    public function test_public_sees_designed_coming_soon_page(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Vzpomínky, které se promítají.')
            ->assertSee('Mám přístupový kód')
            ->assertSee('gratulomat.cz')
            ->assertDontSee('gratulomat.eu')
            ->assertSee('/favicon.svg', false);
        // ostatní stránky v režimu Připravujeme taky
        $this->get('/nejaka-akce')->assertSee('Vzpomínky, které se promítají.');
        $this->get('/ochrana-soukromi')->assertOk()->assertSee('Zásady zpracování osobních údajů');
        // adresy mimo všechny routy (roboti: /wp-login.php, /a/b/c) nesmí skončit chybou 500
        $this->get('/wp-login.php')->assertOk()->assertSee('Vzpomínky, které se promítají.');
        $this->get('/a/b/c/d')->assertOk()->assertSee('Vzpomínky, které se promítají.');
    }

    public function test_tester_code_unlocks_site(): void
    {
        $this->post('/pristup', ['code' => 'beta-1234'])->assertRedirect('/')->assertCookie('gm_tester', 'BETA-1234');
        // tester vidí landing page (ne coming soon) a dostane se do průvodce
        $this->withCookie('gm_tester', 'BETA-1234')->get('/')->assertOk()
            ->assertSee('Ze vzpomínek vašich blízkých film')->assertSee('/zalozit')->assertDontSee('Mám přístupový kód');
        $this->withCookie('gm_tester', 'BETA-1234')->get('/zalozit')->assertOk()->assertSee('Testovací režim');
        $this->defaultCookies = [];
        $this->get('/zalozit')->assertSee('Vzpomínky, které se promítají.'); // veřejnost: coming soon
    }

    public function test_tester_codes_from_backoffice_count_uses_and_expire(): void
    {
        TesterCode::create(['code' => 'BETA-ABCD', 'max_uses' => 1]);
        $this->post('/pristup', ['code' => 'BETA-ABCD'])->assertRedirect('/');
        $this->assertSame(1, TesterCode::first()->uses);
        $this->post('/pristup', ['code' => 'BETA-ABCD'])->assertSessionHasErrors('code');
    }

    public function test_wrong_code_is_rejected(): void
    {
        $this->from('/')->post('/pristup', ['code' => 'NOPE'])->assertRedirect('/')->assertSessionHasErrors('code');
        $this->get('/pristup')->assertOk()->assertSee('Přístupový kód pro testování');
    }

    public function test_maintenance_returns_503_but_events_keep_working(): void
    {
        Setting::put('site_mode', 'maintenance');
        Event::create(['slug' => 'test-akce', 'title' => 'Test', 'recipient_name' => 'X', 'event_date' => now()->addWeek(), 'is_test' => true]);
        $this->get('/nejaka-stranka')->assertStatus(503)->assertSee('Jsme hned zpátky');
        $this->get('/test-akce')->assertOk();
    }

    public function test_backoffice_mode_overrides_env(): void
    {
        Setting::put('site_mode', 'live');
        $this->get('/ochrana-soukromi')->assertOk();
        $this->get('/nejaka-akce')->assertNotFound();
    }

    public function test_waitlist_signup_stores_email_and_notifies_ahoj(): void
    {
        Mail::fake();
        $this->post('/cekaci-listina', ['email' => 'Jana@Example.cz', 'consent' => '1'])->assertRedirect('/#cekaci-listina');
        $this->get('/')->assertSee('Děkujeme! Až spustíme');
        $this->post('/cekaci-listina', ['email' => 'jana@example.cz', 'consent' => '1']); // podruhé bez duplicity

        $this->assertSame(1, WaitlistEntry::count());
        $this->assertSame('jana@example.cz', WaitlistEntry::first()->email);
        Mail::assertSent(WaitlistSignup::class, fn ($m) => $m->hasTo('ahoj@gratulomat.cz'));
        Mail::assertSentCount(1);
    }

    public function test_waitlist_needs_consent_and_valid_email_and_ignores_bots(): void
    {
        Mail::fake();
        $this->post('/cekaci-listina', ['email' => 'jana@example.cz'])->assertSessionHasErrors('consent');
        $this->post('/cekaci-listina', ['email' => 'nic', 'consent' => '1'])->assertSessionHasErrors('email');
        $this->post('/cekaci-listina', ['email' => 'bot@example.cz', 'consent' => '1', 'web' => 'spam']);
        $this->assertSame(0, WaitlistEntry::count());
        Mail::assertNothingSent();
    }
}
