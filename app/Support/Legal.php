<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Str;

/**
 * Formální náležitosti (back-office → Právní náležitosti): identifikace provozovatele,
 * obchodní podmínky a zásady zpracování osobních údajů ve dvou variantách – testovací
 * (režim Připravujeme) a ostré (spuštění i údržba). Přepínač: automaticky podle režimu webu, nebo ručně.
 */
class Legal
{
    public const DOCS = ['terms' => 'Obchodní podmínky', 'privacy' => 'Zásady zpracování osobních údajů', 'guest_rules' => 'Pravidla pro hosty'];

    public const VARIANTS = ['test' => 'Testovací (Připravujeme)', 'live' => 'Ostré (spuštění a údržba)'];

    public const IDENTITY_FIELDS = [
        'name' => 'Obchodní firma / jméno', 'ico' => 'IČO', 'dic' => 'DIČ', 'address' => 'Sídlo / místo podnikání',
        'register' => 'Zápis v rejstříku (spisová značka, nebo živnostenský rejstřík)', 'email' => 'Kontaktní e-mail', 'phone' => 'Telefon',
        'vat_payer' => 'Plátce DPH',
    ];

    public static function identity(): array
    {
        return array_merge(array_fill_keys(array_keys(self::IDENTITY_FIELDS), ''), ['email' => config('gratulomat.notify_email'), 'vat_payer' => '1'],
            array_filter((array) Setting::get('legal_identity', []), fn ($v) => $v !== null && $v !== ''));
    }

    /** Jednořádková identifikace do patičky webu a e-mailů (prázdné, dokud není vyplněná). */
    public static function identityLine(): string
    {
        $i = self::identity();
        if ($i['name'] === '') {
            return '';
        }

        return implode(', ', array_filter([$i['name'], $i['ico'] ? 'IČO '.$i['ico'] : null, $i['address'] ?: null]));
    }

    /** auto | test | live */
    public static function mode(): string
    {
        $m = Setting::get('legal_variant', 'auto');

        return in_array($m, ['auto', 'test', 'live'], true) ? $m : 'auto';
    }

    /** Která varianta platí právě teď: Připravujeme → testovací, spuštění a údržba → ostré. */
    public static function variant(): string
    {
        $m = self::mode();

        return $m !== 'auto' ? $m : (Setting::siteMode() === 'coming_soon' ? 'test' : 'live');
    }

    public static function raw(string $doc, string $variant): string
    {
        $saved = Setting::get("legal_{$doc}_{$variant}");
        if (is_array($saved) && trim($saved['text'] ?? '') !== '') {
            return $saved['text'];
        }
        $file = resource_path("legal/{$doc}_{$variant}.md");

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    public static function meta(string $doc, string $variant): array
    {
        $saved = Setting::get("legal_{$doc}_{$variant}");

        return is_array($saved) ? array_diff_key($saved, ['text' => 1]) : [];
    }

    /** Text pro web: aktuální varianta; prázdná ostrá varianta → testovací. */
    public static function html(string $doc, ?string $variant = null): string
    {
        $variant ??= self::variant();
        $text = self::raw($doc, $variant);
        if (trim($text) === '' && $variant === 'live') {
            $text = self::raw($doc, 'test');
        }

        return Str::markdown(self::fill($text), ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    }

    public static function fill(string $text): string
    {
        $i = self::identity();
        $map = ['{provozovatel}' => $i['name'] ?: 'provozovatel Gratulomatu', '{ico}' => $i['ico'] ?: '—', '{dic}' => $i['dic'] ?: '—',
            '{sidlo}' => $i['address'] ?: '—', '{rejstrik}' => $i['register'] ?: '—', '{email}' => $i['email'], '{telefon}' => $i['phone'] ?: '—',
            '{web}' => 'gratulomat.cz'];

        return strtr($text, $map);
    }

    public static function save(string $doc, string $variant, string $text, ?string $effectiveFrom, string $by): bool
    {
        $old = Setting::get("legal_{$doc}_{$variant}");
        $text = str_replace("\r\n", "\n", $text);
        if (is_array($old) && ($old['text'] ?? '') === $text && ($old['effective_from'] ?? null) === $effectiveFrom) {
            return false;
        }
        $version = (int) ($old['version'] ?? 0) + 1;
        Setting::put("legal_{$doc}_{$variant}", ['text' => $text, 'version' => $version, 'effective_from' => $effectiveFrom, 'updated_at' => now()->toIso8601String(), 'by' => $by]);
        // archiv verzí kvůli doložitelnosti (posledních 50)
        $history = (array) Setting::get('legal_history', []);
        array_unshift($history, ['doc' => $doc, 'variant' => $variant, 'version' => $version, 'effective_from' => $effectiveFrom, 'at' => now()->toIso8601String(), 'by' => $by, 'text' => $text]);
        Setting::put('legal_history', array_slice($history, 0, 50));

        return true;
    }
}
