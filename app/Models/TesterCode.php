<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TesterCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['valid_until' => 'date'];

    public function isValid(): bool
    {
        return ($this->valid_until === null || $this->valid_until->endOfDay()->isFuture())
            && ($this->max_uses === null || $this->uses < $this->max_uses);
    }

    /** Platné kódy: z back-office (tabulka) + dočasně z .env. */
    public static function isValidCode(string $code): bool
    {
        $code = strtoupper(trim($code));
        if (in_array($code, array_map(fn ($c) => strtoupper(trim($c)), (array) config('gratulomat.tester_codes')), true)) {
            return true;
        }

        return (bool) static::where('code', $code)->first()?->isValid();
    }

    public static function generate(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        do {
            $code = 'BETA-';
            for ($i = 0; $i < 4; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('code', $code)->exists());

        return $code;
    }
}
