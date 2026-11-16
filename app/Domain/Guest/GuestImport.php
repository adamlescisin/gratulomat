<?php

namespace App\Domain\Guest;

use App\Models\Event;
use App\Models\Household;
use RuntimeException;
use ZipArchive;

/**
 * Import seznamu hostů (od Premiéry) z CSV nebo Excelu (.xlsx).
 * Sloupce poznává podle záhlaví (Jméno / E-mail / Vztah), bez záhlaví: 1. sloupec jméno,
 * sloupec s e-mailem podle tvaru, další text jako vztah. CSV i z českého Excelu (Windows-1250, středník).
 */
class GuestImport
{
    private const NAME = '/^(jm[eé]no|jm[eé]na|name|names|domácnost|domacnost|host|hosté|hoste|gratulant|label|vorname)/iu';

    private const EMAIL = '/^(e-?mail|mail)/iu';

    private const RELATION = '/^(vztah|relation|odkud|pozn[aá]mka|note|beziehung)/iu';

    private const SKIP = '/^(p[rř][ií]jmen[ií]|surname|last ?name|nachname)/iu';

    /** @return list<list<string>> */
    public static function rows(string $path, string $originalName): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $rows = $ext === 'xlsx' ? self::xlsx($path) : self::csv((string) file_get_contents($path));

        return array_values(array_filter(array_map(
            fn ($r) => array_map(fn ($c) => trim((string) $c), $r),
            $rows
        ), fn ($r) => implode('', $r) !== ''));
    }

    /** @return array{added:int, skipped:int, duplicates:int} */
    public static function import(Event $event, array $rows): array
    {
        [$map, $rows] = self::columns($rows);
        $limit = $event->limit('guests');
        $existing = $event->households()->pluck('label')->map(fn ($l) => mb_strtolower($l))->flip();
        $added = $skipped = $duplicates = 0;

        foreach ($rows as $r) {
            $label = mb_substr(trim(preg_replace('/\s+/u', ' ', $r[$map['name']] ?? '')), 0, 160);
            if ($label === '') {
                continue;
            }
            if (isset($existing[mb_strtolower($label)])) {
                $duplicates++;

                continue;
            }
            if ($limit !== null && $event->households()->count() >= $limit) {
                $skipped++;

                continue;
            }
            $email = $map['email'] !== null ? mb_strtolower($r[$map['email']] ?? '') : '';
            $relation = $map['relation'] !== null ? mb_substr($r[$map['relation']] ?? '', 0, 160) : '';
            $event->households()->create([
                'token' => Household::newToken(), 'label' => $label, 'names' => Household::splitNames($label) ?: [$label],
                'relation' => $relation ?: null, 'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null, 'source' => 'import',
            ]);
            $existing[mb_strtolower($label)] = true;
            $added++;
        }

        return compact('added', 'skipped', 'duplicates');
    }

    /** @return array{0: array{name:int, email:?int, relation:?int}, 1: list<list<string>>} */
    private static function columns(array $rows): array
    {
        $first = $rows[0] ?? [];
        $map = ['name' => null, 'email' => null, 'relation' => null];
        foreach ($first as $i => $cell) {
            foreach (['name' => self::NAME, 'email' => self::EMAIL, 'relation' => self::RELATION] as $k => $re) {
                if ($map[$k] === null && preg_match($re, $cell) && ! preg_match(self::SKIP, $cell)) {
                    $map[$k] = $i;
                }
            }
        }
        if ($map['name'] !== null || $map['email'] !== null) {
            $map['name'] ??= 0;

            return [$map, array_slice($rows, 1)];
        }
        // bez záhlaví: e-mail podle tvaru, první jiný textový sloupec za jménem = vztah
        $map['name'] = 0;
        foreach (array_slice($rows, 0, 20) as $r) {
            foreach ($r as $i => $cell) {
                if ($i > 0 && $map['email'] === null && filter_var($cell, FILTER_VALIDATE_EMAIL)) {
                    $map['email'] = $i;
                }
            }
        }
        foreach ($first as $i => $cell) {
            if ($i > 0 && $i !== $map['email']) {
                $map['relation'] = $i;
                break;
            }
        }

        return [$map, $rows];
    }

    /** @return list<list<string>> */
    private static function csv(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (iconv('Windows-1250', 'UTF-8//IGNORE', $raw) ?: $raw);
        }
        $lines = preg_split('/\R/u', $raw);
        $sample = implode("\n", array_slice($lines, 0, 5));
        $counts = ["\t" => substr_count($sample, "\t"), ';' => substr_count($sample, ';'), ',' => substr_count($sample, ',')];
        arsort($counts);
        $delimiter = array_sum($counts) ? array_key_first($counts) : ';';

        return array_map(fn ($l) => str_getcsv($l, $delimiter, '"', ''), array_filter($lines, fn ($l) => trim($l) !== ''));
    }

    /** Minimální čtení prvního listu .xlsx (bez knihovny). @return list<list<string>> */
    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Soubor Excelu se nepodařilo otevřít.');
        }
        $shared = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $sx = simplexml_load_string($xml);
            foreach ($sx->si as $si) {
                $shared[] = isset($si->t) ? (string) $si->t : implode('', array_map('strval', $si->xpath('.//*[local-name()="t"]')));
            }
        }
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheet === false) {
            throw new RuntimeException('V souboru chybí první list.');
        }
        $rows = [];
        foreach (simplexml_load_string($sheet)->sheetData->row as $row) {
            $out = [];
            foreach ($row->c as $c) {
                $col = self::colIndex(preg_replace('/\d+/', '', (string) $c['r']));
                $type = (string) $c['t'];
                $val = match ($type) {
                    's' => $shared[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    default => (string) $c->v,
                };
                $out[$col] = $val;
            }
            if ($out) {
                $max = max(array_keys($out));
                $rows[] = array_map(fn ($i) => $out[$i] ?? '', range(0, $max));
            }
        }

        return $rows;
    }

    private static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return max(0, $n - 1);
    }
}
