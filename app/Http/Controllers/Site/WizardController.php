<?php

namespace App\Http\Controllers\Site;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\SlugService;
use App\Domain\Orders\StripeGateway;
use App\Domain\Orders\WizardConfig;
use App\Domain\Pricing\PricingService;
use App\Http\Controllers\Controller;
use App\Mail\NewOrderNotice;
use App\Mail\VipInquiryReceived;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Průvodce založením události (/zalozit) a navazující platba. */
class WizardController extends Controller
{
    public function page(Request $request)
    {
        return view('site.wizard', [
            'preset' => in_array($request->query('tarif'), ['party', 'premiera', 'vip'], true) ? $request->query('tarif') : null,
            'cancelled' => $request->boolean('zruseno'),
            'testMode' => $this->isTest($request),
        ]);
    }

    public function slugCheck(Request $request): JsonResponse
    {
        $slug = SlugService::slugify((string) $request->query('slug'));
        $status = SlugService::status($slug);

        return response()->json([
            'slug' => $slug,
            'status' => $status,
            'suggestions' => in_array($status, ['taken', 'reserved'], true)
                ? SlugService::suggestions($slug, $request->query('year'), $request->query('name')) : [],
        ]);
    }

    public function quote(Request $request, PricingService $pricing): JsonResponse
    {
        $cfg = WizardConfig::fromRequest((array) $request->input('config', []));
        $q = $pricing->quote($cfg->eventConfig());

        return response()->json([...$q->toArray(), 'plan_name' => config("gratulomat.plans.{$q->plan}.name")]);
    }

    public function submit(Request $request, OrderService $orders, StripeGateway $stripe)
    {
        $cfg = WizardConfig::fromRequest((array) json_decode((string) $request->input('config'), true));
        $v = $request->validate([
            'email' => 'required|email:rfc|max:191',
            'terms' => 'accepted',
            'company' => 'nullable|boolean',
            'company_name' => 'required_if:company,1|nullable|string|max:160',
            'company_ico' => ['required_if:company,1', 'nullable', 'regex:/^\d{8}$/'],
            'company_dic' => ['nullable', 'regex:/^(CZ)?\d{8,10}$/i'],
            'company_street' => 'required_if:company,1|nullable|string|max:160',
            'company_city' => 'required_if:company,1|nullable|string|max:120',
            'company_zip' => ['required_if:company,1', 'nullable', 'regex:/^\d{3}\s?\d{2}$/'],
        ], [
            'email.*' => 'Zkontrolujte prosím e-mail.',
            'terms.accepted' => 'Potřebujeme souhlas s obchodními podmínkami.',
            'company_ico.regex' => 'IČO má 8 číslic.',
            'company_zip.regex' => 'PSČ má 5 číslic.',
            '*.required_if' => 'Doplňte fakturační údaje firmy.',
        ]);
        if ($errors = $cfg->errors()) {
            return back()->withErrors($errors)->withInput();
        }
        $company = $request->boolean('company') ? [
            'name' => $v['company_name'], 'ico' => $v['company_ico'], 'dic' => isset($v['company_dic']) ? strtoupper($v['company_dic']) : null,
            'street' => $v['company_street'], 'city' => $v['company_city'], 'zip' => preg_replace('/\s/', '', $v['company_zip']),
        ] : null;

        try {
            $order = $orders->create($cfg, $v['email'], $company, $this->isTest($request));
        } catch (Throwable $e) {
            return back()->withErrors(['slug' => $e->getMessage()])->withInput();
        }

        // VIP: poptávka bez platby
        if ($order->status === 'inquiry') {
            $this->safeMail(fn () => Mail::to($order->email)->send(new VipInquiryReceived($order)));
            $this->safeMail(fn () => Mail::to(config('gratulomat.notify_email'))->send(new NewOrderNotice($order)));

            return redirect()->route('wizard.done', ['objednavka' => $order->id]);
        }
        // Ochutnávka: potvrzení e-mailem
        if ($order->total_halere === 0) {
            $orders->sendFreeConfirmation($order);

            return redirect()->route('wizard.done', ['objednavka' => $order->id]);
        }
        // Placené: Stripe Checkout
        try {
            $session = $stripe->createCheckout($order);
        } catch (Throwable $e) {
            Log::error('Stripe Checkout se nepodařilo vytvořit: '.$e->getMessage(), ['order' => $order->number]);
            $order->update(['status' => 'cancelled']);

            $message = 'Platbu se teď nepodařilo zahájit. Zkuste to prosím za chvíli, nebo nám napište.';
            if ($request->attributes->get('site_full_access') && Setting::siteMode() !== 'live') {
                // testerům a operátorovi před spuštěním ukážeme důvod (zprávy Stripe neobsahují klíče)
                $message .= ' ['.$e->getMessage().']';
            }

            return back()->withErrors(['payment' => $message])->withInput();
        }
        $order->update(['stripe_session_id' => $session['id']]);

        return redirect()->away($session['url']);
    }

    public function done(Request $request)
    {
        $order = Order::find($request->query('objednavka'));
        abort_unless($order, 404);

        return view('site.wizard-done', ['order' => $order]);
    }

    public function status(Order $order): JsonResponse
    {
        return response()->json(['status' => $order->status, 'event' => $order->event?->slug]);
    }

    public function confirm(Request $request, Order $order, OrderService $orders)
    {
        abort_unless($request->hasValidSignature(), 403, 'Odkaz už neplatí.');
        abort_unless($order->total_halere === 0 && in_array($order->status, ['pending', 'fulfilled'], true), 404);
        $orders->fulfill($order);

        return redirect()->route('wizard.done', ['objednavka' => $order->id]);
    }

    /** Testovací režim: před spuštěním vždy, po spuštění pro testery. */
    private function isTest(Request $request): bool
    {
        return Setting::siteMode() !== 'live' || $request->attributes->get('is_tester', false);
    }

    private function safeMail(callable $send): void
    {
        try {
            $send();
        } catch (Throwable $e) {
            Log::error('E-mail se nepodařilo odeslat: '.$e->getMessage());
        }
    }
}
