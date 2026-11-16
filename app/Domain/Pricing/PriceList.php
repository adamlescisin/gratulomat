<?php

namespace App\Domain\Pricing;

use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Ceník tarifů udržovaný provozovatelem v back-office (Ceník).
 *
 * Pro každý tarif: základní cena v Kč s DPH a volitelná plošná sleva v % platná
 * do data (včetně, pražský čas). Výchozí hodnoty jsou v config/gratulomat.php.
 * apply() promítne účinné ceny do config('gratulomat.plans.*.price'), takže je
 * používá PricingService, navýšení tarifu, landing page i konfigurátor.
 */
final class PriceList
{
    public const SETTING = 'plan_prices';

    public const HISTORY = 'plan_prices_history';

    /** Ceny tarifů pro aktuální okamžik: list_price, sale_percent, sale_until, price (účinná), sale_active. */
    public static function rows(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $stored = (array) Setting::get(self::SETTING, []);
        $rows = [];
        foreach (config('gratulomat.plans') as $code => $plan) {
            $s = (array) ($stored[$code] ?? []);
            $list = (int) ($s['price'] ?? $plan['list_price'] ?? $plan['price']);
            $percent = (int) ($s['sale_percent'] ?? 0);
            $until = $s['sale_until'] ?? null;
            $active = $percent > 0 && $list > 0 && $until && $now->copy()->timezone('Europe/Prague')->toDateString() <= $until;
            $rows[$code] = [
                'name' => $plan['name'],
                'list_price' => $list,
                'sale_percent' => $percent,
                'sale_until' => $until,
                'sale_active' => $active,
                'price' => $active ? self::discounted($list, $percent) : $list,
            ];
        }

        return $rows;
    }

    /** Cena po slevě, zaokrouhlená na celé koruny. */
    public static function discounted(int $list, int $percent): int
    {
        return (int) round($list * (100 - min(100, max(0, $percent))) / 100);
    }

    /** Přepíše ceny tarifů v konfiguraci aktuálního požadavku. Idempotentní. */
    public static function apply(?CarbonInterface $now = null): void
    {
        foreach (self::rows($now) as $code => $r) {
            config([
                "gratulomat.plans.$code.list_price" => $r['list_price'],
                "gratulomat.plans.$code.price" => $r['price'],
                "gratulomat.plans.$code.sale" => $r['sale_active'] ? ['percent' => $r['sale_percent'], 'until' => $r['sale_until']] : null,
            ]);
        }
    }

    /**
     * Uloží ceník a zapíše změnu do historie (posledních 50 záznamů).
     *
     * @param  array<string, array{price:int, sale_percent:?int, sale_until:?string}>  $data
     */
    public static function save(array $data, string $by): void
    {
        $before = self::rows();
        $clean = [];
        foreach (array_keys(config('gratulomat.plans')) as $code) {
            if (! isset($data[$code])) {
                continue;
            }
            $percent = (int) ($data[$code]['sale_percent'] ?? 0);
            $clean[$code] = [
                'price' => (int) $data[$code]['price'],
                'sale_percent' => $percent > 0 ? $percent : null,
                'sale_until' => $percent > 0 ? ($data[$code]['sale_until'] ?? null) : null,
            ];
        }
        Setting::put(self::SETTING, $clean);

        $changes = [];
        foreach ($clean as $code => $c) {
            $b = $before[$code];
            if ($b['list_price'] !== $c['price'] || (int) $b['sale_percent'] !== (int) $c['sale_percent'] || $b['sale_until'] !== $c['sale_until']) {
                $changes[] = $b['name'].': '.self::describe($b['list_price'], $b['sale_percent'], $b['sale_until']).' → '.self::describe($c['price'], $c['sale_percent'], $c['sale_until']);
            }
        }
        if ($changes) {
            $history = (array) Setting::get(self::HISTORY, []);
            array_unshift($history, ['at' => now()->toIso8601String(), 'by' => $by, 'changes' => $changes]);
            Setting::put(self::HISTORY, array_slice($history, 0, 50));
        }
        self::apply();
    }

    public static function describe(int $price, ?int $percent, ?string $until): string
    {
        $s = number_format($price, 0, ',', ' ').' Kč';

        return $percent ? $s." (−{$percent} % do ".Carbon::parse($until)->format('j. n. Y').')' : $s;
    }
}
