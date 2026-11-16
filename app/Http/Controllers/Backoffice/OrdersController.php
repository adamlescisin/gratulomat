<?php

namespace App\Http\Controllers\Backoffice;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\StripeGateway;
use App\Http\Controllers\Controller;
use App\Mail\EventReady;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkerJob;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** Back-office: objednávky a platby – přehled, detail, vrácení peněz, VIP odkaz, doklady. */
class OrdersController extends Controller
{
    public const FILTERS = [
        '' => 'Vše', 'paid' => 'Zaplacené', 'pending' => 'Čeká na platbu', 'inquiry' => 'VIP poptávky',
        'refunded' => 'Vrácené', 'manual' => 'Ruční', 'closed' => 'Zrušené / vypršelé',
    ];

    public function index(Request $request)
    {
        $orders = $this->query($request)->latest()->paginate(50)->withQueryString();
        $live = Order::where('is_test', false)->whereNotNull('paid_at');
        $month = (clone $live)->where('paid_at', '>=', now()->startOfMonth());

        return view('backoffice.orders', [
            'orders' => $orders, 'mode' => Setting::siteMode(), 'filters' => self::FILTERS,
            'kpis' => [
                ['Tržby tento měsíc', Order::czk((int) ((clone $month)->sum('total_halere') - (clone $month)->sum('refunded_halere')))],
                ['Tržby celkem', Order::czk((int) ($live->sum('total_halere') - $live->sum('refunded_halere')))],
                ['Zaplacených objednávek', (clone $live)->count()],
                ['Čeká na platbu', Order::where('status', 'pending')->where('total_halere', '>', 0)->count()],
                ['Otevřené VIP poptávky', Order::where('status', 'inquiry')->count()],
                ['Doklady s chybou', WorkerJob::where('status', 'failed')->count()],
            ],
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $orders = $this->query($request)->oldest()->get();

        return response()->streamDownload(function () use ($orders) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Číslo', 'Vytvořeno', 'Zaplaceno', 'Stav', 'Test', 'E-mail', 'Firma', 'IČO', 'Událost', 'Tarif', 'Celkem Kč', 'DPH Kč', 'Sleva Kč', 'Slevový kód', 'Vráceno Kč', 'Doklad', 'Stripe platba'], ';', '"', '');
            foreach ($orders as $o) {
                fputcsv($out, [
                    $o->number, $o->created_at->timezone('Europe/Prague')->format('Y-m-d H:i'), $o->paid_at?->timezone('Europe/Prague')->format('Y-m-d H:i'),
                    $o->statusLabel(), $o->is_test ? 'ano' : '', $o->email, $o->company['name'] ?? '', $o->company['ico'] ?? '',
                    $o->slug, config("gratulomat.plans.{$o->plan}.name", $o->plan),
                    self::num($o->total_halere), self::num($o->vat_halere), self::num($o->discount_halere), $o->discount_code,
                    self::num($o->refunded_halere), $o->invoices->where('kind', 'invoice')->first()?->number, $o->stripe_payment_intent,
                ], ';', '"', '');
            }
            fclose($out);
        }, 'objednavky-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function show(Order $order)
    {
        return view('backoffice.order', [
            'order' => $order->load(['event', 'invoices']), 'mode' => Setting::siteMode(),
            'jobs' => WorkerJob::where('subject_type', Invoice::class)->whereIn('subject_id', $order->invoices->pluck('id'))->orderBy('id')->get(),
        ]);
    }

    public function refund(Request $request, Order $order, StripeGateway $stripe, OrderService $orders)
    {
        $max = $order->refundableHalere();
        abort_if($max <= 0, 422, 'Tuto objednávku nelze vrátit.');
        $v = $request->validate([
            'amount' => 'nullable|numeric|min:1|max:'.($max / 100),
            'reason' => 'required|string|max:200',
        ], ['amount.max' => 'Nejvýš lze vrátit '.Order::czk($max).'.', 'reason.required' => 'Napište důvod vrácení (objeví se na dobropisu).']);
        $amount = isset($v['amount']) ? (int) round($v['amount'] * 100) : $max;

        try {
            $total = $stripe->refund($order, $amount === $max && $order->refunded_halere === 0 ? null : $amount);
        } catch (Throwable $e) {
            Log::error('Vrácení se nepodařilo: '.$e->getMessage(), ['order' => $order->number]);

            return back()->withErrors(['amount' => 'Stripe vrácení odmítl: '.$e->getMessage()]);
        }
        $orders->recordRefund($order, max($total, $order->refunded_halere + $amount), $v['reason'], $request->user()->email);

        return back()->with('ok', 'Vráceno '.Order::czk($amount).'. Zákazník peníze uvidí na kartě do 5–10 dnů'.($order->is_test || ! $order->invoices()->where('kind', 'invoice')->exists() ? '.' : ', dobropis vystaví worker.'));
    }

    public function resend(Request $request, Order $order)
    {
        abort_unless($order->event, 422);
        $user = User::where('email', $order->email)->firstOrFail();
        Mail::to($order->email)->send(new EventReady($order, $order->event, $user->loginLink(60 * 24 * 7)));
        $order->log('Znovu odeslán e-mail s odkazem do administrace', $request->user()->email);

        return back()->with('ok', 'E-mail s odkazem do administrace odešel na '.$order->email.'.');
    }

    public function cancel(Request $request, Order $order)
    {
        abort_unless(in_array($order->status, ['pending', 'inquiry'], true), 422);
        $order->update(['status' => 'cancelled']);
        $order->log('Objednávka zrušena (adresa uvolněna)', $request->user()->email);

        return back()->with('ok', "Objednávka {$order->number} je zrušená, adresa /{$order->slug} je zase volná.");
    }

    public function vipLink(Request $request, Order $order, OrderService $orders, StripeGateway $stripe)
    {
        abort_unless(in_array($order->status, ['inquiry', 'pending', 'expired'], true) && $order->kind === 'vip', 422);
        $v = $request->validate(['price' => 'required|integer|min:100|max:200000'], ['price.*' => 'Cena v Kč (100–200 000).']);
        try {
            $orders->vipPaymentLink($order, $v['price'] * 100, $stripe, $request->user()->email);
        } catch (Throwable $e) {
            return back()->withErrors(['price' => 'Platební odkaz se nepodařilo vytvořit: '.$e->getMessage()]);
        }

        return back()->with('ok', 'Platební odkaz na '.number_format($v['price'], 0, ',', ' ').' Kč odešel na '.$order->email.'.');
    }

    public function vipManual(Request $request, Order $order, OrderService $orders)
    {
        abort_unless($order->status === 'inquiry', 422);
        $order->update(['kind' => 'manual', 'plan' => 'vip', 'total_halere' => 0, 'vat_halere' => 0, 'lines' => [], 'status' => 'pending']);
        $order->log('VIP založeno ručně bez platby', $request->user()->email);
        $orders->fulfill($order->fresh());

        return back()->with('ok', 'Událost je založená, organizátorovi odešel odkaz do administrace.');
    }

    public function retryJob(Request $request, Order $order, WorkerJob $job)
    {
        $job->update(['status' => 'queued', 'attempts' => 0, 'error' => null, 'locked_until' => null]);
        $order->log("Úloha workeru #{$job->id} zařazena znovu", $request->user()->email);

        return back()->with('ok', 'Úloha je znovu ve frontě – worker ji zpracuje při příštím běhu.');
    }

    public function invoicePdf(Order $order, Invoice $invoice)
    {
        abort_unless($invoice->order_id === $order->id && $invoice->pdf_path && Storage::disk('local')->exists($invoice->pdf_path), 404);

        return Storage::disk('local')->download($invoice->pdf_path, ($invoice->number ?: 'doklad').'.pdf');
    }

    public function note(Request $request, Order $order)
    {
        $order->update(['note' => $request->validate(['note' => 'nullable|string|max:2000'])['note']]);

        return back()->with('ok', 'Poznámka uložená.');
    }

    private function query(Request $request): Builder
    {
        $q = Order::query()->with(['event', 'invoices']);
        match ($request->query('stav')) {
            'paid' => $q->whereNotNull('paid_at')->whereColumn('refunded_halere', '<', 'total_halere'),
            'pending' => $q->where('status', 'pending'),
            'inquiry' => $q->where('status', 'inquiry'),
            'refunded' => $q->where('refunded_halere', '>', 0),
            'manual' => $q->where('kind', 'manual'),
            'closed' => $q->whereIn('status', ['cancelled', 'expired']),
            default => null,
        };
        match ($request->query('test')) {
            'ostre' => $q->where('is_test', false),
            'test' => $q->where('is_test', true),
            default => null,
        };
        if ($s = trim((string) $request->query('q'))) {
            $q->where(fn ($w) => $w->where('number', 'like', "%$s%")->orWhere('email', 'like', "%$s%")->orWhere('slug', 'like', "%$s%"));
        }

        return $q;
    }

    private static function num(int $halere): string
    {
        return number_format($halere / 100, 2, ',', '');
    }
}
