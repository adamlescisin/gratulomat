<?php

namespace App\Console\Commands;

use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Založení události bez průvodce (beta, VIP, testy). */
class CreateEvent extends Command
{
    protected $signature = 'gratulomat:event
        {slug : adresa události, např. adamovych-50}
        {--title= : název, např. „Adamových 50“}
        {--name= : jméno adresáta (1. pád)}
        {--forms= : tvary jména jako JSON, např. {"dat":"Adamovi","acc":"Adama","voc":"Adame","gen":"Adama","loc":"Adamovi","ins":"Adamem","poss":"Adamových"}}
        {--type=narozeniny}
        {--year= : rok narození / svatby / založení}
        {--date= : datum a čas události (Y-m-d H:i)}
        {--place=}
        {--collect-until= : uzávěrka sběru (výchozí = datum události)}
        {--plan=premiera}
        {--test : označit jako TEST (funguje v režimu Připravujeme)}';

    protected $description = 'Založí událost (bez platby)';

    public function handle(): int
    {
        $slug = strtolower($this->argument('slug'));
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,28}[a-z0-9]$/', $slug) || in_array($slug, Event::RESERVED_SLUGS, true)) {
            $this->error('Neplatná nebo rezervovaná adresa.');

            return self::FAILURE;
        }
        if (Event::withTrashed()->where('slug', $slug)->exists()) {
            $this->error('Adresa je obsazená.');

            return self::FAILURE;
        }
        $type = $this->option('type');
        if (! in_array($type, config('gratulomat.event_types'), true)) {
            $this->error('Neznámý typ události.');

            return self::FAILURE;
        }
        $date = Carbon::parse($this->option('date') ?: now()->addMonth()->format('Y-m-d 18:00'), 'Europe/Prague');
        $forms = $this->option('forms') ? json_decode($this->option('forms'), true, flags: JSON_THROW_ON_ERROR) : null;
        $collect = $this->option('collect-until') ? Carbon::parse($this->option('collect-until'), 'Europe/Prague')
            : ($type === config('gratulomat.funeral.type') ? $date->copy()->addDays(config('gratulomat.funeral.default_collect_days_after')) : $date);
        $plan = $this->option('plan');

        $event = Event::create([
            'slug' => $slug,
            'type' => $type,
            'title' => $this->option('title') ?: $slug,
            'recipient_name' => $this->option('name') ?: $slug,
            'recipient_forms' => $forms,
            'start_year' => $this->option('year') ? (int) $this->option('year') : null,
            'event_date' => $date->copy()->utc(),
            'event_place' => $this->option('place'),
            'collect_until' => $collect->copy()->utc(),
            'archive_until' => $date->copy()->addMonths(config("gratulomat.plans.$plan.archive_months", 12))->toDateString(),
            'status' => 'collecting',
            'plan' => $plan,
            'limits' => array_intersect_key(config("gratulomat.plans.$plan"), array_flip(['guests', 'photos', 'collect_days', 'archive_months'])),
            'theme' => $type === config('gratulomat.funeral.type') ? config('gratulomat.funeral.theme') : 'kino',
            'is_test' => (bool) $this->option('test'),
        ]);
        $event->show()->create([]);

        $this->info("Událost {$event->title} založena: ".url($event->slug));

        return self::SUCCESS;
    }
}
