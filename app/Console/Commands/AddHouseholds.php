<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Household;
use Illuminate\Console\Command;

/** Hromadné přidání pozvánek: jeden řádek = domácnost, volitelně „; vztah“. */
class AddHouseholds extends Command
{
    protected $signature = 'gratulomat:households {slug} {file? : textový soubor (jinak STDIN)} {--list : jen vypsat odkazy}';

    protected $description = 'Přidá pozvánky (domácnosti) k události a vypíše osobní odkazy';

    public function handle(): int
    {
        $event = Event::where('slug', $this->argument('slug'))->firstOrFail();

        if (! $this->option('list')) {
            $file = $this->argument('file');
            $text = $file ? file_get_contents($file) : stream_get_contents(STDIN);
            $limit = $event->limit('guests');
            $added = 0;
            foreach (preg_split('/\R/u', (string) $text) as $line) {
                [$label, $rel] = array_map('trim', explode(';', $line, 2) + [1 => '']);
                if ($label === '') {
                    continue;
                }
                if ($limit !== null && $event->households()->count() >= $limit) {
                    $this->warn("Limit tarifu ({$limit} hostů) je vyčerpaný; zbytek nepřidán.");
                    break;
                }
                $event->households()->create([
                    'token' => Household::newToken(),
                    'label' => $label,
                    'names' => Household::splitNames($label),
                    'relation' => $rel ?: null,
                ]);
                $added++;
            }
            $this->info("Přidáno: {$added}");
        }

        $this->table(['Domácnost', 'Odkaz'], $event->households()->orderBy('label')->get()
            ->map(fn ($h) => [$h->label, $h->link()])->all());

        return self::SUCCESS;
    }
}
