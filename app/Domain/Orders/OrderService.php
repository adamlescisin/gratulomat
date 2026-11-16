<?php

namespace App\Domain\Orders;

use App\Domain\Pricing\PricingService;
use App\Mail\EventReady;
use App\Mail\FreeEventConfirm;
use App\Mail\NewOrderNotice;
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

    public function create(WizardConfig $cfg, string $email, ?array $company, bool $isTest): Order
    {
        $quote = $this->pricing->quote($cfg->eventConfig());

        return DB::transaction(function () use ($cfg, $email, $company, $isTest, $quote) {
            if (SlugService::status($cfg->data['slug']) !== 'ok') {
                throw new RuntimeException('Tahle adresa už je obsazená. Zvolte prosím jinou.');
            }

            return Order::create([
                'number' => Order::nextNumber($isTest),
                'kind' => $quote->isInquiry ? 'vip' : 'initial',
                'status' => $quote->isInquiry ? 'inquiry' : 'pending',
                'email' => mb_strtolower($email),
                'config' => $cfg->data,
                'company' => $company,
                'slug' => $cfg->data['slug'],
                'plan' => $quote->plan,
                'lines' => $quote->lines,
                'total_halere' => $quote->totalHalere,
                'vat_halere' => $quote->vatHalere,
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
        $event = DB::transaction(function () use ($order, $paymentIntent) {
            $order = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($order->event_id) {
                return $order->event;
            }
            $cfg = new WizardConfig($order->config);
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
                'paid_at' => $order->total_halere > 0 ? now() : null,
                'stripe_payment_intent' => $paymentIntent ?? $order->stripe_payment_intent,
            ]);

            if ($order->total_halere > 0 && ! $order->is_test) {
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
