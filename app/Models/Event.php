<?php

namespace App\Models;

use App\Domain\Guest\Themes;
use App\Domain\Orders\CzechNames;
use App\Domain\Orders\WizardConfig;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

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

    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_members')->withPivot('role')->withTimestamps();
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

    /** Hlavní organizátor (vlastník) podle e-mailu; předchozí vlastník přestane mít přístup. */
    public function setOwner(string $email): User
    {
        $user = User::firstOrCreate(['email' => mb_strtolower(trim($email))], ['name' => strstr($email, '@', true)]);
        $old = $this->organizers()->wherePivot('role', 'owner')->pluck('users.id')->reject(fn ($id) => $id === $user->id);
        if ($old->isNotEmpty()) {
            $this->organizers()->detach($old->all());
        }
        $this->organizers()->syncWithoutDetaching([$user->id => ['role' => 'owner']]);

        return $user;
    }

    /** Tvary jména pro texty (1. pád → 3., 4., 5. pád…), přepočet po změně jména. */
    public function refreshRecipientForms(): void
    {
        $type = array_search($this->recipient_type, WizardConfig::RECIPIENTS, true) ?: 'person';
        $this->recipient_forms = CzechNames::forms($this->recipient_name, $type) ?: null;
    }

    /** Uplynula viditelnost (archiv)? Platí do konce dne archive_until (Praha). */
    public function isArchived(): bool
    {
        return $this->archive_until !== null
            && now()->gt($this->archive_until->copy()->shiftTimezone('Europe/Prague')->endOfDay());
    }

    /** Po kolika dnech od konce archivu se mažou média (lhůta na prodloužení). */
    public const PURGE_GRACE_DAYS = 30;

    /** Závěrečný titulek videoprezentace: vlastní z administrace, jinak výchozí podle typu události. */
    public function finaleTitle(): string
    {
        return $this->setting('finale_title') ?: $this->defaultFinaleTitle();
    }

    public function defaultFinaleTitle(): string
    {
        $key = 'guest.finale.'.(trans()->has('guest.finale.'.$this->type, $this->locale) ? $this->type : 'default');

        return __($key, ['voc' => $this->form('voc'), 'name' => $this->recipient_name], $this->locale);
    }

    /** Grafická šablona stránky pro hosty (vesela | neutralni | exkluzivni | smutecni). */
    public function themeKey(): string
    {
        return Themes::normalize($this->theme);
    }

    public function themeMode(): string
    {
        $mode = $this->setting('mode') ?: (Themes::LEGACY[$this->theme][1] ?? 'light');

        return $mode === 'dark' ? 'dark' : 'light';
    }

    /**
     * Předání oslavenci (u smutečního rozloučení rodině): tajný odkaz jen ke čtení – videoprezentace,
     * všechny vzpomínky včetně soukromých přání a offline balíček. Uloženo v settings.handover.
     *
     * @return array{token:string, email:?string, message:?string, created_at:string, sent_at:?string, opened_at:?string}|null
     */
    public function handover(): ?array
    {
        $h = $this->setting('handover');

        return is_array($h) && ! empty($h['token']) ? $h : null;
    }

    public function handoverUrl(): ?string
    {
        $h = $this->handover();

        return $h ? url($this->slug.'/predani/'.$h['token']) : null;
    }

    /**
     * Smí příjemce předání vidět soukromá přání? Oslavenec ano (hosté psali „uvidí jen :name a organizátor“),
     * u smutečního rozloučení ne – tam hosté psali „vzkaz uvidí jen organizátor“.
     */
    public function handoverIncludesPrivate(): bool
    {
        return ! $this->isFuneral();
    }

    /** Odpovídá token aktuálnímu odkazu předání? (porovnání v konstantním čase) */
    public function handoverTokenValid(string $token): bool
    {
        $h = $this->handover();

        return $h && hash_equals($h['token'], $token);
    }

    /** Časová osa: vzpomínky podle let a dekád (výchozí). Vypnuto = jen vzpomínky a přání, hosté rok nezadávají. */
    public function timeline(): bool
    {
        return (bool) $this->setting('timeline', true);
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

    /**
     * Odkaz „vytvořit vlastní“ pro hosty (growth loop). U smutečních událostí žádná propagace – vrací null.
     * V odkazu není slug ani jméno, jen umístění a typ události.
     */
    public function loopUrl(string $placement): ?string
    {
        if ($this->isFuneral()) {
            return null;
        }

        return rtrim((string) config('gratulomat.marketing_url'), '/').'/?'.http_build_query([
            'utm_source' => 'event', 'utm_medium' => 'guest_loop', 'utm_content' => $placement, 'utm_campaign' => $this->type ?: 'event',
        ]);
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

        return ($from !== null && now()->gte($from)) || $this->autoShared();
    }

    /** Organizátor povolil automatické nasdílení prezentace hostům po uzávěrce sběru. */
    public function autoShared(): bool
    {
        return (bool) $this->setting('auto_share') && ($this->collect_until ?? $this->event_date)?->isPast();
    }

    /** Příspěvky ostatních: zapečetěné do data události; u smutečního rozloučení vidět hned. */
    public function wallOpen(): bool
    {
        return $this->isFuneral() || $this->setting('wall_before_event', false) || now()->gte($this->wallOpensAt()) || $this->showPublic();
    }

    /** Kdy se hostům odpečetí příspěvky ostatních: čas z administrace, jinak uzávěrka sběru, jinak datum události. */
    public function wallOpensAt(): Carbon
    {
        $set = $this->setting('wall_open_at');

        if ($set) {
            return Carbon::parse($set);
        }

        // uzávěrka sběru; pokud by sběr běžel i po oslavě, odpečetí se nejpozději v den události
        return $this->collect_until && $this->collect_until->lt($this->event_date) ? $this->collect_until : $this->event_date;
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
