<?php

namespace App\Console\Commands;

use App\Mail\ArchiveEnding;
use App\Models\Event;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Denně (plánovač): 7 dní před koncem archivu upozorní organizátora, po konci + lhůtě smaže média.
 * Událost i objednávky v databázi zůstávají (doklady), mažou se fotky, videa, hlas a texty vzpomínek.
 */
class ArchiveEvents extends Command
{
    protected $signature = 'gratulomat:archive {--dry-run : jen vypsat, nic neměnit}';

    protected $description = 'Upozornění na konec archivu a úklid médií po jeho uplynutí';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        // 1) upozornění 7 dní předem (jednou)
        $soon = Event::whereNotNull('archive_until')
            ->whereBetween('archive_until', [now()->toDateString(), now()->addDays(7)->toDateString()])->get()
            ->filter(fn (Event $e) => ! $e->setting('archive_notice_sent') && $e->status !== 'purged');
        foreach ($soon as $event) {
            $owners = $event->organizers()->pluck('email');
            $this->line("Upozornění: {$event->slug} → ".$owners->implode(', '));
            if (! $dry && $owners->isNotEmpty()) {
                Mail::to($owners->all())->send(new ArchiveEnding($event));
                $event->settings = [...($event->settings ?? []), 'archive_notice_sent' => now()->toIso8601String()];
                $event->save();
            }
        }

        // 2) úklid médií po konci archivu + lhůtě
        $purge = Event::whereNotNull('archive_until')->where('status', '!=', 'purged')
            ->where('archive_until', '<', now()->subDays(Event::PURGE_GRACE_DAYS)->toDateString())->get();
        foreach ($purge as $event) {
            $this->line("Úklid: {$event->slug} (archiv do {$event->archive_until->toDateString()})");
            if ($dry) {
                continue;
            }
            File::deleteDirectory($event->mediaDir());
            $count = $event->memories()->count();
            $event->memories()->delete();
            $event->update(['status' => 'purged']);
            Log::info('Archiv události vypršel, média smazána', ['event' => $event->slug, 'memories' => $count]);
        }

        $this->info(($dry ? '[zkouška] ' : '')."Upozornění: {$soon->count()}, úklid: {$purge->count()}");

        return self::SUCCESS;
    }
}
