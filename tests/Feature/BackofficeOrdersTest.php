<?php

namespace Tests\Feature;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\SlugService;
use App\Domain\Orders\StripeGateway;
use App\Mail\EventReady;
use App\Mail\PaymentLink;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeStripe;
use Tests\TestCase;

class BackofficeOrdersTest extends TestCase
{
    use RefreshDatabase;

    private RefundingStripe $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['gratulomat.operators' => ['op@gratulomat.cz'], 'services.worker.token' => 'wt']);
        $this->stripe = new RefundingStripe;
        $this->app->instance(StripeGateway::class, $this->stripe);
        $this->actingAs(User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']));
    }

    private function paidOrder(bool $test = false): Order
    {
        $o = Order::create([
            'number' => Order::nextNumber($test), 'kind' => 'initial', 'status' => 'pending', 'email' => 'jana@example.cz',
            'config' => ['name' => 'Janiných 60', 'slug' => 'janinych-60', 'occasion' => 'birthday', 'date' => now()->addMonth()->toDateString(), 'recipient' => 'person', 'recName' => 'Jana', 'guests' => 20, 'voice' => true, 'collect' => 14, 'archive' => 1],
            'slug' => 'janinych-60', 'plan' => 'party', 'lines' => [['code' => 'party', 'name' => 'Tarif Párty', 'qty' => 1, 'unit_price' => 99000, 'total' => 99000]],
            'total_halere' => 99000, 'vat_halere' => 17182, 'is_test' => $test, 'stripe_payment_intent' => 'pi_1',
        ]);
        $o->update(['status' => 'paid', 'paid_at' => now()]);
        app(OrderService::class)->fulfill($o);

        return $o->fresh();
    }

    public function test_list_filters_and_csv(): void
    {
        $this->paidOrder();
        Order::create(['number' => 'T-2026-0009', 'kind' => 'vip', 'status' => 'inquiry', 'email' => 'vip@example.cz', 'config' => ['name' => 'Firma 25'], 'slug' => 'firma-25', 'plan' => 'vip', 'lines' => [], 'total_halere' => 499000, 'vat_halere' => 0, 'is_test' => true]);

        $this->get('/backoffice/objednavky')->assertOk()->assertSee('990 Kč')->assertSee('Firma 25')->assertSee('Otevřené VIP poptávky');
        $this->get('/backoffice/objednavky?stav=inquiry')->assertSee('Firma 25')->assertDontSee('Janiných 60');
        $this->get('/backoffice/objednavky?q=jana@')->assertSee('Janiných 60')->assertDontSee('Firma 25');
        $csv = $this->get('/backoffice/objednavky.csv?test=ostre')->assertOk()->streamedContent();
        $this->assertStringContainsString('Janiných 60' === '' ? '' : 'janinych-60', $csv);
        $this->assertStringContainsString('990,00', $csv);
        $this->assertStringNotContainsString('firma-25', $csv);
    }

    public function test_partial_refund_creates_credit_note_job_once(): void
    {
        $o = $this->paidOrder();
        $inv = Invoice::where('kind', 'invoice')->first();
        $this->get("/backoffice/objednavky/{$o->id}")->assertOk()->assertSee('Vrátit peníze');

        $this->post("/backoffice/objednavky/{$o->id}/vratit", ['amount' => 2000, 'reason' => 'x'])->assertSessionHasErrors('amount');
        $this->post("/backoffice/objednavky/{$o->id}/vratit", ['amount' => 290, 'reason' => 'Méně hostů'])->assertSessionHas('ok');
        $o->refresh();
        $this->assertSame(29000, $o->refunded_halere);
        $this->assertSame('částečně vráceno', $o->statusLabel());
        $this->assertSame([29000], $this->stripe->refunds);

        // webhook charge.refunded se stejným součtem nic nezdvojí
        app(OrderService::class)->recordRefund($o, 29000, 'vráceno ve Stripe');
        $notes = WorkerJob::where('type', 'credit_note')->get();
        $this->assertCount(1, $notes);
        $this->assertSame(29000, $notes[0]->payload['refund_halere']);
        $this->assertFalse($notes[0]->payload['full']);

        // dobropis čeká na vystavení původního dokladu
        $h = ['Authorization' => 'Bearer wt'];
        $types = fn () => collect($this->getJson('/api/worker/jobs', $h)->json('data'))->pluck('type')->all();
        $this->assertSame(['invoice'], $types());
        $inv->update(['myucto_id' => 77, 'status' => 'sent']);
        WorkerJob::where('type', 'invoice')->update(['status' => 'done']);
        $jobs = $this->getJson('/api/worker/jobs', $h)->json('data');
        $this->assertSame('credit_note', $jobs[0]['type']);
        $this->assertSame(77, $jobs[0]['payload']['invoice_myucto_id']);

        // zbytek
        $this->post("/backoffice/objednavky/{$o->id}/vratit", ['reason' => 'Zrušená oslava']);
        $this->assertSame(99000, $o->fresh()->refunded_halere);
        $this->assertSame('vráceno', $o->fresh()->statusLabel());
        $this->assertSame(0, $o->fresh()->refundableHalere());
    }

    public function test_test_order_refund_has_no_credit_note(): void
    {
        $o = $this->paidOrder(true);
        $this->post("/backoffice/objednavky/{$o->id}/vratit", ['reason' => 'test']);
        $this->assertSame(99000, $o->fresh()->refunded_halere);
        $this->assertSame([null], $this->stripe->refunds, 'celá částka bez amount');
        $this->assertSame(0, WorkerJob::where('type', 'credit_note')->count());
    }

    public function test_webhook_refund_from_stripe_dashboard(): void
    {
        $o = $this->paidOrder();
        $payload = ['id' => 'evt_r', 'object' => 'event', 'type' => 'charge.refunded', 'data' => ['object' => ['object' => 'charge', 'payment_intent' => 'pi_1', 'amount_refunded' => 99000]]];
        $this->postJson('/api/stripe/webhook', $payload, ['Stripe-Signature' => 'ok'])->assertOk();
        $this->assertSame(99000, $o->fresh()->refunded_halere);
        $this->assertTrue(WorkerJob::where('type', 'credit_note')->first()->payload['full']);
    }

    public function test_vip_inquiry_payment_link_and_manual(): void
    {
        $o = Order::create(['number' => 'G-2026-0050', 'kind' => 'vip', 'status' => 'inquiry', 'email' => 'vip@example.cz',
            'config' => ['name' => 'Firma 25', 'slug' => 'firma-25', 'occasion' => 'company', 'date' => now()->addMonth()->toDateString(), 'recipient' => 'company', 'recName' => 'Firma', 'guests' => 200, 'vipSetup' => true],
            'slug' => 'firma-25', 'plan' => 'vip', 'lines' => [], 'total_halere' => 499000, 'vat_halere' => 0, 'is_test' => false]);
        $this->post("/backoffice/objednavky/{$o->id}/vip-odkaz", ['price' => 7500])->assertSessionHas('ok');
        $o->refresh();
        $this->assertSame('pending', $o->status);
        $this->assertSame(750000, $o->total_halere);
        $this->assertSame(130165, $o->vat_halere);
        $this->assertSame(60 * 23, $this->stripe->lastExpires);
        Mail::assertSent(PaymentLink::class, fn ($m) => $m->hasTo('vip@example.cz'));

        $o2 = Order::create(['number' => 'G-2026-0051', 'kind' => 'vip', 'status' => 'inquiry', 'email' => 'vip2@example.cz',
            'config' => ['name' => 'Firma 30', 'slug' => 'firma-30', 'occasion' => 'company', 'date' => now()->addMonth()->toDateString(), 'recipient' => 'company', 'recName' => 'Firma', 'guests' => 200],
            'slug' => 'firma-30', 'plan' => 'vip', 'lines' => [], 'total_halere' => 499000, 'vat_halere' => 0, 'is_test' => false]);
        $this->post("/backoffice/objednavky/{$o2->id}/vip-rucne")->assertSessionHas('ok');
        $this->assertSame('fulfilled', $o2->fresh()->status);
        $this->assertSame('vip', $o2->fresh()->event->plan);
        $this->assertSame(0, WorkerJob::count());
    }

    public function test_resend_cancel_note_and_retry(): void
    {
        $o = $this->paidOrder();
        $this->post("/backoffice/objednavky/{$o->id}/znovu-odeslat")->assertSessionHas('ok');
        Mail::assertSent(EventReady::class, 2);
        $this->post("/backoffice/objednavky/{$o->id}/poznamka", ['note' => 'Volala, chce fotoknihu']);
        $this->assertSame('Volala, chce fotoknihu', $o->fresh()->note);

        $job = WorkerJob::first();
        $job->update(['status' => 'failed', 'attempts' => 5, 'error' => 'MyÚčto nedostupné']);
        $this->get("/backoffice/objednavky/{$o->id}")->assertSee('MyÚčto nedostupné');
        $this->post("/backoffice/objednavky/{$o->id}/uloha/{$job->id}");
        $this->assertSame('queued', $job->fresh()->status);

        $p = Order::create(['number' => 'G-2026-0060', 'kind' => 'initial', 'status' => 'pending', 'email' => 'x@example.cz', 'config' => ['name' => 'X'], 'slug' => 'x-akce', 'plan' => 'party', 'lines' => [], 'total_halere' => 99000, 'vat_halere' => 0, 'is_test' => false, 'expires_at' => now()->addMinutes(30)]);
        $this->assertSame('taken', SlugService::status('x-akce'));
        $this->post("/backoffice/objednavky/{$p->id}/zrusit");
        $this->assertSame('ok', SlugService::status('x-akce'));
    }

    public function test_non_operator_cannot_access(): void
    {
        $this->actingAs(User::create(['email' => 'jana@example.cz', 'name' => 'j']));
        $this->get('/backoffice/objednavky')->assertNotFound();
    }
}

class RefundingStripe extends FakeStripe
{
    public array $refunds = [];

    public int $refunded = 0;

    public ?int $lastExpires = null;

    public function createCheckout(Order $order, int $expiresMinutes = 35): array
    {
        $this->lastExpires = $expiresMinutes;

        return parent::createCheckout($order, $expiresMinutes);
    }

    public function refund(Order $order, ?int $amountHalere = null): int
    {
        $this->refunds[] = $amountHalere;
        $this->refunded += $amountHalere ?? ($order->total_halere - $order->refunded_halere);

        return $this->refunded;
    }
}
