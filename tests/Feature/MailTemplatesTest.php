<?php

namespace Tests\Feature;

use App\Mail\LoginLink;
use App\Mail\TemplatePreview;
use App\Models\User;
use App\Support\MailTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->op = User::create(['email' => 'op@gratulomat.cz', 'name' => 'Operátor']);
    }

    public function test_every_default_template_renders_with_sample_data(): void
    {
        foreach (array_keys(MailTemplates::all()) as $key) {
            $m = new TemplatePreview($key, MailTemplates::sample($key));
            $html = $m->render();
            $this->assertNotSame('', $m->envelope()->subject, $key);
            $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}|\[\/?(pokud|tlacitko)/', $html.$m->envelope()->subject, $key);
            $this->assertSame([], MailTemplates::unknownPlaceholders($key, MailTemplates::definition($key)['body']), $key);
        }
    }

    public function test_default_login_link_keeps_original_text(): void
    {
        $u = User::create(['email' => 'jana@example.cz', 'name' => 'Jana']);
        $m = new LoginLink($u, 'https://example.test/link');
        $html = $m->render();
        $this->assertSame('Přihlášení do Gratulomatu', $m->envelope()->subject);
        $this->assertStringContainsString('Dobrý den, Jana,', $html);
        $this->assertStringContainsString('href="https://example.test/link"', $html);
        $this->assertStringContainsString('Přihlásit se', $html);

        $anon = User::create(['email' => 'x@example.cz', 'name' => '']);
        $this->assertStringContainsString('Dobrý den,</p>', preg_replace('/\s+(?=<)/', '', (new LoginLink($anon, 'https://e.test'))->render()));
    }

    public function test_operator_edits_template_with_placeholders_buttons_and_conditions(): void
    {
        $this->actingAs($this->op);
        $this->get('/backoffice/emaily')->assertOk()->assertSee('Šablony e-mailů zákazníkům')->assertSee('Přihlašovací odkaz');
        $this->get('/backoffice/emaily/sablony/login_link')->assertOk()->assertSee('{jmeno}', false)->assertSee('[tlacitko prihlaseni]', false);
        $this->get('/backoffice/emaily/sablony/neexistuje')->assertNotFound();

        $this->post('/backoffice/emaily/sablony/login_link', [
            'subject' => 'Váš vstup[pokud jmeno] pro {jmeno}[/pokud]',
            'body' => "Ahoj[pokud jmeno] {jmeno}[/pokud]!\n\n[tlacitko prihlaseni]Vstoupit do {web}[/tlacitko]\n\n[pokud ne jmeno]Bez jména.[/pokud]\n\n{neznamy} <b>x</b>",
        ])->assertRedirect('/backoffice/emaily/sablony/login_link')->assertSessionHas('ok', fn ($m) => str_contains($m, '{neznamy}') === false && str_contains($m, 'neznamy'));

        $u = User::create(['email' => 'jana@example.cz', 'name' => 'Jana <script>']);
        $m = new LoginLink($u, 'https://example.test/link');
        $html = $m->render();
        $this->assertSame('Váš vstup pro Jana <script>', $m->envelope()->subject);
        $this->assertStringContainsString('Ahoj Jana &lt;script&gt;!', $html);
        $this->assertStringNotContainsString('Bez jména', $html);
        $this->assertStringContainsString('href="https://example.test/link"', $html);
        $this->assertMatchesRegularExpression('/Vstoupit do [^<]*localhost|Vstoupit do /', $html);
        $this->assertStringContainsString('{neznamy}', $html);
        $this->get('/backoffice/emaily')->assertSee('upraveno');

        // reset na výchozí text
        $this->post('/backoffice/emaily/sablony/login_link', ['action' => 'reset'])->assertRedirect();
        $this->assertNull(MailTemplates::saved('login_link'));
        $this->assertStringContainsString('Přihlásit se', (new LoginLink($u, 'https://e.test'))->render());
    }

    public function test_saving_unchanged_default_keeps_template_default(): void
    {
        $this->actingAs($this->op);
        $d = MailTemplates::definition('archive_ending');
        $this->post('/backoffice/emaily/sablony/archive_ending', ['subject' => $d['subject'], 'body' => str_replace("\n", "\r\n", $d['body'])])->assertRedirect();
        $this->assertNull(MailTemplates::saved('archive_ending'));
    }

    public function test_preview_and_test_mail_use_unsaved_text(): void
    {
        $this->actingAs($this->op);
        $this->get('/backoffice/emaily/sablony/event_ready/nahled')->assertOk()->assertSee('Janiných 60 je připravená', false)->assertSee('Předmět:', false);
        $this->post('/backoffice/emaily/sablony/event_ready/nahled', ['subject' => 'Hotovo: {nazev_udalosti}', 'body' => 'Neuložený text[pokud zaplaceno] za {cena}[/pokud].'])
            ->assertOk()->assertSee('Hotovo: Janiných 60', false)->assertSee('Neuložený text za 990 Kč.', false);

        Mail::fake();
        $this->post('/backoffice/emaily/sablony/event_ready/test', ['subject' => 'Hotovo: {nazev_udalosti}', 'body' => 'Zkouška'])->assertRedirect()->assertSessionHas('ok');
        Mail::assertSent(TemplatePreview::class, fn ($m) => $m->hasTo('op@gratulomat.cz') && $m->envelope()->subject === '[TEST] Hotovo: Janiných 60');
        $this->assertNull(MailTemplates::saved('event_ready'));

        $this->post('/backoffice/emaily/sablony/event_ready/test', ['subject' => '', 'body' => 'x'])->assertSessionHasErrors('subject');
    }

    public function test_non_operator_cannot_edit_templates(): void
    {
        $this->actingAs(User::create(['email' => 'org@example.cz', 'name' => 'Org']));
        $this->get('/backoffice/emaily/sablony/login_link')->assertNotFound(); // back-office se neoperátorům neprozrazuje
    }
}
