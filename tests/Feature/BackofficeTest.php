<?php

namespace Tests\Feature;

use App\Http\Controllers\Backoffice\BackofficeController;
use App\Mail\LoginLink;
use App\Models\Event;
use App\Models\Setting;
use App\Models\TesterCode;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BackofficeTest extends TestCase
{
    use RefreshDatabase;

    private User $op;

    protected function setUp(): void
    {
        parent::setUp();
        config(['gratulomat.site_mode' => 'coming_soon', 'gratulomat.operators' => ['adam@example.com']]);
        $this->op = User::create(['email' => 'adam@example.com', 'name' => 'Adam']);
    }

    public function test_only_operators_get_in(): void
    {
        $this->get('/backoffice')->assertRedirect('/prihlaseni');
        $this->actingAs(User::create(['email' => 'cizi@example.com', 'name' => 'X']))->get('/backoffice')->assertNotFound();
        $this->actingAs($this->op)->get('/backoffice')->assertOk()->assertSee('Přehled')->assertSee('Režim webu: Připravujeme');
    }

    public function test_operator_can_request_login_link_without_account(): void
    {
        Mail::fake();
        User::query()->delete();
        $this->post('/prihlaseni', ['email' => 'adam@example.com']);
        Mail::assertSent(LoginLink::class);
        $user = User::where('email', 'adam@example.com')->first();
        $this->get($user->loginLink())->assertRedirect('/backoffice');
    }

    public function test_operator_sees_events_and_can_open_any_event_admin(): void
    {
        $event = Event::create(['slug' => 'adamovych-50', 'title' => 'Adamových 50', 'recipient_name' => 'Adam', 'event_date' => now()->addMonth(), 'is_test' => true]);
        $event->show()->create([]);
        $this->actingAs($this->op)->get('/backoffice/udalosti')->assertOk()->assertSee('Adamových 50')->assertSee('TEST');
        $this->get('/adamovych-50/admin')->assertOk();
        $this->getJson('/adamovych-50/admin/api/state')->assertOk();
    }

    public function test_waitlist_list_export_and_delete(): void
    {
        $w = WaitlistEntry::create(['email' => 'jana@example.cz', 'consent_at' => now()]);
        $this->actingAs($this->op)->get('/backoffice/cekaci-listina')->assertOk()->assertSee('jana@example.cz');
        $csv = $this->get('/backoffice/cekaci-listina.csv')->assertOk()->streamedContent();
        $this->assertStringContainsString('jana@example.cz', $csv);
        $this->delete("/backoffice/cekaci-listina/{$w->id}")->assertRedirect();
        $this->assertSame(0, WaitlistEntry::count());
    }

    public function test_mode_switch_and_launch_requires_checklist_and_confirmation(): void
    {
        $this->actingAs($this->op);
        $this->post('/backoffice/rezim', ['mode' => 'maintenance'])->assertRedirect();
        $this->assertSame('maintenance', Setting::siteMode());

        $this->post('/backoffice/rezim', ['mode' => 'live', 'confirm' => 1])->assertStatus(422);
        $this->post('/backoffice/rezim/kontrola', ['checks' => array_keys(BackofficeController::CHECKS)])->assertRedirect();
        $this->post('/backoffice/rezim', ['mode' => 'live'])->assertStatus(422);
        $this->post('/backoffice/rezim', ['mode' => 'live', 'confirm' => 1])->assertRedirect();
        $this->assertSame('live', Setting::siteMode());
    }

    public function test_tester_codes_are_created_and_revoked(): void
    {
        $this->actingAs($this->op)->post('/backoffice/rezim/kody', ['label' => 'Rodina', 'max_uses' => 5])->assertSessionHas('ok');
        $code = TesterCode::first();
        $this->assertMatchesRegularExpression('/^BETA-[A-Z0-9]{4}$/', $code->code);
        $this->get('/backoffice/rezim')->assertSee($code->code)->assertSee('Rodina');
        $this->delete("/backoffice/rezim/kody/{$code->id}")->assertRedirect();
        $this->assertSame(0, TesterCode::count());
    }
}
