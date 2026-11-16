<?php

namespace App\Domain\Orders;

use App\Models\Order;
use RuntimeException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * Stripe Checkout (jednorázová platba, CZK, ceny s DPH). Testovací objednávky jdou přes testovací klíče.
 * V testech se nahrazuje (container binding).
 */
class StripeGateway
{
    public function client(bool $test): StripeClient
    {
        $key = config($test ? 'services.stripe.test_secret' : 'services.stripe.live_secret');
        if (! $key) {
            throw new RuntimeException('Platby nejsou nastavené (chybí klíč Stripe '.($test ? 'TEST' : 'OSTRÝ').').');
        }

        return new StripeClient($key);
    }

    /** @return array{id: string, url: string} */
    public function createCheckout(Order $order, int $expiresMinutes = 35): array
    {
        $client = $this->client($order->is_test);
        $items = array_values(array_filter($order->lines, fn ($l) => $l['total'] > 0));
        $discounts = [];
        if ($order->discount_halere > 0) {
            // Stripe nepovoluje záporné položky → sleva jako jednorázový kupon na tuto objednávku
            $coupon = $client->coupons->create([
                'amount_off' => $order->discount_halere, 'currency' => 'czk', 'duration' => 'once', 'max_redemptions' => 1,
                'name' => "Sleva {$order->discount_code} ({$order->discount_percent} %)",
            ]);
            $discounts = [['coupon' => $coupon->id]];
        }
        $params = [
            'mode' => 'payment',
            'currency' => 'czk',
            'customer_email' => $order->email,
            'client_reference_id' => $order->id,
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->number],
            'payment_intent_data' => ['metadata' => ['order_id' => $order->id, 'order_number' => $order->number], 'description' => 'Gratulomat '.$order->number],
            'line_items' => array_map(fn ($l) => [
                'quantity' => $l['qty'],
                'price_data' => ['currency' => 'czk', 'unit_amount' => $l['unit_price'], 'product_data' => ['name' => $l['name']]],
            ], $items),
            'locale' => 'cs',
            'success_url' => $order->kind === 'upgrade' ? route('organizer.admin', $order->slug).'?navyseni=ok' : route('wizard.done').'?objednavka='.$order->id,
            'cancel_url' => $order->kind === 'upgrade' ? route('organizer.admin', $order->slug).'?navyseni=zruseno' : route('wizard').'?zruseno=1',
            // Stripe vyžaduje aspoň 30 minut; rezerva kvůli rozdílu hodin
            'expires_at' => now()->addMinutes(max(35, min($expiresMinutes, 60 * 23)))->timestamp,
        ];
        if ($discounts) {
            $params['discounts'] = $discounts;
        }
        $session = $client->checkout->sessions->create($params);

        return ['id' => $session->id, 'url' => $session->url];
    }

    /** Ověří podpis webhooku testovacím i ostrým tajemstvím. */
    public function verify(string $payload, string $signature): Event
    {
        foreach (['test_webhook_secret', 'live_webhook_secret'] as $key) {
            $secret = config("services.stripe.$key");
            if (! $secret) {
                continue;
            }
            try {
                return Webhook::constructEvent($payload, $signature, $secret);
            } catch (SignatureVerificationException) {
                continue;
            }
        }
        throw new UnexpectedValueException('Neplatný podpis webhooku.');
    }

    /** Vrácení přes Stripe; vrací celkovou vrácenou částku platby (haléře). */
    public function refund(Order $order, ?int $amountHalere = null): int
    {
        $client = $this->client($order->is_test);
        $client->refunds->create(array_filter([
            'payment_intent' => $order->stripe_payment_intent,
            'amount' => $amountHalere,
        ]));
        $pi = $client->paymentIntents->retrieve($order->stripe_payment_intent, ['expand' => ['latest_charge']]);

        return (int) ($pi->latest_charge->amount_refunded ?? 0);
    }

    public static function isPaid(object $session): bool
    {
        return ($session->payment_status ?? null) === Session::PAYMENT_STATUS_PAID;
    }
}
