<?php

namespace App\Domain\Orders;

use App\Domain\Pricing\PricingService;
use App\Mail\EventReady;
use App\Mail\FreeEventConfirm;
use App\Mail\NewOrderNotice;
use App\Mail\PaymentLink;
use App\Mail\UpgradeDone;
use App\Models\DiscountCode;
use App\Models\Event;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\WorkerJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/** Objednávka z průvodce → platba → založení události, organizátor, e-maily, doklad. */
class OrderService
{
    public function __construct(private PricingService $pricing) {}

    /** Cena objednávky včetně případné slevy (jediný zdroj pravdy pro průvodce i objednávku). */
    public function quote(WizardConfig $cfg): array
    {
        $quote = $this->pricing->quote($cfg->eventConfig());
        $discount = DiscountCode::usable($cfg->data['discount'] ?? null);
        if ($discount) {
            $quote = $this->pricing->applyDiscount($quote, $discount->percent, $discount->code);
        }

        return [$quote, $quote->isInquiry ? null : $discount];
    }

    /**
     * @param  bool  $manual  provozovatel zakládá ručně bez platby (i VIP) – bez Stripe a bez dokladu
     */
    public function create(WizardConfig $cfg, string $email, ?array $company, bool $isTest, bool $manual = false): Order
    {
        [$quote, $discount] = $this->quote($cfg);

        return DB::transaction(function () use ($cfg, $email, $company, $isTest, $quote, $discount, $manual) {
            if (SlugService::status($cfg->data['slug']) !== 'ok') {
                throw new RuntimeException('Tahle adresa už je obsazená. Zvolte prosím jinou.');
            }

            $inquiry = $quote->isInquiry && ! $manual;
            $discountHalere = (int) -collect($quote->lines)->where('code', 'discount')->sum('total');

            return Order::create([
                'number' => Order::nextNumber($isTest),
                'kind' => $manual ? 'manual' : ($quote->isInquiry ? 'vip' : 'initial'),
                'status' => $inquiry ? 'inquiry' : 'pending',
                'email' => mb_strtolower($email),
                'config' => $cfg->data,
                'company' => $company,
                'slug' => $cfg->data['slug'],
                'plan' => $quote->plan,
                // ruční založení provozovatelem: bez ceny a bez položek (nic se neúčtuje)
                'lines' => $manual ? [] : $quote->lines,
                'total_halere' => $manual ? 0 : $quote->totalHalere,
                'vat_halere' => $manual ? 0 : $quote->vatHalere,
                'discount_code' => $manual ? null : $discount?->code,
                'discount_percent' => $manual ? null : $discount?->percent,
                'discount_halere' => $manual ? 0 : $discountHalere,
                'is_test' => $isTest,
                'expires_at' => now()->addMinutes($quote->totalHalere === 0 ? 60 * 24 : 30),
            ]);
        });
    }

    /** Ochutnávka: založení až po potvrzení e-mailu (ochrana proti zneužití). */
    public function sendFreeConfirmation(Order $order): void
    {
        $link = URL::temporarySignedRoute('wizard.confirm', now()->addDay(), ['order' => $order->id]);
        Mail::to($order->email)->send(new FreeEventConfirm($order, $link));
    }

    /** Zaplaceno (webhook) nebo potvrzeno (Ochutnávka) → událost. Idempotentní. */
    public function fulfill(Order $order, ?string $paymentIntent = null): Event
    {
        if ($order->kind === 'upgrade') {
            return $this->fulfillUpgrade($order, $paymentIntent);
        }
        $event = DB::transaction(function () use ($order, $paymentIntent) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($order->event_id) {
                return $order->event;
            }
            $cfg = WizardConfig::fromRequest($order->config);
            $guestLimit = (int) collect($order->lines)->where('code', 'guest_pack')->sum('qty') * config('gratulomat.addons.guest_pack.size')
                + config("gratulomat.plans.{$order->plan}.guests");

            $attrs = $cfg->eventAttributes($order->plan, $guestLimit, $order->is_test);
            if (Event::withTrashed()->where('slug', $attrs['slug'])->exists()) {
                // adresu mezitím někdo obsadil (nemělo by nastat díky rezervaci) – přidáme číslo objednávky
                $attrs['slug'] = substr($attrs['slug'], 0, 24).'-'.substr(strtolower($order->id), -5);
            }
            $event = Event::create($attrs);
            $event->show()->create(['game_enabled' => in_array('game', $cfg->features(), true) && ! $cfg->isFuneral()]);

            $user = User::firstOrCreate(['email' => $order->email], ['name' => strstr($order->email, '@', true)]);
            $event->organizers()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

            $order->update([
                'status' => 'fulfilled', 'event_id' => $event->id,
                'paid_at' => $order->total_halere > 0 && $order->kind !== 'manual' ? now() : null,
                'stripe_payment_intent' => $paymentIntent ?? $order->stripe_payment_intent,
            ]);

            $this->assignTodo($order);
            if ($order->discount_code) {
                DiscountCode::where('code', $order->discount_code)->increment('used_count');
            }
            if ($order->total_halere > 0 && ! $order->is_test && $order->kind !== 'manual') {
                $this->queueInvoice($order);
            }

            return $event;
        });

        $order->refresh();
        try {
            $user = User::where('email', $order->email)->first();
            Mail::to($order->email)->send(new EventReady($order, $event, $user->loginLink(60 * 24 * 7)));
            Mail::to(config('gratulomat.notify_email'))->send(new NewOrderNotice($order, $event));
        } catch (Throwable $e) {
            Log::error('E-mail k objednávce se nepodařilo odeslat: '.$e->getMessage(), ['order' => $order->number]);
        }

        return $event;
    }

    /** Úkoly pro provozovatele (fotokniha, VIP služby) – vyřizují se ručně v back-office. */
    public function assignTodo(Order $order): void
    {
        $todo = [];
        if (collect($order->lines)->contains('code', 'photobook')) {
            $todo[] = ['code' => 'photobook', 'name' => 'Fotokniha – připravit návrh a domluvit tisk', 'done_at' => null];
        }
        $cfg = $order->config ?? [];
        if ($order->plan === 'vip' || ! empty($cfg['vipSetup'])) {
            $todo[] = ['code' => 'vip_setup', 'name' => 'VIP – nastavení události a pozvánky za zákazníka', 'done_at' => null];
        }
        if (! empty($cfg['vipEdit'])) {
            $todo[] = ['code' => 'vip_edit', 'name' => 'VIP – sestřih filmu na míru', 'done_at' => null];
        }
        if ($todo) {
            $order->todo = [...($order->todo ?? []), ...$todo];
            $order->save();
        }
    }

    /** Navýšení tarifu / doplňky k existující události (idempotentní). */
    private function fulfillUpgrade(Order $order, ?string $paymentIntent): Event
    {
        $done = DB::transaction(function () use ($order, $paymentIntent) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($order->status === 'fulfilled' || ! empty($order->config['upgrade']['applied_at'])) {
                return false;
            }
            app(UpgradeService::class)->apply($order);
            $order->config = [...$order->config, 'upgrade' => [...$order->config['upgrade'], 'applied_at' => now()->toIso8601String()]];
            $this->assignTodo($order);
            $order->update(['status' => 'fulfilled', 'paid_at' => now(), 'stripe_payment_intent' => $paymentIntent ?? $order->stripe_payment_intent]);
            $order->log('Navýšení provedeno: tarif '.config("gratulomat.plans.{$order->plan}.name").', hosté '.$order->event->limit('guests').', archiv do '.$order->event->archive_until->toDateString());
            if ($order->total_halere > 0 && ! $order->is_test) {
                $this->queueInvoice($order);
            }

            return true;
        });
        $order->refresh();
        if ($done) {
            try {
                Mail::to($order->email)->send(new UpgradeDone($order));
                Mail::to(config('gratulomat.notify_email'))->send(new NewOrderNotice($order, $order->event));
            } catch (Throwable $e) {
                Log::error('E-mail k navýšení se nepodařilo odeslat: '.$e->getMessage(), ['order' => $order->number]);
            }
        }

        return $order->event;
    }

    /**
     * Vrácení peněz (z back-office nebo webhooku charge.refunded). $totalRefunded je celková vrácená částka
     * podle Stripe, takže opakované volání nic nezdvojí. Za rozdíl se vystaví dobropis.
     */
    public function recordRefund(Order $order, int $totalRefunded, string $reason, ?string $by = null): int
    {
        return DB::transaction(function () use ($order, $totalRefunded, $reason, $by) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();
            $delta = min($totalRefunded, $order->total_halere) - $order->refunded_halere;
            if ($delta <= 0) {
                return 0;
            }
            $order->refunded_halere += $delta;
            $order->refunded_at = now();
            $order->log('Vráceno '.Order::czk($delta).' – '.$reason, $by);

            $invoice = $order->invoices()->where('kind', 'invoice')->first();
            if ($invoice && ! $order->is_test) {
                $note = Invoice::create(['order_id' => $order->id, 'kind' => 'credit_note', 'status' => 'queued', 'amount_halere' => -$delta, 'source_invoice_id' => $invoice->id]);
                WorkerJob::create([
                    'type' => 'credit_note', 'subject_type' => Invoice::class, 'subject_id' => $note->id,
                    'payload' => [
                        'order_number' => $order->number, 'invoice_id' => $invoice->id, 'refund_halere' => $delta,
                        'full' => $order->refunded_halere >= $order->total_halere && $delta === $order->total_halere,
                        'reason' => mb_substr($reason, 0, 200), 'vat_rate' => config('gratulomat.vat_rate'),
                    ],
                ]);
            }

            return $delta;
        });
    }

    /** VIP poptávka → individuální cena a platební odkaz Stripe (platí 23 hodin). */
    public function vipPaymentLink(Order $order, int $amountHalere, StripeGateway $stripe, ?string $by = null): string
    {
        $vat = $amountHalere - (int) round($amountHalere * 100 / (100 + config('gratulomat.vat_rate')));
        $order->update([
            'plan' => 'vip', 'lines' => [['code' => 'vip', 'name' => 'Tarif VIP', 'qty' => 1, 'unit_price' => $amountHalere, 'total' => $amountHalere]],
            'total_halere' => $amountHalere, 'vat_halere' => $vat, 'status' => 'pending', 'expires_at' => now()->addHours(23),
        ]);
        $session = $stripe->createCheckout($order->fresh(), 60 * 23);
        $order->update(['stripe_session_id' => $session['id'], 'payment_url' => $session['url']]);
        $order->log('Platební odkaz VIP na '.Order::czk($amountHalere), $by);
        Mail::to($order->email)->send(new PaymentLink($order->fresh()));

        return $session['url'];
    }

    /** Úloha pro worker na Mac Mini: daňový doklad v MyÚčtu (spec. „Fakturace a DPH“). */
    public function queueInvoice(Order $order): void
    {
        $invoice = Invoice::create(['order_id' => $order->id, 'kind' => 'invoice', 'status' => 'queued']);
        $company = $order->company;
        WorkerJob::create([
            'type' => 'invoice',
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
            'payload' => [
                'order_number' => $order->number,
                'paid_at' => ($order->paid_at ?? now())->timezone('Europe/Prague')->toDateString(),
                'stripe_payment' => $order->stripe_payment_intent,
                'vat_rate' => config('gratulomat.vat_rate'),
                'customer' => ['email' => $order->email, 'name' => $company['name'] ?? null, 'company' => $company ? [
                    'ico' => $company['ico'], 'dic' => $company['dic'] ?? null, 'name' => $company['name'],
                    'street' => $company['street'], 'city' => $company['city'], 'zip' => $company['zip'], 'country' => 'CZ',
                ] : null],
                'lines' => array_map(fn ($l) => ['name' => $l['name'], 'qty' => $l['qty'], 'unit_price_halere' => $l['unit_price']], $order->lines),
            ],
        ]);
    }
}
