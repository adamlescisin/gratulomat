<?php

namespace App\Http\Controllers\Api;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\StripeGateway;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use UnexpectedValueException;

/** POST /api/stripe/webhook – rozhoduje o založení události (návrat z Checkoutu jen zobrazuje stav). */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, StripeGateway $stripe, OrderService $orders)
    {
        try {
            $event = $stripe->verify($request->getContent(), (string) $request->header('Stripe-Signature'));
        } catch (UnexpectedValueException $e) {
            return response()->json(['error' => 'invalid signature'], 400);
        }
        // idempotence podle ID události Stripe
        if (! Cache::add('stripe-event:'.$event->id, true, now()->addDays(3))) {
            return response()->json(['ok' => true, 'duplicate' => true]);
        }

        try {
            $this->handle($event, $orders);
        } catch (\Throwable $e) {
            Cache::forget('stripe-event:'.$event->id); // Stripe pošle znovu
            throw $e;
        }

        return response()->json(['ok' => true]);
    }

    private function handle(Event $event, OrderService $orders): void
    {
        $obj = $event->data->object;
        $order = isset($obj->metadata->order_id) ? Order::find($obj->metadata->order_id)
            : (isset($obj->client_reference_id) ? Order::find($obj->client_reference_id) : null);

        switch ($event->type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                if ($order && StripeGateway::isPaid($obj)) {
                    $order->update(['status' => 'paid', 'paid_at' => now(), 'stripe_payment_intent' => $obj->payment_intent ?? null]);
                    $orders->fulfill($order, $obj->payment_intent ?? null);
                }
                break;
            case 'checkout.session.expired':
                if ($order && $order->status === 'pending') {
                    $order->update(['status' => 'expired']);
                }
                break;
            case 'charge.refunded':
            case 'charge.dispute.created':
                Log::warning("Stripe: {$event->type}", ['payment_intent' => $obj->payment_intent ?? null]);
                break;
        }
    }
}
