<?php

namespace Tests\Feature;

use App\Domain\Guest\EventRules;
use App\Domain\Orders\StripeGateway;
use App\Mail\ArchiveEnding;
use App\Mail\GuestInvitation;
use App\Mail\UpgradeDone;
use App\Models\Event;
use App\Models\Household;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\FakeStripe;
use Tests\TestCase;

class LaunchBlockTest extends TestCase
{
    use RefreshDatabase;

    private function event(array $attrs = []): Event
    {
        $e = Event::create(['slug' => 'janinych-60', 'title' => 'Janiných 60', 'recipient_name' => 'Jana', 'event_date' => now()->addMonth(),
            'collect_until' => now()->addWeeks(3), 'archive_until' => now()->addMonths(2)->toDateString(), 'plan' => 'party',
            'limits' => ['guests' => 25, 'photos' => null, 'features' => ['wishes', 'no_watermark', 'offline', 'all_themes', 'dark_mode']], ...$attrs]);
        $u = User::firstOrCreate(['email' => 'org@example.cz'], ['name' => 'org']);
        $e->organizers()->syncWithoutDetaching([$u->id => ['role' => 'owner']]);
        $e->households()->create(['token' => 'tok12345678', 'label' => 'Petra', 'names' => ['Petra'], 'email' => 'petra@example.cz']);

        return $e;
    }

    public function test_archived_event_is_hidden_from_guests_but_not_organizer(): void
    {
        $e = $this->event(['archive_until' => now()->subDays(2)->toDateString()]);
        $this->get('/janinych-60/g/tok12345678');
        $this->get('/janinych-60')->assertStatus(410)->assertSee('Tato událost už skončila')->assertDontSee('name="kod"', false);
        $this->getJson('/janinych-60/api/me')->assertStatus(410);
        $this->get('/janinych-60/kino')->assertStatus(410);

        $this->actingAs(User::first());
        $this->get('/janinych-60/admin')->assertOk();
        $this->getJson('/janinych-60/admin/api/state')->assertJson(['event' => ['is_archived' => true]]);
    }

    public function test_archive_command_notifies_and_purges(): void
    {
        Mail::fake();
        $soon = $this->event(['archive_until' => now()->addDays(5)->toDateString()]);
        $old = Event::create(['slug' => 'stara-akce', 'title' => 'Stará', 'recipient_name' => 'X', 'event_date' => now()->subYear(), 'archive_until' => now()->subDays(40)->toDateString()]);
        $old->memories()->create(['id' => (string) Str::uuid(), 'household_id' => $old->households()->create(['token' => 'oldtok123', 'label' => 'A', 'names' => ['A']])->id, 'body' => 'x', 'status' => 'visible', 'authors' => ['A'], 'tags' => []]);
        File::ensureDirectoryExists($old->mediaDir('abc'));

        $this->artisan('gratulomat:archive --dry-run')->assertSuccessful();
        Mail::assertNothingSent();
        $this->artisan('gratulomat:archive')->assertSuccessful();
        Mail::assertSent(ArchiveEnding::class, fn ($m) => $m->hasTo('org@example.cz'));
        $this->assertSame('purged', $old->fresh()->status);
        $this->assertSame(0, $old->memories()->count());
        $this->assertDirectoryDoesNotExist(storage_path('app/media/'.$old->id));
        $this->artisan('gratulomat:archive');
        Mail::assertSentCount(1);
        $this->assertNotSame('purged', $soon->fresh()->status);
    }

    public function test_watermark_only_without_feature(): void
    {
        $e = $this->event();
        $this->actingAs(User::first());
        $this->getJson('/janinych-60/api/show')->assertJsonFragment(['watermark' => false]);
        $e->update(['plan' => 'ochutnavka', 'limits' => ['guests' => 5, 'photos' => 10, 'features' => []]]);
        $this->getJson('/janinych-60/api/show')->assertJsonFragment(['watermark' => true]);
    }

    public function test_upgrade_flow_with_stripe_and_invoice(): void
    {
        Mail::fake();
        config(['gratulomat.site_mode' => 'live']);
        Setting::put('site_mode', 'live');
        $this->app->instance(StripeGateway::class, $stripe = new FakeStripe);
        $e = $this->event();
        $this->actingAs(User::first());

        $q = $this->postJson('/janinych-60/admin/api/upgrade-quote', ['plan' => 'premiera', 'guest_packs' => 1, 'archive_years' => 1, 'photobook' => true])->assertOk()->json();
        $this->assertSame((1000 + 290 + 290 + 1290) * 100, $q['total']);
        $this->assertSame(170, $q['guests']);
        $this->postJson('/janinych-60/admin/api/upgrade-quote', ['plan' => 'ochutnavka'])->assertJsonPath('errors.0', 'Tarif nelze snížit.');
        $this->postJson('/janinych-60/admin/api/upgrade', ['plan' => 'party'])->assertStatus(422);

        $this->postJson('/janinych-60/admin/api/upgrade', ['plan' => 'premiera', 'guest_packs' => 1, 'archive_years' => 1, 'photobook' => true])
            ->assertOk()->assertJson(['url' => 'https://checkout.stripe.test/cs_test_1']);
        $order = Order::where('kind', 'upgrade')->first();
        $this->assertSame($e->id, $order->event_id);
        $this->assertFalse($order->is_test);

        $this->postJson('/api/stripe/webhook', FakeStripe::completed($order), ['Stripe-Signature' => 'ok'])->assertOk();
        $e->refresh();
        $this->assertSame('premiera', $e->plan);
        $this->assertSame(170, $e->limit('guests'));
        $this->assertTrue(EventRules::has($e, 'import'));
        $this->assertTrue($e->archive_until->gt(now()->addYear()));
        $this->assertSame('fulfilled', $order->fresh()->status);
        $this->assertSame('photobook', $order->fresh()->openTodo()[0]['code']);
        $this->assertSame(1, WorkerJob::where('type', 'invoice')->count());
        Mail::assertSent(UpgradeDone::class);

        // znovu webhook – nic se nezdvojí
        $this->postJson('/api/stripe/webhook', array_merge(FakeStripe::completed($order), ['id' => 'evt_other']), ['Stripe-Signature' => 'ok']);
        $this->assertSame(1, WorkerJob::where('type', 'invoice')->count(), json_encode(['jobs' => WorkerJob::all()->toArray(), 'order' => $order->fresh()->only(['status', 'is_test', 'total_halere', 'history'])]));
    }

    public function test_email_invitations_and_reminders(): void
    {
        Mail::fake();
        $e = $this->event(['plan' => 'premiera', 'limits' => ['guests' => 150, 'features' => ['reminders', 'import']]]);
        $e->households()->create(['token' => 'tok22222222', 'label' => 'Karel', 'names' => ['Karel']]);
        $this->actingAs(User::first());
        $this->postJson('/janinych-60/admin/api/send-invites')->assertJson(['queued' => 1]);
        $this->artisan('gratulomat:guest-mail')->assertSuccessful();
        Mail::assertSent(GuestInvitation::class, fn ($m) => $m->hasTo('petra@example.cz') && ! $m->reminder);
        $h = Household::where('email', 'petra@example.cz')->first();
        $this->assertNotNull($h->invited_at);
        $this->assertStringContainsString('/janinych-60/g/tok12345678', (new GuestInvitation($h))->render());

        // připomínka 3 dny před uzávěrkou
        $e->update(['collect_until' => now()->addDays(2)->addHours(12)]);
        $this->artisan('gratulomat:guest-mail');
        Mail::assertSent(GuestInvitation::class, fn ($m) => $m->reminder);
        $this->artisan('gratulomat:guest-mail');
        Mail::assertSentCount(2);

        // odhlášení
        auth()->logout();
        $this->get($h->unsubscribeUrl())->assertOk()->assertSee('další e-maily');
        $this->assertTrue($h->fresh()->email_opt_out);
        $this->get('/janinych-60/odhlasit-emaily/'.$h->id)->assertStatus(403);
    }

    public function test_failing_invitation_is_reported_and_stops_after_attempts(): void
    {
        $e = $this->event(['plan' => 'premiera', 'limits' => ['guests' => 150, 'features' => ['reminders']]]);
        $this->actingAs(User::first());
        $this->postJson('/janinych-60/admin/api/send-invites')->assertJson(['queued' => 1]);
        $this->assertSame('odesílá se', $this->getJson('/janinych-60/admin/api/state')->json('households.0.mail_status'));

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Resend: API key is invalid'));
        $this->artisan('gratulomat:guest-mail')->assertSuccessful();
        $this->assertNotNull(Setting::get('scheduler_heartbeat'));
        $h = Household::where('email', 'petra@example.cz')->first();
        $this->assertSame(1, $h->mail_attempts);
        $this->assertSame('odeslání selhává, zkoušíme znovu', $this->getJson('/janinych-60/admin/api/state')->json('households.0.mail_status'));

        for ($i = 0; $i < 6; $i++) {
            $this->artisan('gratulomat:guest-mail');
        }
        $h->refresh();
        $this->assertSame(5, $h->mail_attempts);
        $this->assertNull($h->invite_queued_at); // vzdali jsme to
        $state = $this->getJson('/janinych-60/admin/api/state')->json('households.0');
        $this->assertSame('nepodařilo se odeslat', $state['mail_status']);
        $this->assertSame('Resend: API key is invalid', $state['mail_error']);

        // back-office vidí plánovač i chybu
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']));
        $this->get('/backoffice')->assertOk()->assertSee('Plánovač a e-maily hostům')->assertSee('1 se nepodařilo odeslat')->assertSee('API key is invalid');

        // organizátor odešle znovu → chyba se vynuluje
        $this->actingAs(User::where('email', 'org@example.cz')->first());
        $this->postJson('/janinych-60/admin/api/send-invites')->assertJson(['queued' => 1]);
        $this->assertNull($h->fresh()->mail_error);
    }

    public function test_dashboard_warns_when_scheduler_never_ran(): void
    {
        config(['gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']));
        $this->get('/backoffice')->assertOk()->assertSee('Plánovač se ještě nespustil');
        $this->artisan('gratulomat:guest-mail');
        $this->get('/backoffice')->assertSee('V pořádku</strong> – naposledy', false);
    }
}
