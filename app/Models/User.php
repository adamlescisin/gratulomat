<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'last_login_at',
    ];

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_members')->withPivot('role')->withTimestamps();
    }

    public function organizes(Event $event): bool
    {
        return $this->isOperator() || $this->events()->whereKey($event->id)->exists();
    }

    /** Provozovatel (back-office) – podle OPERATOR_EMAILS. */
    public function isOperator(): bool
    {
        return in_array(strtolower($this->email), config('gratulomat.operators'), true);
    }

    /** Přihlašovací odkaz (bez hesla), platný $minutes minut a jen jednou. */
    public function loginLink(int $minutes = 30): string
    {
        return URL::temporarySignedRoute('login.link', now()->addMinutes($minutes), [
            'user' => $this->id, 'nonce' => Str::random(16),
        ]);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }
}
