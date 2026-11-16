<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Nastavení provozovatele v databázi (režim webu, kontrolní seznam…); přebíjí hodnoty z .env. */
class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['value' => 'json'];

    private static array $cache = [];

    public static function get(string $key, mixed $default = null): mixed
    {
        if (! array_key_exists($key, self::$cache)) {
            try {
                self::$cache[$key] = static::find($key)?->value;
            } catch (Throwable) {
                self::$cache[$key] = null; // tabulka ještě neexistuje (první nasazení)
            }
        }

        return self::$cache[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        self::$cache[$key] = $value;
    }

    public static function flushCache(): void
    {
        self::$cache = [];
    }

    public static function siteMode(): string
    {
        return self::get('site_mode', config('gratulomat.site_mode'));
    }
}
