<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Event extends Model
{
    use HasUlids, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'recipient_forms' => 'array',
        'limits' => 'array',
        'settings' => 'array',
        'event_date' => 'datetime',
        'collect_until' => 'datetime',
        'archive_until' => 'date',
        'is_test' => 'boolean',
    ];

    public const RESERVED_SLUGS = [
        'admin', 'api', 'backoffice', 'zalozit', 'cenik', 'moje', 'prihlaseni', 'pristup', 'kino', 'www',
        'gratulomat', 'test', 'jazyk', 'up', 'assets', 'build', 'storage', 'obchodni-podminky', 'ochrana-soukromi',
        'reklamacni-rad', 'pravidla-pro-hosty', 'jak-to-funguje', 'otazky',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }

    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    public function show(): HasOne
    {
        return $this->hasOne(ShowSettings::class);
    }

    /** Tvar jména adresáta pro texty: nom, gen, dat, acc, voc, poss (Adamových). Bez tvaru → jméno. */
    public function form(string $case): string
    {
        return $this->recipient_forms[$case] ?? $this->recipient_name;
    }

    /** Zástupné symboly pro texty: :name, :gen, :dat, :acc, :voc, :loc, :ins, :poss, :title, :age. */
    public function textVars(): array
    {
        $vars = ['name' => $this->recipient_name, 'title' => $this->title, 'age' => (string) ($this->age() ?? '')];
        foreach (['gen', 'dat', 'acc', 'voc', 'loc', 'ins', 'poss'] as $case) {
            $vars[$case] = $this->form($case);
        }

        return $vars;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function limit(string $key): mixed
    {
        return $this->limits[$key] ?? config("gratulomat.plans.{$this->plan}.{$key}");
    }

    public function age(): ?int
    {
        return $this->start_year ? $this->event_date->year - $this->start_year : null;
    }

    public function isFuneral(): bool
    {
        return $this->type === config('gratulomat.funeral.type');
    }

    public function submissionsOpen(): bool
    {
        return in_array($this->status, ['collecting'], true)
            && ($this->collect_until === null || now()->lte($this->collect_until));
    }

    public function started(): bool
    {
        return now()->gte($this->event_date);
    }

    /** Videoprezentaci hostům zpřístupňuje vždy organizátor (ručně nebo naplánovaným datem). */
    public function showPublic(): bool
    {
        $from = $this->show?->public_from;

        return $from !== null && now()->gte($from);
    }

    /** Příspěvky ostatních: zapečetěné do data události; u smutečního rozloučení vidět hned. */
    public function wallOpen(): bool
    {
        return $this->isFuneral() || $this->setting('wall_before_event', false) || $this->started() || $this->showPublic();
    }

    /** V režimu Připravujeme jsou dostupné jen testovací a zaplacené (aktivní) události. */
    public function reachableBeforeLaunch(): bool
    {
        return $this->is_test || $this->status !== 'draft';
    }

    public function mediaDir(string $memoryId = ''): string
    {
        $dir = storage_path('app/media/'.$this->id.($memoryId !== '' ? '/'.$memoryId : ''));
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }
}
