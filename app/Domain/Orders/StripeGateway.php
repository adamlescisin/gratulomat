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
    public function createCheckout(Order $order): array
    {
        $session = $this->client($order->is_test)->checkout->sessions->create([
            'mode' => 'payment',
            'currency' => 'czk',
            'customer_email' => $order->email,
            'client_reference_id' => $order->id,
            'metadata' => ['order_id' => $order->id, 'order_number' => $order->number],
            'payment_intent_data' => ['metadata' => ['order_id' => $order->id, 'order_number' => $order->number], 'description' => 'Gratulomat '.$order->number],
            'line_items' => array_map(fn ($l) => [
                'quantity' => $l['qty'],
                'price_data' => ['currency' => 'czk', 'unit_amount' => $l['unit_price'], 'product_data' => ['name' => $l['name']]],
            ], $order->lines),
            'locale' => 'cs',
            'success_url' => route('wizard.done').'?objednavka='.$order->id,
            'cancel_url' => route('wizard').'?zruseno=1',
            // Stripe vyžaduje aspoň 30 minut; rezerva kvůli rozdílu hodin
            'expires_at' => now()->addMinutes(35)->timestamp,
        ]);

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

    public function refund(Order $order, ?int $amountHalere = null): void
    {
        $this->client($order->is_test)->refunds->create(array_filter([
            'payment_intent' => $order->stripe_payment_intent,
            'amount' => $amountHalere,
        ]));
    }

    public static function isPaid(object $session): bool
    {
        return ($session->payment_status ?? null) === Session::PAYMENT_STATUS_PAID;
    }
}
