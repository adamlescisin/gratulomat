<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Legal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);

        return User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']);
    }

    public function test_default_test_texts_are_public_in_coming_soon(): void
    {
        $this->get('/obchodni-podminky')->assertOk()->assertSee('testovacím provozu');
        $this->get('/ochrana-soukromi')->assertOk()->assertSee('Proč a na jakém základě');
    }

    public function test_operator_sets_identity_texts_and_switch(): void
    {
        $this->actingAs($this->operator());
        $this->get('/backoffice/pravni')->assertOk()->assertSee('Identifikace provozovatele');

        $this->post('/backoffice/pravni', ['identity' => ['name' => 'Gratulomat s.r.o.', 'ico' => '123'], 'identity_' => 1])->assertSessionHasErrors('identity.ico');
        $this->post('/backoffice/pravni', ['identity' => ['name' => 'Gratulomat s.r.o.', 'ico' => '12345678', 'dic' => 'cz12345678', 'address' => 'Hostín 1, 277 32', 'email' => 'ahoj@gratulomat.cz']])
            ->assertSessionHas('ok');

        $this->post('/backoffice/pravni', ['variant' => 'live', 'terms' => "## 1. Úvod\n\nProvozovatel {provozovatel}, IČO {ico}.\n\n<script>alert(1)</script>", 'terms_from' => '2027-01-01', 'privacy' => '## Zásady (ostré)'])
            ->assertRedirect('/backoffice/pravni?varianta=live');

        // Připravujeme → testovací, ostré se zatím neukazují
        auth()->logout();
        $this->get('/obchodni-podminky')->assertSee('testovacím provozu')->assertSee('Provozovatel: Gratulomat s.r.o., IČO 12345678, Hostín 1, 277 32');

        // spuštění → ostré, zástupné znaky dosazené, HTML escapované
        Setting::put('site_mode', 'live');
        $this->get('/obchodni-podminky')->assertSee('Provozovatel Gratulomat s.r.o., IČO 12345678.')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Účinné od 1. ledna 2027');
        Setting::put('site_mode', 'maintenance');
        $this->assertSame('live', Legal::variant());

        // ruční přepínač
        $this->actingAs(User::first());
        $this->post('/backoffice/pravni', ['legal_variant' => 'test']);
        $this->assertSame('test', Legal::variant());
        $this->get('/backoffice/pravni')->assertSee('verze 1');
        $this->assertCount(2, Setting::get('legal_history'));

        // beze změny → žádná nová verze
        $this->post('/backoffice/pravni', ['variant' => 'live', 'terms' => "## 1. Úvod\n\nProvozovatel {provozovatel}, IČO {ico}.\n\n<script>alert(1)</script>", 'terms_from' => '2027-01-01', 'privacy' => '## Zásady (ostré)']);
        $this->assertCount(2, Setting::get('legal_history'));
    }
}
