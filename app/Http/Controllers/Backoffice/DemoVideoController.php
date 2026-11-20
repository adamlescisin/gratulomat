<?php

namespace App\Http\Controllers\Backoffice;

use App\Http\Controllers\Controller;
use App\Support\DemoVideo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** Ukázkový Gratulomat (video) pro landing page. */
class DemoVideoController extends Controller
{
    public const MAX_KB = 307200; // 300 MB; reálně omezuje i upload_max_filesize na serveru

    public function index()
    {
        return view('backoffice.demo', ['demo' => DemoVideo::get(), 'raw' => DemoVideo::raw(), 'limit' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size')]);
    }

    public function save(Request $request)
    {
        $limit = ini_get('upload_max_filesize');
        $v = $request->validate([
            'video' => 'nullable|file|mimes:mp4,webm|max:'.self::MAX_KB,
            'poster' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
            'url' => 'nullable|string|max:500',
            'remove_poster' => 'nullable|boolean',
        ], [
            'video.mimes' => 'Video nahrajte jako MP4 (H.264) nebo WebM.',
            'video.max' => 'Video může mít nejvýš 300 MB.',
            'video.uploaded' => "Video se nepodařilo nahrát – server přijme soubor nejvýš $limit. Zmenšete ho, nebo použijte odkaz na YouTube/Vimeo.",
            'poster.*' => 'Náhledový obrázek nahrajte jako JPG, PNG nebo WebP do 4 MB.',
        ]);
        $url = $request->has('url') ? trim((string) ($v['url'] ?? '')) : null;
        if ($url !== null && $url !== '' && ! DemoVideo::isPlayableUrl($url)) {
            return back()->withInput()->withErrors(['url' => 'Odkaz musí vést na YouTube, Vimeo nebo přímo na soubor .mp4/.webm (https).']);
        }
        DemoVideo::store($request->file('video'), $request->file('poster'), $url, $request->boolean('remove_poster'));
        Log::info('Ukázkové video uloženo', ['by' => $request->user()->email, 'file' => (bool) $request->file('video'), 'url' => $url]);

        return redirect()->route('backoffice.demo')->with('ok', 'Ukázka je uložená.');
    }

    public function destroy()
    {
        DemoVideo::removeFile();

        return redirect()->route('backoffice.demo')->with('ok', 'Nahrané video je smazané.');
    }
}
