<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Order extends Model
{
    use HasUlids;

    protected $guarded = ['id'];

    protected $casts = [
        'config' => 'array', 'company' => 'array', 'lines' => 'array', 'is_test' => 'boolean',
        'paid_at' => 'datetime', 'expires_at' => 'datetime', 'refunded_at' => 'datetime', 'history' => 'array', 'todo' => 'array',
    ];

    public const STATUS_LABELS = [
        'pending' => 'čeká na platbu', 'paid' => 'zaplaceno', 'fulfilled' => 'založeno', 'inquiry' => 'VIP poptávka',
        'expired' => 'vypršelo', 'cancelled' => 'zrušeno',
    ];

    public function statusLabel(): string
    {
        if ($this->refunded_halere > 0) {
            return $this->refunded_halere >= $this->total_halere ? 'vráceno' : 'částečně vráceno';
        }
        if ($this->status === 'pending' && $this->total_halere === 0 && $this->kind !== 'manual') {
            return 'čeká na potvrzení e-mailu';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    /** Zápis do historie objednávky (back-office). */
    public function log(string $text, ?string $by = null): void
    {
        $this->history = [...($this->history ?? []), ['at' => now()->toIso8601String(), 'text' => $text, 'by' => $by]];
        $this->save();
    }

    public function openTodo(): array
    {
        return array_values(array_filter($this->todo ?? [], fn ($t) => empty($t['done_at'])));
    }

    public function refundableHalere(): int
    {
        return $this->stripe_payment_intent && $this->kind !== 'manual' ? max(0, $this->total_halere - $this->refunded_halere) : 0;
    }

    public function stripeUrl(): ?string
    {
        return $this->stripe_payment_intent
            ? 'https://dashboard.stripe.com/'.($this->is_test ? 'test/' : '').'payments/'.$this->stripe_payment_intent : null;
    }

    public static function czk(int $halere): string
    {
        return number_format($halere / 100, $halere % 100 ? 2 : 0, ',', ' ').' Kč';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function jobs(): HasManyThrough
    {
        return $this->hasManyThrough(WorkerJob::class, Invoice::class, 'order_id', 'subject_id')->where('subject_type', Invoice::class);
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
