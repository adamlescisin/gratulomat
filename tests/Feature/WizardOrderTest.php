<?php

namespace Tests\Feature;

use App\Domain\Guest\EventRules;
use App\Domain\Orders\SlugService;
use App\Domain\Orders\StripeGateway;
use App\Mail\EventReady;
use App\Mail\FreeEventConfirm;
use App\Mail\InvoiceIssued;
use App\Mail\NewOrderNotice;
use App\Mail\VipInquiryReceived;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class WizardOrderTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripe $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['gratulomat.site_mode' => 'coming_soon', 'gratulomat.tester_codes' => ['BETA-1234'], 'services.worker.token' => 'wt-secret']);
        $this->stripe = new FakeStripe;
        $this->app->instance(StripeGateway::class, $this->stripe);
        $this->withCookie('gm_tester', 'BETA-1234');
    }

    private function config(array $o = []): array
    {
        return array_merge([
            'name' => 'Janiných 60', 'slug' => 'janinych-60', 'occasion' => 'birthday', 'date' => now()->addMonth()->toDateString(),
            'recipient' => 'person', 'recName' => 'Jana', 'startYear' => '1967', 'guests' => 5, 'theme' => 'zaklad', 'mode' => 'light',
            'voice' => false, 'nowm' => false, 'offline' => false, 'collect' => 14, 'archive' => 1,
        ], $o);
    }

    private function submit(array $config, array $extra = [])
    {
        return $this->post('/zalozit', array_merge(['config' => json_encode($config), 'email' => 'Jana@Example.cz', 'terms' => '1'], $extra));
    }

    public function test_slug_check_and_suggestions(): void
    {
        Event::create(['slug' => 'jana60', 'title' => 'X', 'recipient_name' => 'X', 'event_date' => now()]);
        $this->getJson('/api/slug-check?slug=Jana 60')->assertJson(['slug' => 'jana-60', 'status' => 'ok']);
        $r = $this->getJson('/api/slug-check?slug=jana60&year=2027')->assertJson(['status' => 'taken'])->json();
        $this->assertContains('jana60-2027', $r['suggestions']);
        $this->getJson('/api/slug-check?slug=admin')->assertJson(['status' => 'reserved']);
        $this->getJson('/api/slug-check?slug=ab')->assertJson(['status' => 'invalid']);
    }

    public function test_quote_is_computed_on_server(): void
    {
        $this->postJson('/api/quote', ['config' => $this->config()])->assertJson(['plan' => 'ochutnavka', 'total' => 0]);
        $this->postJson('/api/quote', ['config' => $this->config(['voice' => true, 'guests' => 30])])
            ->assertJson(['plan' => 'party', 'total' => 1280, 'plan_name' => 'Párty']);
        $this->postJson('/api/quote', ['config' => $this->config(['theme' => 'archiv', 'archive' => 12])])->assertJson(['plan' => 'premiera']);
        $this->postJson('/api/quote', ['config' => $this->config(['vipSetup' => true])])->assertJson(['inquiry' => true]);
        // smuteční: tichý vzhled je zdarma a hra se vypne
        $this->postJson('/api/quote', ['config' => $this->config(['occasion' => 'funeral', 'theme' => 'ticho', 'game' => true])])->assertJson(['plan' => 'ochutnavka']);
    }

    public function test_free_event_is_created_after_email_confirmation(): void
    {
        $this->submit($this->config())->assertRedirect();
        $order = Order::first();
        $this->assertSame('pending', $order->status);
        $this->assertTrue($order->is_test);
        Mail::assertSent(FreeEventConfirm::class, fn ($m) => $m->hasTo('jana@example.cz'));
        $this->get(route('wizard.done', ['objednavka' => $order->id]))->assertSee('Potvrďte e-mail');

        $link = URL::temporarySignedRoute('wizard.confirm', now()->addDay(), ['order' => $order->id]);
        $this->get($link)->assertRedirect();
        $this->get($link)->assertRedirect(); // podruhé nic nového nevznikne

        $event = Event::where('slug', 'janinych-60')->first();
        $this->assertNotNull($event);
        $this->assertSame(1, Event::count());
        $this->assertSame('ochutnavka', $event->plan);
        $this->assertSame('Janě', $event->form('dat'));
        $this->assertTrue($event->is_test);
        $this->assertTrue(User::where('email', 'jana@example.cz')->first()->organizes($event));
        Mail::assertSent(EventReady::class);
        Mail::assertSent(NewOrderNotice::class, fn ($m) => $m->hasTo('ahoj@gratulomat.cz'));
        $this->get(route('wizard.done', ['objednavka' => $order->id]))->assertSee('Hotovo!');
        $this->assertSame(0, WorkerJob::count());
    }

    public function test_paid_order_goes_to_stripe_and_webhook_creates_event(): void
    {
        $this->submit($this->config(['voice' => true, 'guests' => 40]))->assertRedirect('https://checkout.stripe.test/cs_test_1');
        $order = Order::first();
        $this->assertSame('cs_test_1', $order->stripe_session_id);
        $this->assertSame(1280, $order->total_halere / 100); // Párty + 1 balíček (40 hostů → 45)
        $this->assertSame('pending', $order->status);
        $this->assertSame('taken', SlugService::status('janinych-60'), 'adresa je během platby rezervovaná');

        $this->postJson('/api/stripe/webhook', FakeStripe::completed($order), ['Stripe-Signature' => 'ok'])->assertOk();
        $this->postJson('/api/stripe/webhook', FakeStripe::completed($order), ['Stripe-Signature' => 'ok'])->assertJson(['duplicate' => true]);

        $order->refresh();
        $this->assertSame('fulfilled', $order->status);
        $this->assertSame('pi_1', $order->stripe_payment_intent);
        $event = $order->event;
        $this->assertSame('party', $event->plan);
        $this->assertSame(45, $event->limit('guests'));
        $this->assertTrue(EventRules::has($event, 'wishes'));
        $this->assertFalse(EventRules::has($event, 'videos'));
        Mail::assertSent(EventReady::class);
        $this->assertSame(0, WorkerJob::count(), 'testovací objednávka nevystavuje doklad');
    }

    public function test_invalid_webhook_signature_is_rejected(): void
    {
        $this->postJson('/api/stripe/webhook', ['id' => 'evt_x'], ['Stripe-Signature' => 'bad'])->assertStatus(400);
    }

    public function test_live_order_queues_invoice_and_worker_delivers_pdf(): void
    {
        Setting::put('site_mode', 'live');
        $this->defaultCookies = []; // běžný zákazník po spuštění
        $this->submit($this->config(['voice' => true, 'guests' => 10]), [
            'company' => '1', 'company_name' => 'Firma s.r.o.', 'company_ico' => '12345678', 'company_dic' => 'cz12345678',
            'company_street' => 'Dlouhá 2', 'company_city' => 'Praha', 'company_zip' => '110 00',
        ])->assertRedirect('https://checkout.stripe.test/cs_test_1');
        $order = Order::first();
        $this->assertFalse($order->is_test);
        $this->assertStringStartsWith('G-', $order->number);
        $this->postJson('/api/stripe/webhook', FakeStripe::completed($order), ['Stripe-Signature' => 'ok'])->assertOk();

        $job = WorkerJob::first();
        $this->assertSame('invoice', $job->type);
        $this->assertSame('12345678', $job->payload['customer']['company']['ico']);
        $this->assertSame('CZ12345678', $job->payload['customer']['company']['dic']);
        $this->assertSame(99000, $job->payload['lines'][0]['unit_price_halere']);

        // worker na Mac Mini
        $this->getJson('/api/worker/jobs')->assertUnauthorized();
        $jobs = $this->getJson('/api/worker/jobs', ['Authorization' => 'Bearer wt-secret'])->assertOk()->json('data');
        $this->assertCount(1, $jobs);
        $this->assertCount(0, $this->getJson('/api/worker/jobs', ['Authorization' => 'Bearer wt-secret'])->json('data'), 'úloha je zamčená');

        $storage = sys_get_temp_dir().'/gm-inv-'.uniqid();
        File::ensureDirectoryExists("$storage/app");
        $this->app->useStoragePath($storage);
        config(['filesystems.disks.local.root' => "$storage/app/private"]);
        $this->post("/api/worker/jobs/{$job->id}/done", [
            'myucto_id' => 55, 'number' => '2027001', 'pdf' => UploadedFile::fake()->create('doklad.pdf', 20, 'application/pdf'),
        ], ['Authorization' => 'Bearer wt-secret'])->assertOk();

        $invoice = Invoice::first();
        $this->assertSame('sent', $invoice->status);
        $this->assertSame('2027001', $invoice->number);
        Mail::assertSent(InvoiceIssued::class, fn ($m) => $m->hasTo('jana@example.cz'));
        File::deleteDirectory($storage);
    }

    public function test_vip_inquiry_has_no_payment(): void
    {
        $this->submit($this->config(['vipSetup' => true]))->assertRedirect();
        $order = Order::first();
        $this->assertSame('inquiry', $order->status);
        Mail::assertSent(VipInquiryReceived::class);
        Mail::assertSent(NewOrderNotice::class);
        $this->assertNull($this->stripe->lastOrder);
    }

    public function test_validation_and_taken_slug(): void
    {
        Event::create(['slug' => 'janinych-60', 'title' => 'X', 'recipient_name' => 'X', 'event_date' => now()]);
        $this->submit($this->config())->assertSessionHasErrors('slug');
        $this->submit($this->config(['slug' => 'nova-adresa']), ['terms' => null])->assertSessionHasErrors('terms');
        $this->submit($this->config(['slug' => 'nova-adresa', 'date' => '2020-01-01']))->assertSessionHasErrors('date');
        $this->assertSame(0, Order::count());
    }
}

/** Náhrada Stripe pro testy. */
class FakeStripe extends StripeGateway
{
    public ?Order $lastOrder = null;

    public function createCheckout(Order $order): array
    {
        $this->lastOrder = $order;

        return ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/cs_test_1'];
    }

    public function verify(string $payload, string $signature): \Stripe\Event
    {
        if ($signature !== 'ok') {
            throw new \UnexpectedValueException('bad');
        }

        return \Stripe\Event::constructFrom(json_decode($payload, true));
    }

    public static function completed(Order $order): array
    {
        return ['id' => 'evt_'.$order->id, 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'object' => 'checkout.session', 'id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1',
            'client_reference_id' => $order->id, 'metadata' => ['order_id' => $order->id],
        ]]];
    }
}
