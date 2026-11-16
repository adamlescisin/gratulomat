<?php

namespace App\Domain\Show;

use App\Domain\Guest\MemoryPresenter;
use App\Models\Event;
use RuntimeException;
use ZipArchive;

/**
 * Offline balíček pro promítání bez internetu: index.html (Kino s vloženými daty, funguje i přes file://),
 * assets, media/, show.json, guests.json a private-wishes.json (jen pro organizátora, nepromítá se).
 */
class OfflinePackage
{
    public function build(Event $event, string $target): string
    {
        $zip = new ZipArchive;
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('ZIP nelze vytvořit.');
        }
        $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        $show = MemoryPresenter::show($event, 'media');

        $zip->addFromString('show.json', json_encode($show, $flags));
        $private = array_values(array_filter(MemoryPresenter::all($event, true),
            fn ($m) => ! $m['wish_public'] && ($m['wish_text'] || array_filter($m['media'], fn ($x) => $x['kind'] === 'audio'))));
        $zip->addFromString('private-wishes.json', json_encode($private, $flags));
        $zip->addFromString('guests.json', json_encode($event->households()->orderBy('label')
            ->get(['label', 'names', 'relation', 'last_seen_at']), $flags));
        app()->setLocale($event->locale);
        $zip->addFromString('index.html', view('event.kino', [
            'event' => $event, 'offline' => true, 'showUrl' => 'show.json', 'embeddedShow' => $show,
        ])->render());
        $zip->addFromString('JAK-PROMITAT.txt', "Rozbalte složku a otevřete index.html v Chrome nebo Safari.\n"
            ."Ovládání: mezerník pauza, šipky zpět/dál, F celá obrazovka, G přehled, M hudba.\n"
            ."Soubor private-wishes.json obsahuje soukromá přání – nepromítají se.\n");

        $assets = public_path('assets/event');
        $zip->addFile("$assets/css/app.css", 'assets/css/app.css');
        $zip->addFile("$assets/css/themes.css", 'assets/css/themes.css');
        $zip->addFile("$assets/js/kino.js", 'assets/js/kino.js');
        foreach (glob("$assets/fonts/*.woff2") as $f) {
            $zip->addFile($f, 'assets/fonts/'.basename($f));
        }
        if ($music = $event->show?->music) {
            if (is_file(public_path($music))) {
                $zip->addFile(public_path($music), $music);
            }
        }
        // všechna média včetně skrytých vzpomínek (ať se nic neztratí) + memory.json
        foreach (glob($event->mediaDir().'/*', GLOB_ONLYDIR) as $dir) {
            foreach (glob("$dir/*") as $f) {
                $zip->addFile($f, 'media/'.basename($dir).'/'.basename($f));
            }
        }
        $zip->close();

        return $target;
    }
}
