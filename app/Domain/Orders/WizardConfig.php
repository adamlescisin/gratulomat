<?php

namespace App\Domain\Orders;

use App\Domain\Guest\Themes;
use App\Domain\Pricing\EventConfig;
use App\Models\DiscountCode;
use Illuminate\Support\Carbon;

/**
 * Konfigurace z průvodce (/zalozit): normalizace vstupu z prohlížeče, převod na výpočet ceny a na údaje události.
 * Prohlížeč počítá jen náhled; závazná je vždy tato třída + PricingService.
 */
class WizardConfig
{
    public const OCCASIONS = [
        'birthday' => ['narozeniny', 'Narozeniny'], 'wedding' => ['svatba', 'Svatba'], 'anniversary' => ['vyroci-svatby', 'Výročí svatby'],
        'retire' => ['duchod', 'Odchod do důchodu'], 'company' => ['firemni-jubileum', 'Firemní jubileum'], 'farewell' => ['rozlucka', 'Rozlučka'],
        'reunion' => ['sraz', 'Sraz spolužáků'], 'funeral' => ['smutecni', 'Smuteční rozloučení'],
    ];

    public const RECIPIENTS = ['person' => 'osoba', 'couple' => 'par', 'team' => 'tym', 'company' => 'firma'];

    /** šablony viz App\Domain\Guest\Themes (neutrální zdarma, u smutečního rozloučení i smuteční) */
    public const THEMES = Themes::ALL;

    public const COLLECT_DAYS = [14, 30, 60, 90, 180];

    public const ARCHIVE_MONTHS = [1, 3, 12];

    /** přepínač v průvodci => funkce v ceníku */
    public const FEATURE_MAP = [
        'voice' => 'wishes', 'nowm' => 'no_watermark', 'offline' => 'offline', 'video' => 'videos', 'game' => 'game',
        'reminders' => 'reminders', 'importList' => 'import', 'anonLink' => 'anonymous_link', 'langs' => 'translations',
        'vipSetup' => 'vip_services', 'vipEdit' => 'vip_services',
    ];

    public function __construct(public readonly array $data) {}

    public static function fromRequest(array $in): self
    {
        $occasion = array_key_exists($in['occasion'] ?? '', self::OCCASIONS) ? $in['occasion'] : 'birthday';
        $funeral = $occasion === 'funeral';
        $theme = Themes::normalize($in['theme'] ?? null);
        $bool = fn ($k) => filter_var($in[$k] ?? false, FILTER_VALIDATE_BOOLEAN);
        $collect = (int) ($in['collect'] ?? 14);
        $archive = (int) ($in['archive'] ?? 1);
        $year = (int) ($in['startYear'] ?? 0);

        $data = [
            'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 140),
            'slug' => SlugService::slugify((string) ($in['slug'] ?? '')),
            'occasion' => $occasion,
            'date' => self::date($in['date'] ?? null),
            'recipient' => $funeral ? 'person' : (array_key_exists($in['recipient'] ?? '', self::RECIPIENTS) ? $in['recipient'] : 'person'),
            'recName' => mb_substr(trim((string) ($in['recName'] ?? '')), 0, 120),
            'startYear' => ($year >= 1900 && $year <= (int) date('Y')) ? $year : null,
            'guests' => max(1, min(300, (int) ($in['guests'] ?? 5))),
            'theme' => $theme,
            'mode' => ($in['mode'] ?? 'light') === 'dark' ? 'dark' : 'light',
            'collect' => in_array($collect, self::COLLECT_DAYS, true) ? $collect : 14,
            'archive' => in_array($archive, self::ARCHIVE_MONTHS, true) ? $archive : 1,
            'book' => $bool('book'),
            'archExt' => $bool('archExt'),
            'discount' => mb_substr(DiscountCode::normalize($in['discount'] ?? ''), 0, 40),
        ];
        foreach (array_keys(self::FEATURE_MAP) as $k) {
            $data[$k] = $bool($k);
        }
        if ($funeral) {
            $data['game'] = false;
        }

        return new self($data);
    }

    private static function date(mixed $v): ?string
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    public function isFuneral(): bool
    {
        return $this->data['occasion'] === 'funeral';
    }

    /** @return list<string> funkce z ceníku */
    public function features(): array
    {
        $f = [];
        foreach (self::FEATURE_MAP as $k => $feature) {
            if ($this->data[$k]) {
                $f[] = $feature;
            }
        }
        if (! Themes::isFree($this->data['theme'], $this->isFuneral())) {
            $f[] = 'all_themes';
        }
        if ($this->data['mode'] === 'dark') {
            $f[] = 'dark_mode';
        }

        return array_values(array_unique($f));
    }

    public function eventConfig(): EventConfig
    {
        return new EventConfig(
            eventType: self::OCCASIONS[$this->data['occasion']][0],
            guests: $this->data['guests'],
            features: $this->features(),
            collectDays: $this->isFuneral() ? 14 : $this->data['collect'], // u smutečního se sběr počítá od data rozloučení
            extraArchiveYears: $this->data['archExt'] ? 1 : 0,
            photobooks: $this->data['book'] ? 1 : 0,
            archiveMonths: $this->data['archive'],
        );
    }

    /** @return array<string, string> chyby podle kroku (pro průvodce i server) */
    public function errors(): array
    {
        $e = [];
        $d = $this->data;
        if (mb_strlen($d['name']) < 2) {
            $e['name'] = 'Doplňte název události.';
        }
        $status = SlugService::status($d['slug']);
        if ($status !== 'ok') {
            $e['slug'] = ['invalid' => 'Neplatná adresa (3–30 znaků: a–z, 0–9, pomlčka).', 'reserved' => 'Tahle adresa je rezervovaná.', 'taken' => 'Tahle adresa už je obsazená.'][$status];
        }
        if (! $d['date']) {
            $e['date'] = 'Doplňte datum.';
        } elseif ($this->isFuneral() ? $d['date'] < now()->subDays(30)->toDateString() : $d['date'] < now()->toDateString()) {
            $e['date'] = $this->isFuneral() ? 'Datum rozloučení může být nejvýš 30 dní zpět.' : 'Datum události musí být v budoucnosti.';
        }
        if ($d['recName'] === '') {
            $e['recName'] = 'Doplňte jméno.';
        }

        return $e;
    }

    /** Údaje pro založení události z objednávky. */
    public function eventAttributes(string $plan, int $guestLimit, bool $isTest): array
    {
        $d = $this->data;
        $type = self::OCCASIONS[$d['occasion']][0];
        $date = Carbon::parse($d['date'].' 18:00', 'Europe/Prague');
        $collect = $this->isFuneral() ? $date->copy()->addDays(max($d['collect'], 30)) : $date->copy();
        $archiveUntil = ($this->isFuneral() ? $collect : $date)->copy()->addMonths($d['archive'])->addYears($d['archExt'] ? 1 : 0);
        $planCfg = config("gratulomat.plans.$plan");

        return [
            'slug' => $d['slug'],
            'type' => $type,
            'title' => $d['name'],
            'locale' => 'cs',
            'recipient_type' => $this->isFuneral() ? 'zesnuly' : self::RECIPIENTS[$d['recipient']],
            'recipient_name' => $d['recName'],
            'recipient_forms' => CzechNames::forms($d['recName'], $d['recipient']) ?: null,
            'start_year' => $d['startYear'],
            // aplikace ukládá v UTC (APP_TIMEZONE), místní čas převedeme
            'event_date' => $date->copy()->utc(),
            'collect_until' => $collect->copy()->utc(),
            'archive_until' => $archiveUntil->toDateString(),
            'status' => 'collecting',
            'plan' => $plan,
            'limits' => [
                'guests' => $guestLimit,
                'photos' => $planCfg['photos'],
                'collect_days' => $planCfg['collect_days'],
                'archive_months' => $d['archive'],
                'features' => $this->featuresForPlan($plan),
            ],
            'settings' => ['mode' => $d['mode'], 'wizard' => array_intersect_key($d, array_flip(['guests', 'theme', 'mode', 'collect', 'archive']))],
            'theme' => $d['theme'],
            'is_test' => $isTest,
        ];
    }

    /** Všechny funkce zaplaceného tarifu (organizátor je může zapnout i později). */
    private function featuresForPlan(string $plan): array
    {
        $codes = array_keys(config('gratulomat.plans'));
        $level = array_search($plan, $codes, true);
        $out = [];
        foreach (config('gratulomat.features') as $feature => $need) {
            if (array_search($need, $codes, true) <= $level) {
                $out[] = $feature;
            }
        }
        if ($this->isFuneral()) {
            $out = array_values(array_diff($out, config('gratulomat.funeral.disabled_features')));
        }

        return $out;
    }
}
