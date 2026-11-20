<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ukázkový Gratulomat na landing page (back-office → Ukázka).
 * Buď nahraný soubor (MP4/WebM v storage/app/demo, servíruje route demo.file), nebo odkaz:
 * YouTube/Vimeo se vloží přehrávačem, přímý odkaz na .mp4/.webm přehraje <video>.
 * Setting „demo_video“ = {file?, poster?, url?, updated_at}.
 */
class DemoVideo
{
    public const DIR = 'app/demo';

    public static function raw(): array
    {
        try {
            $v = Setting::get('demo_video');
        } catch (Throwable) {
            return [];
        }

        return is_array($v) ? $v : [];
    }

    /** @return array{type:string, src:string, poster:?string}|null */
    public static function get(): ?array
    {
        $r = self::raw();
        $poster = ! empty($r['poster']) && is_file(self::path($r['poster'])) ? route('demo.file', $r['poster']) : null;
        if (! empty($r['file']) && is_file(self::path($r['file']))) {
            return ['type' => 'file', 'src' => route('demo.file', $r['file']), 'poster' => $poster];
        }
        if (! empty($r['url'])) {
            $embed = self::embedUrl($r['url']);

            return $embed ? ['type' => 'embed', 'src' => $embed, 'poster' => null] : ['type' => 'url', 'src' => $r['url'], 'poster' => $poster];
        }

        return null;
    }

    public static function path(string $name): string
    {
        return storage_path(self::DIR.'/'.basename($name));
    }

    /** Odkaz pro vložení YouTube/Vimeo, jinak null. */
    public static function embedUrl(string $url): ?string
    {
        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?autoplay=1&rel=0&modestbranding=1';
        }
        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1].'?autoplay=1';
        }

        return null;
    }

    public static function isPlayableUrl(string $url): bool
    {
        return self::embedUrl($url) !== null || (bool) preg_match('~^https://[^\s]+\.(mp4|webm)(\?.*)?$~i', $url);
    }

    public static function store(?UploadedFile $video, ?UploadedFile $poster, ?string $url, bool $removePoster = false): void
    {
        $r = self::raw();
        if (! is_dir(storage_path(self::DIR))) {
            mkdir(storage_path(self::DIR), 0775, true);
        }
        if ($video) {
            self::delete($r['file'] ?? null);
            $r['file'] = self::move($video);
        }
        if ($removePoster) {
            self::delete($r['poster'] ?? null);
            unset($r['poster']);
        }
        if ($poster) {
            self::delete($r['poster'] ?? null);
            $r['poster'] = self::move($poster);
        }
        if ($url !== null) {
            $r['url'] = trim($url) ?: null;
        }
        $r['updated_at'] = now()->toIso8601String();
        Setting::put('demo_video', $r);
    }

    public static function removeFile(): void
    {
        $r = self::raw();
        self::delete($r['file'] ?? null);
        unset($r['file']);
        Setting::put('demo_video', $r ?: null);
    }

    private static function move(UploadedFile $f): string
    {
        $ext = strtolower($f->getClientOriginalExtension() ?: $f->guessExtension() ?: 'bin');
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        $name = 'demo-'.Str::lower(Str::random(24)).'.'.$ext;
        $f->move(storage_path(self::DIR), $name);

        return $name;
    }

    private static function delete(?string $name): void
    {
        if ($name && is_file(self::path($name))) {
            @unlink(self::path($name));
        }
    }
}
