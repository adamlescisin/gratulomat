<?php

namespace App\Http\Controllers\Api;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\StripeGateway;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
                if ($order && $order->status !== 'fulfilled' && StripeGateway::isPaid($obj)) {
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
                $order = Order::where('stripe_payment_intent', $obj->payment_intent ?? '-')->first();
                if ($order) {
                    $orders->recordRefund($order, (int) $obj->amount_refunded, 'vráceno ve Stripe', 'Stripe');
                } else {
                    Log::warning('Stripe: vrácení k neznámé platbě', ['payment_intent' => $obj->payment_intent ?? null]);
                }
                break;
            case 'charge.dispute.created':
                $order = Order::where('stripe_payment_intent', $obj->payment_intent ?? '-')->first();
                $order?->log('Zákazník rozporoval platbu (chargeback) – vyřešte ve Stripe', 'Stripe');
                Log::warning('Stripe: chargeback', ['payment_intent' => $obj->payment_intent ?? null, 'order' => $order?->number]);
                try {
                    Mail::raw('Zákazník rozporoval platbu '.($order?->number ?? $obj->payment_intent).'. Odpovězte ve Stripe Dashboardu (Disputes) do termínu.', fn ($m) => $m->to(config('gratulomat.notify_email'))->subject('Gratulomat: chargeback '.($order?->number ?? '')));
                } catch (\Throwable) {
                }
                break;
        }
    }
}
