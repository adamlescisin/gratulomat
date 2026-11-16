<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array', 'company' => 'array', 'lines' => 'array', 'is_test' => 'boolean',
        'paid_at' => 'datetime', 'expires_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public static function nextNumber(bool $test): string
    {
        $prefix = ($test ? 'T-' : 'G-').date('Y').'-';
        $last = static::where('number', 'like', $prefix.'%')->orderByDesc('number')->value('number');
        $n = $last ? (int) substr($last, strlen($prefix)) + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    public function totalCzk(): string
    {
        return number_format($this->total_halere / 100, 0, ',', ' ').' Kč';
    }
}
