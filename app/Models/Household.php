<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;

class Household extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['names' => 'array', 'last_seen_at' => 'datetime', 'invite_queued_at' => 'datetime', 'invited_at' => 'datetime',
        'reminder_queued_at' => 'datetime', 'reminded_at' => 'datetime', 'email_opt_out' => 'boolean'];

    /** Lze poslat e-mail? */
    public function mailable(): bool
    {
        return $this->email && ! $this->email_opt_out;
    }

    /** Odkaz pro odhlášení e-mailů (podepsaný, bez expirace). */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('event.unsubscribe', ['event' => $this->event->slug, 'household' => $this->id]);
    }

    public function inviteUrl(): string
    {
        return route('event.invite', [$this->event->slug, $this->token]);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /** Token 10 znaků bez zaměnitelných znaků (≈ 49 bitů). */
    public static function newToken(int $length = 10): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        do {
            $token = '';
            for ($i = 0; $i < $length; $i++) {
                $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (static::where('token', $token)->exists());

        return $token;
    }

    /** „Petra a Honza“, „Petra & Honza“, „Petra, Honza“ → jména. */
    public static function splitNames(string $label): array
    {
        $parts = preg_split('/\s*(?:,|&|\+|\sa\s|\sand\s|\sund\s)\s*/u', trim($label)) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn ($x) => $x !== ''));
    }

    public function link(): string
    {
        return url($this->event->slug.'/g/'.$this->token);
    }
}
