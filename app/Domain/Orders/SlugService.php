<?php

namespace App\Domain\Orders;

use App\Models\Event;
use App\Models\Order;
use Illuminate\Support\Str;

/** Kontrola adresy události a návrhy volných variant. */
class SlugService
{
    public const PATTERN = '/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/';

    public static function slugify(string $text): string
    {
        return trim(substr(preg_replace('/[^a-z0-9]+/', '-', Str::lower(Str::ascii($text))), 0, 30), '-');
    }

    /** @return 'ok'|'invalid'|'reserved'|'taken' */
    public static function status(string $slug, ?string $ignoreOrderId = null): string
    {
        if (! preg_match(self::PATTERN, $slug) || str_contains($slug, '--')) {
            return 'invalid';
        }
        if (in_array($slug, Event::RESERVED_SLUGS, true) || in_array($slug, (array) config('gratulomat.blocked_slugs'), true)) {
            return 'reserved';
        }
        if (Event::withTrashed()->where('slug', $slug)->exists()) {
            return 'taken';
        }
        $held = Order::where('slug', $slug)->where('status', 'pending')->where('expires_at', '>', now())
            ->when($ignoreOrderId, fn ($q) => $q->whereKeyNot($ignoreOrderId))->exists();

        return $held ? 'taken' : 'ok';
    }

    /** @return list<string> */
    public static function suggestions(string $slug, ?string $year = null, ?string $name = null): array
    {
        $base = preg_replace('/-?\d+$/', '', $slug) ?: $slug;
        $candidates = array_filter([
            $year ? "$slug-$year" : null,
            "$base-oslava",
            $name ? $slug.'-'.self::slugify($name) : null,
            "$slug-".random_int(2, 99),
        ]);
        $out = [];
        foreach ($candidates as $c) {
            $c = trim(substr($c, 0, 30), '-');
            if (self::status($c) === 'ok' && ! in_array($c, $out, true)) {
                $out[] = $c;
            }
        }

        return array_slice($out, 0, 3);
    }
}
