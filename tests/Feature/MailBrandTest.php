<?php

namespace Tests\Feature;

use App\Mail\LoginLink;
use App\Models\Setting;
use App\Models\User;
use App\Support\MailBrand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailBrandTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_sets_mail_branding(): void
    {
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
        $storage = sys_get_temp_dir().'/gm-brand-'.uniqid();
        File::ensureDirectoryExists($storage);
        $this->app->useStoragePath($storage);
        $op = User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']);
        $this->actingAs($op);

        $this->get('/backoffice/emaily')->assertOk()->assertSee('Barva tlačítek');
        $this->post('/backoffice/emaily', ['color' => 'zelená'])->assertSessionHasErrors('color');
        $this->post('/backoffice/emaily', [
            'from_name' => 'Gratulomat.cz', 'color' => '#2ec4a0', 'footer' => "Gratulomat s.r.o.\nIČO 12345678", 'logo_width' => 150,
            'logo' => UploadedFile::fake()->image('logo.png', 300, 80),
        ])->assertRedirect('/backoffice/emaily');

        $this->assertSame('#2EC4A0', MailBrand::color());
        $this->assertNotNull(MailBrand::logoUrl());
        $html = (new LoginLink($op, 'https://example.test/x'))->render();
        $this->assertStringContainsString(MailBrand::logoUrl(), $html);
        $this->assertStringContainsString('IČO 12345678', $html);
        $this->assertStringContainsString('#2EC4A0', $html);
        $this->get(parse_url(MailBrand::logoUrl(), PHP_URL_PATH))->assertOk();
        $this->get('/backoffice/emaily/nahled')->assertOk()->assertSee('Gratulomat.cz', false);

        Mail::fake();
        $this->post('/backoffice/emaily/test')->assertRedirect();
        Mail::assertSent(LoginLink::class);

        $this->post('/backoffice/emaily', ['remove_logo' => 1]);
        $this->assertNull(MailBrand::logoUrl());
        File::deleteDirectory($storage);
    }

    public function test_sender_name_is_applied(): void
    {
        Setting::put('mail_from_name', 'Tým Gratulomatu');
        config(['mail.default' => 'array', 'mail.from.address' => 'ahoj@gratulomat.cz']);
        $u = User::create(['email' => 'x@example.cz', 'name' => 'x']);
        Mail::to($u)->send(new LoginLink($u, 'https://example.test'));
        $sent = app('mailer')->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame('Tým Gratulomatu', $sent->getFrom()[0]->getName());
    }
}
