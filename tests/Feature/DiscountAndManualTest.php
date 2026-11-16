<?php

namespace Tests\Feature;

use App\Domain\Orders\StripeGateway;
use App\Mail\EventReady;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\FakeStripe;
use Tests\TestCase;

class DiscountAndManualTest extends TestCase
{
    use RefreshDatabase;

    private FakeStripe $stripe;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['gratulomat.site_mode' => 'coming_soon', 'gratulomat.tester_codes' => ['BETA-1234'], 'gratulomat.operators' => ['op@gratulomat.cz']]);
        $this->stripe = new FakeStripe;
        $this->app->instance(StripeGateway::class, $this->stripe);
        $this->withCookie('gm_tester', 'BETA-1234');
    }

    private function config(array $o = []): array
    {
        return array_merge([
            'name' => 'Petrových 40', 'slug' => 'petrovych-40', 'occasion' => 'birthday', 'date' => now()->addMonth()->toDateString(),
            'recipient' => 'person', 'recName' => 'Petr', 'guests' => 20, 'voice' => true, 'collect' => 14, 'archive' => 1,
        ], $o);
    }

    private function operator(): User
    {
        return User::create(['email' => 'op@gratulomat.cz', 'name' => 'op']);
    }

    public function test_operator_manages_discount_codes(): void
    {
        $this->get('/backoffice/slevy')->assertRedirect();
        $this->actingAs($this->operator());
        $this->post('/backoffice/slevy', ['code' => 'jaro 20', 'percent' => 20, 'max_uses' => 2])->assertRedirect('/backoffice/slevy');
        $this->post('/backoffice/slevy', ['code' => 'JARO20', 'percent' => 10])->assertSessionHasErrors('code');
        $this->post('/backoffice/slevy', ['code' => 'XX', 'percent' => 150])->assertSessionHasErrors(['code', 'percent']);
        $code = DiscountCode::first();
        $this->assertSame('JARO20', $code->code);
        $this->get('/backoffice/slevy')->assertSee('JARO20')->assertSee('0 / 2');
        $this->post("/backoffice/slevy/{$code->id}/prepnout");
        $this->assertFalse($code->fresh()->active);
        $this->delete("/backoffice/slevy/{$code->id}");
        $this->assertSame(0, DiscountCode::count());
    }

    public function test_quote_applies_valid_discount(): void
    {
        DiscountCode::create(['code' => 'JARO20', 'percent' => 20]);
        DiscountCode::create(['code' => 'STARY', 'percent' => 50, 'valid_until' => now()->subDay()]);

        $q = $this->postJson('/api/quote', ['config' => $this->config(['discount' => 'jaro20'])])->assertOk()->json();
        $this->assertSame(792, $q['total']);
        $this->assertTrue($q['discount']['valid']);
        $this->assertSame(-19800, end($q['lines'])['total']);

        $q = $this->postJson('/api/quote', ['config' => $this->config(['discount' => 'STARY'])])->json();
        $this->assertSame(990, $q['total']);
        $this->assertFalse($q['discount']['valid']);
    }

    public function test_discounted_order_through_stripe_counts_usage(): void
    {
        DiscountCode::create(['code' => 'JARO20', 'percent' => 20, 'max_uses' => 1]);
        $this->post('/zalozit', ['config' => json_encode($this->config(['discount' => 'JARO20'])), 'email' => 'p@example.cz', 'terms' => '1'])
            ->assertRedirect('https://checkout.stripe.test/cs_test_1');
        $order = Order::first();
        $this->assertSame(79200, $order->total_halere);
        $this->assertSame(19800, $order->discount_halere);
        $this->assertSame('JARO20', $order->discount_code);

        $this->postJson('/api/stripe/webhook', FakeStripe::completed($order), ['Stripe-Signature' => 'ok'])->assertOk();
        $this->assertSame(1, DiscountCode::first()->used_count);
        // vyčerpaný kód už neprojde
        $this->post('/zalozit', ['config' => json_encode($this->config(['slug' => 'jina-akce', 'discount' => 'JARO20'])), 'email' => 'p@example.cz', 'terms' => '1'])
            ->assertSessionHasErrors('discount');
    }

    public function test_full_discount_makes_paid_plan_free(): void
    {
        DiscountCode::create(['code' => 'ZDARMA', 'percent' => 100]);
        $this->post('/zalozit', ['config' => json_encode($this->config(['discount' => 'ZDARMA'])), 'email' => 'p@example.cz', 'terms' => '1'])->assertRedirect();
        $order = Order::first();
        $this->assertSame(0, $order->total_halere);
        $this->assertSame('party', $order->plan);
        $this->assertNull($this->stripe->lastOrder);
    }

    public function test_operator_creates_event_manually_without_payment(): void
    {
        $this->actingAs($this->operator());
        $this->get('/zalozit')->assertSee('Založit ručně bez platby');
        $this->post('/zalozit', ['config' => json_encode($this->config(['vipSetup' => true, 'guests' => 200])), 'email' => 'organizator@example.cz', 'manual' => '1'])
            ->assertRedirect();

        $order = Order::first();
        $this->assertSame('manual', $order->kind);
        $this->assertSame('fulfilled', $order->status);
        $this->assertNull($this->stripe->lastOrder);
        $event = Event::where('slug', 'petrovych-40')->first();
        $this->assertSame('vip', $event->plan);
        $this->assertTrue(User::where('email', 'organizator@example.cz')->first()->organizes($event));
        Mail::assertSent(EventReady::class, fn ($m) => $m->hasTo('organizator@example.cz'));
        $this->assertSame(0, WorkerJob::count());
    }

    public function test_manual_flag_is_ignored_for_non_operators(): void
    {
        $this->actingAs(User::create(['email' => 'jana@example.cz', 'name' => 'jana']));
        $this->get('/zalozit')->assertDontSee('Založit ručně bez platby');
        $this->post('/zalozit', ['config' => json_encode($this->config()), 'email' => 'jana@example.cz', 'manual' => '1'])
            ->assertSessionHasErrors('terms');
        $this->post('/zalozit', ['config' => json_encode($this->config()), 'email' => 'jana@example.cz', 'manual' => '1', 'terms' => '1'])
            ->assertRedirect('https://checkout.stripe.test/cs_test_1');
        $this->assertSame('initial', Order::first()->kind);
    }
}
