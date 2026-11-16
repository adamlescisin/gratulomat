<?php

namespace App\Domain\Orders;

use App\Models\Event;
use App\Models\Order;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Navýšení tarifu a doplňky k existující události (administrace organizátora).
 * Cena = rozdíl ceny tarifů + doplňky; vše na serveru, ceny s DPH, v haléřích.
 */
class UpgradeService
{
    public function __construct(private StripeGateway $stripe) {}

    /** @return array{plan:string, lines:array, total:int, vat:int, guests:int, archive_until:string, errors:array} */
    public function quote(Event $event, array $in): array
    {
        $plans = config('gratulomat.plans');
        $codes = array_keys($plans);
        $current = $event->plan;
        $target = $in['plan'] ?? $current;
        $packs = max(0, (int) ($in['guest_packs'] ?? 0));
        $years = max(0, min(5, (int) ($in['archive_years'] ?? 0)));
        $book = (bool) ($in['photobook'] ?? false);
        $errors = [];

        if (! isset($plans[$target]) || ($plans[$target]['inquiry'] ?? false)) {
            $errors[] = 'Tarif VIP domluvíme individuálně – napište nám na '.config('gratulomat.notify_email').'.';
            $target = $current;
        }
        if (array_search($target, $codes, true) < array_search($current, $codes, true)) {
            $errors[] = 'Tarif nelze snížit.';
            $target = $current;
        }
        $level = array_search($target, $codes, true);
        $minAddon = array_search('party', $codes, true);
        if (($packs || $years || $book) && $level < $minAddon) {
            $errors[] = 'Doplňky jsou k dispozici od tarifu Párty.';
        }
        if ($event->status === 'purged') {
            $errors[] = 'Archiv této události už vypršel a média byla smazána.';
        }

        $lines = [];
        $diff = ($plans[$target]['price'] - $plans[$current]['price']) * 100;
        if ($target !== $current) {
            $lines[] = ['code' => 'upgrade_'.$target, 'name' => "Navýšení tarifu {$plans[$current]['name']} → {$plans[$target]['name']}", 'qty' => 1, 'unit_price' => $diff, 'total' => $diff];
        }
        foreach (['guest_pack' => $packs, 'archive_year' => $years, 'photobook' => $book ? 1 : 0] as $code => $qty) {
            if ($qty > 0) {
                $a = config("gratulomat.addons.$code");
                $lines[] = ['code' => $code, 'name' => $a['name'], 'qty' => $qty, 'unit_price' => $a['price'] * 100, 'total' => $a['price'] * 100 * $qty];
            }
        }
        $total = (int) collect($lines)->sum('total');
        $vatRate = config('gratulomat.vat_rate');
        $vat = (int) collect($lines)->sum(fn ($l) => $l['total'] - (int) round($l['total'] * 100 / (100 + $vatRate)));

        // nové limity (náhled)
        $existingPacks = intdiv(max(0, ($event->limit('guests') ?? 0) - $plans[$current]['guests']), config('gratulomat.addons.guest_pack.size'));
        $guests = $plans[$target]['guests'] + ($existingPacks + $packs) * config('gratulomat.addons.guest_pack.size');

        return ['plan' => $target, 'lines' => $lines, 'total' => $total, 'vat' => $vat, 'guests' => max($guests, (int) $event->limit('guests')),
            'archive_until' => $this->newArchiveUntil($event, $target, $years)->toDateString(), 'errors' => $errors];
    }

    public function createOrder(Event $event, string $email, array $in, bool $isTest): Order
    {
        $q = $this->quote($event, $in);
        if ($q['errors']) {
            throw new InvalidArgumentException($q['errors'][0]);
        }
        if ($q['total'] <= 0) {
            throw new InvalidArgumentException('Vyberte vyšší tarif nebo doplněk.');
        }

        return Order::create([
            'number' => Order::nextNumber($isTest), 'kind' => 'upgrade', 'status' => 'pending', 'email' => $email,
            'config' => ['name' => $event->title, 'upgrade' => [
                'plan' => $q['plan'], 'guest_packs' => (int) ($in['guest_packs'] ?? 0), 'archive_years' => (int) ($in['archive_years'] ?? 0), 'photobook' => (bool) ($in['photobook'] ?? false),
            ]],
            'slug' => $event->slug, 'plan' => $q['plan'], 'lines' => $q['lines'], 'total_halere' => $q['total'], 'vat_halere' => $q['vat'],
            'is_test' => $isTest, 'event_id' => $event->id, 'expires_at' => now()->addMinutes(35),
        ]);
    }

    public static function isTest(Event $event): bool
    {
        return $event->is_test || Setting::siteMode() !== 'live';
    }

    /** Po zaplacení: nový tarif, funkce, limity a archiv. */
    public function apply(Order $order): void
    {
        $event = $order->event;
        $u = $order->config['upgrade'];
        $plans = config('gratulomat.plans');
        $q = $this->quote($event, $u);

        $limits = $event->limits ?? [];
        $limits['guests'] = $q['guests'];
        $limits['photos'] = $plans[$q['plan']]['photos'];
        $limits['collect_days'] = max((int) ($limits['collect_days'] ?? 0), $plans[$q['plan']]['collect_days']);
        $limits['features'] = array_values(array_unique([...($limits['features'] ?? []), ...WizardConfig::featuresFor($q['plan'], $event->isFuneral())]));
        $settings = $event->settings ?? [];
        unset($settings['archive_notice_sent']);

        $event->update(['plan' => $q['plan'], 'limits' => $limits, 'archive_until' => $q['archive_until'], 'settings' => $settings]);
        if (in_array('game', $limits['features'], true)) {
            $event->show()->firstOrCreate([]);
        }
    }

    private function newArchiveUntil(Event $event, string $plan, int $years): Carbon
    {
        $base = ($event->isFuneral() && $event->collect_until ? $event->collect_until : $event->event_date)->copy()
            ->timezone('Europe/Prague')->addMonths(config("gratulomat.plans.$plan.archive_months"))->startOfDay();
        $current = $event->archive_until?->copy() ?? $base;
        $until = $current->gt($base) ? $current : $base;
        if ($years > 0) {
            $from = $until->lt(now()) ? now()->startOfDay() : $until;
            $until = $from->copy()->addYears($years);
        }

        return $until;
    }
}
