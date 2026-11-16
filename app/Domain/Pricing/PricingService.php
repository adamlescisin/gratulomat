<?php

namespace App\Domain\Pricing;

use InvalidArgumentException;

/**
 * Výpočet tarifu a ceny podle specifikace (kapitola „Tarify, limity a cenotvorba“).
 * Výpočet provádí vždy server; prohlížeč výsledek jen zobrazuje.
 */
final class PricingService
{
    /** @var array<string, array> */
    private array $plans;

    /** @var array<string, string> */
    private array $features;

    /** @var array<string, array> */
    private array $addons;

    private int $vatRate;

    private array $funeral;

    public function __construct(?array $config = null)
    {
        $config ??= config('gratulomat');
        $this->plans = $config['plans'];
        $this->features = $config['features'];
        $this->addons = $config['addons'];
        $this->vatRate = (int) $config['vat_rate'];
        $this->funeral = $config['funeral'];
    }

    public function quote(EventConfig $cfg): Quote
    {
        $this->validate($cfg);

        $codes = array_keys($this->plans);
        $reasons = [];
        $level = 0;

        $raise = function (string $plan, string $reason) use (&$level, &$reasons, $codes): void {
            $idx = array_search($plan, $codes, true);
            if ($idx > $level) {
                $level = $idx;
            }
            $reasons[] = $reason;
        };

        // 1) Funkce
        foreach ($cfg->features as $feature) {
            $raise($this->features[$feature], __('pricing.feature_reason', [
                'feature' => __('pricing.feature.'.$feature),
                'plan' => $this->plans[$this->features[$feature]]['name'],
            ]));
        }

        // 2) Délka sběru
        foreach ($codes as $idx => $code) {
            if ($cfg->collectDays <= $this->plans[$code]['collect_days']) {
                if ($idx > 0) {
                    $raise($code, "Sběr {$cfg->collectDays} dní je od tarifu {$this->plans[$code]['name']}.");
                }
                break;
            }
        }

        // 2b) Délka archivu po události
        foreach ($codes as $idx => $code) {
            if ($cfg->archiveMonths <= $this->plans[$code]['archive_months']) {
                if ($idx > 0) {
                    $raise($code, "Archiv {$cfg->archiveMonths} měs. je od tarifu {$this->plans[$code]['name']}.");
                }
                break;
            }
        }

        // 3) Doplňky (Ochutnávka žádné nemá)
        if ($cfg->extraArchiveYears > 0) {
            $raise($this->addons['archive_year']['min_plan'], 'Prodloužení archivu je doplněk od tarifu Párty.');
        }
        if ($cfg->photobooks > 0) {
            $raise($this->addons['photobook']['min_plan'], 'Fotokniha je doplněk od tarifu Párty.');
        }

        // 4) Hosté: nad limit Ochutnávky vždy aspoň Párty
        if ($cfg->guests > $this->plans[$codes[0]]['guests']) {
            $raise($codes[1], "Více než {$this->plans[$codes[0]]['guests']} hostů je od tarifu {$this->plans[$codes[1]]['name']}.");
        }

        $vipIdx = array_search('vip', $codes, true);
        if ($level === $vipIdx) {
            return $this->inquiry($reasons);
        }

        // 5) Hosté nad limit tarifu: levnější z balíčků a vyššího tarifu
        [$level, $packs, $guestReason] = $this->resolveGuests($cfg->guests, $level, $codes, $vipIdx);
        if ($guestReason) {
            $reasons[] = $guestReason;
        }
        if ($level === $vipIdx) {
            return $this->inquiry($reasons);
        }

        $plan = $codes[$level];
        $lines = [];
        $lines[] = $this->line($plan, 'Tarif '.$this->plans[$plan]['name'], 1, $this->plans[$plan]['price']);
        if ($packs > 0) {
            $lines[] = $this->line('guest_pack', $this->addons['guest_pack']['name'], $packs, $this->addons['guest_pack']['price']);
        }
        if ($cfg->extraArchiveYears > 0) {
            $lines[] = $this->line('archive_year', $this->addons['archive_year']['name'], $cfg->extraArchiveYears, $this->addons['archive_year']['price']);
        }
        if ($cfg->photobooks > 0) {
            $lines[] = $this->line('photobook', $this->addons['photobook']['name'], $cfg->photobooks, $this->addons['photobook']['price']);
        }

        $total = array_sum(array_column($lines, 'total'));
        [$base, $vat] = $this->splitVat($lines);

        return new Quote(
            plan: $plan,
            lines: $lines,
            totalHalere: $total,
            baseHalere: $base,
            vatHalere: $vat,
            guestLimit: $this->plans[$plan]['guests'] + $packs * $this->addons['guest_pack']['size'],
            reasons: $reasons ?: ['Vše, co jste zvolili, obsahuje tarif '.$this->plans[$plan]['name'].'.'],
        );
    }

    /**
     * @return array{0:int,1:int,2:?string} [úroveň, počet balíčků, důvod]
     */
    private function resolveGuests(int $guests, int $level, array $codes, int $vipIdx): array
    {
        $pack = $this->addons['guest_pack'];
        $minPackLevel = array_search($pack['min_plan'], $codes, true);
        $maxPacks = $pack['max_per_event'];

        $limit = $this->plans[$codes[$level]]['guests'];
        if ($guests <= $limit) {
            return [$level, 0, null];
        }

        // Kandidáti: (tarif od aktuální úrovně) + nutné balíčky; VIP jen jako poslední možnost.
        $best = null;
        for ($idx = max($level, $minPackLevel); $idx < $vipIdx; $idx++) {
            $planLimit = $this->plans[$codes[$idx]]['guests'];
            $packs = (int) max(0, ceil(($guests - $planLimit) / $pack['size']));
            if ($maxPacks !== null && $packs > $maxPacks) {
                continue;
            }
            $price = $this->plans[$codes[$idx]]['price'] + $packs * $pack['price'];
            if ($best === null || $price < $best[2]) {
                $best = [$idx, $packs, $price];
            }
        }

        if ($best === null) {
            return [$vipIdx, 0, "{$guests} hostů řešíme individuálně v tarifu VIP."];
        }

        [$idx, $packs] = $best;
        $reason = $packs > 0
            ? "{$guests} hostů: tarif {$this->plans[$codes[$idx]]['name']} + {$packs}× balíček +{$pack['size']} hostů vychází nejlevněji."
            : "{$guests} hostů pokryje tarif {$this->plans[$codes[$idx]]['name']}.";

        return [$idx, $packs, $reason];
    }

    private function inquiry(array $reasons): Quote
    {
        $vip = $this->plans['vip'];

        return new Quote('vip', [], $vip['price'] * 100, 0, 0, $vip['guests'], $reasons, true);
    }

    /** Procentní sleva z celé objednávky jako záporná položka; výsledná cena na celé koruny. */
    public function applyDiscount(Quote $q, int $percent, string $code): Quote
    {
        if ($q->isInquiry || $q->totalHalere === 0 || $percent < 1) {
            return $q;
        }
        $percent = min(100, $percent);
        $after = (int) round($q->totalHalere * (100 - $percent) / 100 / 100) * 100;
        $discount = $q->totalHalere - $after;
        if ($discount <= 0) {
            return $q;
        }
        $lines = [...$q->lines, ['code' => 'discount', 'name' => "Sleva {$code} ({$percent} %)", 'qty' => 1, 'unit_price' => -$discount, 'total' => -$discount]];
        [$base, $vat] = $this->splitVat($lines);

        return new Quote($q->plan, $lines, $after, $base, $vat, $q->guestLimit, $q->reasons, false);
    }

    private function line(string $code, string $name, int $qty, int $unitCzk): array
    {
        return ['code' => $code, 'name' => $name, 'qty' => $qty, 'unit_price' => $unitCzk * 100, 'total' => $qty * $unitCzk * 100];
    }

    /**
     * DPH shora po položkách, zaokrouhlení na haléře (990 Kč = 818,18 + 171,82).
     *
     * @return array{0:int,1:int}
     */
    private function splitVat(array $lines): array
    {
        $base = 0;
        foreach ($lines as $line) {
            $base += (int) round($line['total'] * 100 / (100 + $this->vatRate));
        }
        $total = array_sum(array_column($lines, 'total'));

        return [$base, $total - $base];
    }

    private function validate(EventConfig $cfg): void
    {
        foreach ($cfg->features as $feature) {
            if (! isset($this->features[$feature])) {
                throw new InvalidArgumentException("Neznámá funkce: {$feature}");
            }
        }
        if ($cfg->eventType === $this->funeral['type']) {
            $blocked = array_intersect($cfg->features, $this->funeral['disabled_features']);
            if ($blocked) {
                throw new InvalidArgumentException('U smutečního rozloučení nejsou dostupné funkce: '.implode(', ', $blocked));
            }
        }
    }
}
