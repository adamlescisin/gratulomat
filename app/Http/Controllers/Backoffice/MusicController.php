<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Models\MusicTrack;
use App\Models\ShowSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/** Knihovna hudebních podkresů pro videoprezentace. */
class MusicController extends Controller
{
    public const MAX_KB = 20480; // 20 MB na skladbu

    public function index()
    {
        $tracks = MusicTrack::orderByDesc('active')->orderBy('title')->get();
        $usage = ShowSettings::whereNotNull('music_track_id')->selectRaw('music_track_id, count(*) n')->groupBy('music_track_id')->pluck('n', 'music_track_id');
        $tags = collect(MusicTrack::SUGGESTED_TAGS)->merge($tracks->pluck('tags')->flatten())->filter()->unique()->values();

        return view('backoffice.music', compact('tracks', 'usage', 'tags'));
    }

    public function store(Request $request)
    {
        $v = $request->validate([
            'file' => 'required|file|mimes:mp3,mpga|max:'.self::MAX_KB,
            'title' => 'required|string|max:140',
            'artist' => 'nullable|string|max:140',
            'license' => 'nullable|string|max:500',
            'tags' => 'nullable|string|max:300',
            'duration' => 'nullable|integer|min:1|max:7200',
        ], [
            'file.required' => 'Vyberte soubor MP3.',
            'file.mimes' => 'Soubor musí být MP3.',
            'file.max' => 'Soubor může mít nejvýš 20 MB.',
            'title.required' => 'Vyplňte název skladby.',
        ]);
        File::ensureDirectoryExists(storage_path('app/music'));
        $name = Str::lower(Str::random(32)).'.mp3';
        $upload = $request->file('file');
        $bytes = $upload->getSize();
        $upload->move(storage_path('app/music'), $name);

        MusicTrack::create([
            'title' => $v['title'], 'artist' => $v['artist'] ?? null, 'license' => $v['license'] ?? null,
            'tags' => self::tags($v['tags'] ?? ''), 'duration' => $v['duration'] ?? null,
            'file' => $name, 'bytes' => $bytes,
        ]);
        Log::info('Podkres nahrán', ['title' => $v['title'], 'by' => $request->user()->email]);

        return redirect()->route('backoffice.music')->with('ok', "Skladba „{$v['title']}“ je v knihovně.");
    }

    public function update(Request $request, MusicTrack $track)
    {
        $v = $request->validate([
            'title' => 'required|string|max:140',
            'artist' => 'nullable|string|max:140',
            'license' => 'nullable|string|max:500',
            'tags' => 'nullable|string|max:300',
            'active' => 'nullable|boolean',
        ]);
        $track->update([
            'title' => $v['title'], 'artist' => $v['artist'] ?? null, 'license' => $v['license'] ?? null,
            'tags' => self::tags($v['tags'] ?? ''), 'active' => $request->boolean('active'),
        ]);

        return redirect()->route('backoffice.music')->with('ok', "Skladba „{$track->title}“ je uložená.");
    }

    public function destroy(MusicTrack $track)
    {
        $used = ShowSettings::where('music_track_id', $track->id)->count();
        @unlink($track->path());
        $track->delete(); // události s touto skladbou zůstanou bez podkresu (cizí klíč nullOnDelete)

        return redirect()->route('backoffice.music')->with('ok', "Skladba „{$track->title}“ je smazaná".($used ? " – {$used} událostí teď hraje bez podkresu." : '.'));
    }

    /** „Veselá, piano ,  Piano“ → ['veselá', 'piano'] */
    public static function tags(string $raw): array
    {
        return collect(preg_split('/[,;]/', $raw))->map(fn ($t) => mb_strtolower(trim($t)))->filter()->unique()->take(12)->values()->all();
    }
}
