<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Upravitelné šablony e-mailů zákazníkům (back-office → E-maily → Šablony).
 *
 * Zápis šablony (Markdown):
 *   {nazev_udalosti}                          – zástupce proměnné
 *   [tlacitko administrace]Text[/tlacitko]    – tlačítko s odkazem
 *   [pokud zaplaceno]…[/pokud]                – blok jen když platí příznak
 *   [pokud ne zaplaceno]…[/pokud]             – blok jen když neplatí
 * Příznakem je i každá proměnná – platí, když není prázdná.
 * Uložené úpravy jsou v Setting „mail_tpl_{klíč}“ = {subject, body}; bez nich platí výchozí text.
 */
class MailTemplates
{
    /** Proměnné dostupné ve všech šablonách. */
    public const COMMON_VARS = ['web' => 'Adresa webu (gratulomat.cz)'];

    public static function all(): array
    {
        return [
            'login_link' => [
                'label' => 'Přihlašovací odkaz',
                'when' => 'Organizátor si vyžádá přihlášení do administrace.',
                'subject' => 'Přihlášení do Gratulomatu',
                'body' => <<<'MD'
# Přihlášení do Gratulomatu

Dobrý den[pokud jmeno], {jmeno}[/pokud],

pro přihlášení do administrace své události klikněte na tlačítko. Odkaz platí 30 minut a dá se použít jen jednou.

[tlacitko prihlaseni]Přihlásit se[/tlacitko]

Pokud jste o přihlášení nežádali, e-mail můžete ignorovat.

Gratulomat
MD,
                'vars' => ['jmeno' => 'Jméno organizátora (může být prázdné)'],
                'flags' => [],
                'buttons' => ['prihlaseni' => 'Jednorázový přihlašovací odkaz'],
                'sample' => ['vars' => ['jmeno' => 'Jana'], 'buttons' => ['prihlaseni' => '/prihlaseni']],
            ],
            'free_confirm' => [
                'label' => 'Potvrzení založení (Ochutnávka)',
                'when' => 'Po objednávce zdarma – organizátor potvrzuje založení události.',
                'subject' => 'Potvrďte založení události „{nazev_udalosti}“',
                'body' => <<<'MD'
# Ještě jeden klik

Potvrďte prosím založení události **{nazev_udalosti}** v tarifu Ochutnávka. Po potvrzení se stránka hned vytvoří a pošleme vám odkaz do administrace.

[tlacitko potvrzeni]Potvrdit a založit událost[/tlacitko]

Odkaz platí 24 hodin. Pokud jste událost nezakládali, e-mail ignorujte.

Gratulomat
MD,
                'vars' => ['nazev_udalosti' => 'Název události'],
                'flags' => [],
                'buttons' => ['potvrzeni' => 'Odkaz pro potvrzení založení'],
                'sample' => ['vars' => ['nazev_udalosti' => 'Janiných 60'], 'buttons' => ['potvrzeni' => '/']],
            ],
            'event_ready' => [
                'label' => 'Událost je připravená',
                'when' => 'Po zaplacení nebo potvrzení – událost je založená.',
                'subject' => 'Událost „{nazev_udalosti}“ je připravená',
                'body' => <<<'MD'
# {nazev_udalosti} je připravená 🎉

[pokud zaplaceno]Děkujeme za platbu **{cena}** (objednávka {cislo_objednavky}, tarif {tarif}).[pokud ne test] Daňový doklad vám pošleme v samostatném e-mailu.[/pokud][/pokud]
[pokud ne zaplaceno]Událost běží v tarifu Ochutnávka. Na vyšší tarif můžete přejít kdykoli – nic se neztratí.[/pokud]

**Co dál**
1. Otevřete administraci a přidejte gratulanty – každý dostane osobní odkaz nebo QR kód.
2. Pošlete jim pozvánku (text je předvyplněný, stačí zkopírovat do WhatsAppu nebo e-mailu).
3. V den události zpřístupníte videoprezentaci jedním tlačítkem.

[tlacitko administrace]Otevřít administraci[/tlacitko]

Odkaz platí 7 dní a jen jednou. Další přihlášení: {prihlaseni} – stačí zadat tento e-mail.

Stránka pro gratulanty: {stranka_hostu}

[pokud test]_Testovací objednávka – nic se neúčtovalo._[/pokud]

Gratulomat
MD,
                'vars' => [
                    'nazev_udalosti' => 'Název události', 'cena' => 'Zaplacená částka', 'cislo_objednavky' => 'Číslo objednávky',
                    'tarif' => 'Název tarifu', 'prihlaseni' => 'Adresa stránky pro přihlášení', 'stranka_hostu' => 'Adresa stránky pro hosty',
                ],
                'flags' => ['zaplaceno' => 'Objednávka byla placená', 'test' => 'Testovací objednávka'],
                'buttons' => ['administrace' => 'Přihlášení do administrace (platí 7 dní)'],
                'sample' => [
                    'vars' => ['nazev_udalosti' => 'Janiných 60', 'cena' => '990 Kč', 'cislo_objednavky' => 'G-2026-0042', 'tarif' => 'Párty',
                        'prihlaseni' => '/prihlaseni', 'stranka_hostu' => '/janinych-60'],
                    'flags' => ['zaplaceno' => true], 'buttons' => ['administrace' => '/prihlaseni'],
                ],
            ],
            'payment_link' => [
                'label' => 'Platební odkaz (VIP)',
                'when' => 'Operátor pošle k VIP poptávce nabídku s platebním odkazem.',
                'subject' => 'Odkaz k platbě – {nazev_udalosti} (Gratulomat VIP)',
                'body' => <<<'MD'
# Vaše VIP událost je připravená k objednání

Děkujeme za poptávku **{nazev_udalosti}**. Jak jsme se domluvili, cena tarifu VIP je **{cena}** včetně DPH (objednávka {cislo_objednavky}).

[tlacitko platba]Zaplatit {cena}[/tlacitko]

Odkaz platí do {platnost_do}. Hned po zaplacení událost založíme na adrese {adresa} a pošleme vám odkaz do administrace[pokud ne test] i daňový doklad[/pokud].

[pokud test]_Testovací objednávka – použijte testovací kartu 4242 4242 4242 4242._[/pokud]

Gratulomat
MD,
                'vars' => [
                    'nazev_udalosti' => 'Název události', 'cena' => 'Cena včetně DPH', 'cislo_objednavky' => 'Číslo objednávky',
                    'platnost_do' => 'Do kdy platí platební odkaz', 'adresa' => 'Adresa budoucí stránky (gratulomat.cz/…)',
                ],
                'flags' => ['test' => 'Testovací objednávka'],
                'buttons' => ['platba' => 'Platební brána'],
                'sample' => [
                    'vars' => ['nazev_udalosti' => 'Firemní večírek', 'cena' => '4 900 Kč', 'cislo_objednavky' => 'G-2026-0043',
                        'platnost_do' => '27. 11. 2026 12:00', 'adresa' => 'gratulomat.cz/firemni-vecirek'],
                    'buttons' => ['platba' => '/'],
                ],
            ],
            'vip_received' => [
                'label' => 'Přijatá VIP poptávka',
                'when' => 'Zákazník odešle poptávku tarifu VIP.',
                'subject' => 'Děkujeme za poptávku VIP – Gratulomat',
                'body' => <<<'MD'
# Děkujeme za poptávku

Poptávku VIP pro **{nazev_udalosti}** jsme přijali. Ozveme se do jednoho pracovního dne s nabídkou a platebním odkazem – adresu {adresa} vám do té doby podržíme.

Gratulomat
MD,
                'vars' => ['nazev_udalosti' => 'Název události', 'adresa' => 'Rezervovaná adresa stránky'],
                'flags' => [],
                'buttons' => [],
                'sample' => ['vars' => ['nazev_udalosti' => 'Firemní večírek', 'adresa' => '/firemni-vecirek']],
            ],
            'upgrade_done' => [
                'label' => 'Navýšení tarifu hotové',
                'when' => 'Po zaplacení vyššího tarifu nebo doplňků.',
                'subject' => 'Navýšení události „{nazev_udalosti}“ je hotové',
                'body' => <<<'MD'
# Hotovo, děkujeme!

Událost **{nazev_udalosti}** má teď tarif **{tarif}**.

- Hosté: až {limit_hostu}
- Archiv do: {archiv_do}
{polozky}

Zaplaceno {cena} (objednávka {cislo_objednavky}).[pokud ne test] Daňový doklad pošleme zvlášť.[/pokud]

[tlacitko administrace]Otevřít administraci[/tlacitko]

Gratulomat
MD,
                'vars' => [
                    'nazev_udalosti' => 'Název události', 'tarif' => 'Nový tarif', 'limit_hostu' => 'Nový limit hostů',
                    'archiv_do' => 'Datum konce archivu', 'polozky' => 'Seznam objednaných položek (odrážky)',
                    'cena' => 'Zaplacená částka', 'cislo_objednavky' => 'Číslo objednávky',
                ],
                'flags' => ['test' => 'Testovací objednávka'],
                'buttons' => ['administrace' => 'Administrace události'],
                'sample' => [
                    'vars' => ['nazev_udalosti' => 'Janiných 60', 'tarif' => 'Premiéra', 'limit_hostu' => '100', 'archiv_do' => '30. června 2028',
                        'polozky' => new HtmlString("- Tarif Premiéra\n- Archiv +1 rok"), 'cena' => '1 290 Kč', 'cislo_objednavky' => 'G-2026-0044'],
                    'buttons' => ['administrace' => '/'],
                ],
            ],
            'invoice' => [
                'label' => 'Daňový doklad',
                'when' => 'Worker vystaví doklad v MyÚčtu (PDF je v příloze).',
                'subject' => '{typ_dokladu} {cislo_dokladu} – Gratulomat',
                'body' => <<<'MD'
# {typ_dokladu} {cislo_dokladu}

V příloze posíláme {typ_dokladu_maly} k objednávce {cislo_objednavky}. Doklad je uhrazený kartou.

Gratulomat
MD,
                'vars' => [
                    'typ_dokladu' => '„Daňový doklad“ nebo „Opravný daňový doklad“', 'typ_dokladu_maly' => 'Totéž malými písmeny',
                    'cislo_dokladu' => 'Číslo dokladu', 'cislo_objednavky' => 'Číslo objednávky',
                ],
                'flags' => ['dobropis' => 'Jde o opravný doklad (dobropis)'],
                'buttons' => [],
                'sample' => ['vars' => ['typ_dokladu' => 'Daňový doklad', 'typ_dokladu_maly' => 'daňový doklad', 'cislo_dokladu' => '2026000042', 'cislo_objednavky' => 'G-2026-0042']],
            ],
            'archive_ending' => [
                'label' => 'Archiv brzy končí',
                'when' => 'Před koncem archivu události.',
                'subject' => 'Archiv události „{nazev_udalosti}“ brzy končí',
                'body' => <<<'MD'
# Archiv brzy končí

Stránka a videoprezentace události **{nazev_udalosti}** budou dostupné do **{archiv_do}**. Potom je hosté neuvidí a po dalších {dny_do_smazani} dnech fotky, videa a vzkazy natrvalo smažeme.

Než se to stane, můžete:
- archiv prodloužit v administraci (Tarif a doplňky → Archiv +1 rok),
- stáhnout si offline balíček videoprezentace na památku.

[tlacitko administrace]Otevřít administraci[/tlacitko]

Gratulomat
MD,
                'vars' => ['nazev_udalosti' => 'Název události', 'archiv_do' => 'Datum konce archivu', 'dny_do_smazani' => 'Počet dní mezi koncem archivu a smazáním'],
                'flags' => [],
                'buttons' => ['administrace' => 'Administrace události'],
                'sample' => ['vars' => ['nazev_udalosti' => 'Janiných 60', 'archiv_do' => '30. června 2027', 'dny_do_smazani' => '30'], 'buttons' => ['administrace' => '/']],
            ],
        ];
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function definition(string $key): array
    {
        return self::all()[$key];
    }

    /** Uložená úprava, nebo null (platí výchozí text). */
    public static function saved(string $key): ?array
    {
        try {
            $v = Setting::get("mail_tpl_$key");
        } catch (Throwable) {
            return null;
        }

        return is_array($v) && isset($v['subject'], $v['body']) ? $v : null;
    }

    /** @return array{subject:string, body:string} */
    public static function current(string $key): array
    {
        $d = self::definition($key);

        return self::saved($key) ?? ['subject' => $d['subject'], 'body' => $d['body']];
    }

    public static function save(string $key, string $subject, string $body): void
    {
        $d = self::definition($key);
        $same = trim($subject) === $d['subject'] && self::normalize($body) === self::normalize($d['body']);
        Setting::put("mail_tpl_$key", $same ? null : ['subject' => trim($subject), 'body' => self::normalize($body), 'updated_at' => now()->toIso8601String()]);
    }

    public static function reset(string $key): void
    {
        Setting::put("mail_tpl_$key", null);
    }

    /** Vzorová data pro náhled a zkušební e-mail (bez databáze). */
    public static function sample(string $key): array
    {
        $s = self::definition($key)['sample'] ?? [];
        $s['buttons'] = array_map(fn ($u) => url($u), $s['buttons'] ?? []);
        $s['vars'] = array_map(fn ($v) => is_string($v) && str_starts_with($v, '/') ? url($v) : $v, $s['vars'] ?? []);

        return $s + ['vars' => [], 'flags' => []];
    }

    /**
     * Vyrenderuje předmět a segmenty těla (text / tlačítko) pro šablonu mail.template.
     *
     * @param  array{vars?:array, flags?:array, buttons?:array}  $data
     * @param  array{subject:string, body:string}|null  $tpl  vlastní text (náhled neuloženého); jinak aktuální
     * @return array{subject:string, segments:list<array{type:string, text:string, url?:string}>}
     */
    public static function render(string $key, array $data, ?array $tpl = null): array
    {
        $tpl ??= self::current($key);
        $vars = ($data['vars'] ?? []) + ['web' => preg_replace('~^https?://~', '', url('/'))];
        $flags = ($data['flags'] ?? []);
        foreach ($vars as $k => $v) {
            $flags[$k] ??= trim((string) ($v instanceof Htmlable ? $v->toHtml() : $v)) !== '';
        }
        $buttons = $data['buttons'] ?? [];

        $subject = self::substitute(strip_tags(self::conditions($tpl['subject'], $flags)), $vars, false);
        $subject = trim(preg_replace('/\s+/u', ' ', $subject));

        $body = self::conditions(self::normalize($tpl['body']), $flags);
        $body = preg_replace("/\n{3,}/", "\n\n", $body);

        $segments = [];
        $parts = preg_split('/\[(?:tlacitko|tlačítko)\s+([a-z0-9_]+)\](.*?)\[\/(?:tlacitko|tlačítko)\]/su', $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0; $i < count($parts); $i += 3) {
            $text = trim(self::substitute($parts[$i], $vars, true), "\n");
            if (trim($text) !== '') {
                $segments[] = ['type' => 'text', 'text' => $text];
            }
            if (isset($parts[$i + 1]) && ! empty($buttons[$parts[$i + 1]])) {
                $label = trim(self::substitute(strip_tags($parts[$i + 2]), $vars, false));
                $segments[] = ['type' => 'button', 'url' => $buttons[$parts[$i + 1]], 'text' => $label !== '' ? $label : 'Otevřít'];
            }
        }

        return ['subject' => $subject, 'segments' => $segments];
    }

    /** Neznámé zástupce v textu – pro upozornění v editoru. */
    public static function unknownPlaceholders(string $key, string $text): array
    {
        $d = self::definition($key);
        $known = array_keys($d['vars'] + self::COMMON_VARS);
        preg_match_all('/\{([a-z0-9_]+)\}/', $text, $m);
        $unknown = array_diff(array_unique($m[1]), $known);
        preg_match_all('/\[(?:tlacitko|tlačítko)\s+([a-z0-9_]+)\]/u', $text, $b);
        foreach (array_diff(array_unique($b[1]), array_keys($d['buttons'])) as $x) {
            $unknown[] = "tlačítko $x";
        }
        preg_match_all('/\[pokud\s+(?:ne\s+)?([a-z0-9_]+)\]/u', $text, $c);
        foreach (array_diff(array_unique($c[1]), array_merge($known, array_keys($d['flags']))) as $x) {
            $unknown[] = "pokud $x";
        }

        return array_values($unknown);
    }

    private static function normalize(string $s): string
    {
        return trim(str_replace(["\r\n", "\r"], "\n", $s));
    }

    /** Vyhodnotí [pokud …]…[/pokud] zevnitř ven (podporuje vnoření). */
    private static function conditions(string $s, array $flags): string
    {
        $re = '/\[pokud\s+(ne\s+)?([a-z0-9_]+)\]((?:(?!\[pokud\s).)*?)\[\/pokud\]/su';
        for ($n = 0; $n < 20 && preg_match($re, $s); $n++) {
            $s = preg_replace_callback($re, fn ($m) => (! empty($flags[$m[2]])) !== ($m[1] !== '') ? $m[3] : '', $s);
        }

        return $s;
    }

    private static function substitute(string $s, array $vars, bool $escape): string
    {
        return preg_replace_callback('/\{([a-z0-9_]+)\}/', function ($m) use ($vars, $escape) {
            if (! array_key_exists($m[1], $vars)) {
                return $m[0];
            }
            $v = $vars[$m[1]];
            if ($v instanceof Htmlable) {
                return $escape ? $v->toHtml() : strip_tags($v->toHtml());
            }

            return $escape ? e((string) $v) : (string) $v;
        }, $s);
    }
}
