<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Slevový kód (procentní sleva z celé objednávky), spravuje provozovatel v back-office. */
class DiscountCode extends Model
{
    protected $guarded = [];

    protected $casts = ['valid_until' => 'date', 'active' => 'boolean'];

    public static function normalize(?string $code): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    /** Platný a použitelný kód, jinak null. */
    public static function usable(?string $code): ?self
    {
        $code = self::normalize($code);
        if ($code === '') {
            return null;
        }
        $d = self::where('code', $code)->first();

        return $d && $d->isUsable() ? $d : null;
    }

    public function isUsable(): bool
    {
        return $this->active
            && ($this->valid_until === null || $this->valid_until->endOfDay()->isFuture())
            && ($this->max_uses === null || $this->used_count < $this->max_uses);
    }

    public function statusLabel(): string
    {
        return match (true) {
            ! $this->active => 'vypnutý',
            $this->valid_until !== null && $this->valid_until->endOfDay()->isPast() => 'vypršel',
            $this->max_uses !== null && $this->used_count >= $this->max_uses => 'vyčerpaný',
            default => 'aktivní',
        };
    }
}
