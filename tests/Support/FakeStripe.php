<?php

namespace Tests\Support;

use App\Domain\Orders\StripeGateway;
use App\Models\Order;
use Stripe\Event;

/** Náhrada Stripe pro testy. */
class FakeStripe extends StripeGateway
{
    public ?Order $lastOrder = null;

    public function createCheckout(Order $order, int $expiresMinutes = 35): array
    {
        $this->lastOrder = $order;

        return ['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/cs_test_1'];
    }

    public function verify(string $payload, string $signature): Event
    {
        if ($signature !== 'ok') {
            throw new \UnexpectedValueException('bad');
        }

        return Event::constructFrom(json_decode($payload, true));
    }

    public static function completed(Order $order): array
    {
        return ['id' => 'evt_'.$order->id, 'object' => 'event', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'object' => 'checkout.session', 'id' => 'cs_test_1', 'payment_status' => 'paid', 'payment_intent' => 'pi_1',
            'client_reference_id' => $order->id, 'metadata' => ['order_id' => $order->id],
        ]]];
    }
}
