<?php

namespace App\Console\Commands;

use App\Domain\Guest\MemoryPresenter;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

/**
 * Převod dat z prototypu Adamových 50 (adam50-private/data: party.sqlite + media/).
 * Tokeny pozvánek zůstávají, takže hosté můžou dál používat své odkazy
 * (staré adam50.lescisin.cz/g/<token> stačí přesměrovat na gratulomat.cz/<slug>/g/<token>).
 */
class ImportAdam50 extends Command
{
    protected $signature = 'gratulomat:import-adam50 {slug : cílová událost (už založená)} {data : cesta ke složce data prototypu}';

    protected $description = 'Importuje hosty, vzpomínky a média z prototypu Adamových 50';

    public function handle(): int
    {
        $event = Event::where('slug', $this->argument('slug'))->firstOrFail();
        $data = rtrim($this->argument('data'), '/');
        if (! is_file("$data/party.sqlite")) {
            $this->error("Nenalezeno: $data/party.sqlite");

            return self::FAILURE;
        }
        $src = new PDO("sqlite:$data/party.sqlite", null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $map = [];
        $counts = ['households' => 0, 'memories' => 0, 'media' => 0, 'skipped' => 0];

        DB::transaction(function () use ($src, $event, $data, &$map, &$counts) {
            foreach ($src->query('SELECT * FROM households ORDER BY id') as $h) {
                $existing = $event->households()->where('token', $h['token'])->first();
                $household = $existing ?? $event->households()->create([
                    'token' => $h['token'],
                    'label' => $h['label'],
                    'names' => json_decode($h['names_json'], true),
                    'relation' => $h['relation'],
                    'source' => 'import',
                    'last_seen_at' => $h['last_seen'],
                    'created_at' => $h['created_at'],
                ]);
                $map[$h['id']] = $household->id;
                $counts['households'] += $existing ? 0 : 1;
            }

            $cols = array_column($src->query('PRAGMA table_info(memories)')->fetchAll(), 'name');
            foreach ($src->query('SELECT * FROM memories ORDER BY created_at') as $m) {
                if ($event->memories()->whereKey($m['id'])->exists()) {
                    $counts['skipped']++;

                    continue;
                }
                $memory = $event->memories()->create([
                    'id' => $m['id'],
                    'household_id' => $map[$m['household_id']],
                    'authors' => json_decode($m['authors_json'], true),
                    'title' => $m['title'] ?: null,
                    'body' => $m['body'] ?: null,
                    'year' => $m['year'],
                    'tags' => json_decode($m['tags_json'], true),
                    'wish_public' => (bool) $m['is_wish'],
                    'wish_text' => in_array('wish_text', $cols, true) ? $m['wish_text'] : null,
                    'status' => $m['status'],
                    'created_at' => $m['created_at'],
                    'updated_at' => $m['updated_at'],
                ]);
                $memory->setRelation('event', $event);
                $st = $src->prepare('SELECT * FROM media WHERE memory_id = ? ORDER BY sort, id');
                $st->execute([$m['id']]);
                foreach ($st->fetchAll() as $x) {
                    foreach (array_filter([$x['file'], $x['thumb']]) as $f) {
                        if (is_file("$data/media/{$m['id']}/$f")) {
                            copy("$data/media/{$m['id']}/$f", $memory->dir()."/$f");
                        }
                    }
                    $memory->media()->create([
                        'kind' => $x['kind'], 'file' => $x['file'], 'thumb' => $x['thumb'], 'mime' => $x['mime'],
                        'width' => $x['width'], 'height' => $x['height'], 'duration' => $x['duration'],
                        'bytes' => $x['bytes'], 'sort' => $x['sort'],
                    ]);
                    $counts['media']++;
                }
                MemoryPresenter::writeSidecar($memory);
                $counts['memories']++;
            }
        });

        $this->info("Hosté: {$counts['households']}, vzpomínky: {$counts['memories']}, média: {$counts['media']}, přeskočeno: {$counts['skipped']}");

        return self::SUCCESS;
    }
}
