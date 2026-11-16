<?php

namespace App\Domain\Guest;

use App\Models\Memory;
use GdImage;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Uložení nahraných souborů ke vzpomínce (převzato z prototypu Adamových 50).
 * Fotky se znovu zakódují přes GD: odstraní EXIF/GPS, zmenší na 2560 px, náhled 640 px
 * a zároveň zneškodní cokoli, co se jen tváří jako obrázek.
 */
class MediaStore
{
    public const PHOTO_MAX_EDGE = 2560;

    public const THUMB_EDGE = 640;

    public const AUDIO_MIMES = [
        'audio/webm' => 'webm', 'audio/ogg' => 'ogg', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a',
        'audio/aac' => 'aac', 'audio/mpeg' => 'mp3', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav',
        'video/webm' => 'webm', // Chrome někdy hlásí nahrávku z mikrofonu jako video/webm
    ];

    public const VIDEO_MIMES = [
        'video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm', 'video/x-m4v' => 'm4v',
    ];

    /** @return array{kind:string,file:string,thumb:?string,mime:string,width:?int,height:?int,bytes:int} */
    public function store(Memory $memory, UploadedFile $file, string $declaredKind, int $sort, int $maxVideoMb, int $maxAudioMb = 25): array
    {
        if (! $file->isValid()) {
            throw new RuntimeException(__('guest.err.upload', ['code' => $file->getError()]));
        }
        $tmp = $file->getRealPath();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream';
        $dir = $memory->dir();
        $base = sprintf('%02d-%s', $sort, bin2hex(random_bytes(3)));
        $bytes = (int) filesize($tmp);

        if (str_starts_with($mime, 'image/')) {
            $src = @imagecreatefromstring((string) file_get_contents($tmp));
            if (! $src) {
                throw new RuntimeException(__('guest.err.image'));
            }
            $src = $this->orient($src, $tmp, $mime);
            $full = $this->resize($src, self::PHOTO_MAX_EDGE);
            $thumb = $this->resize($src, self::THUMB_EDGE);
            imagejpeg($full, "$dir/$base.jpg", 86);
            imagejpeg($thumb, "$dir/$base.thumb.jpg", 78);
            $out = ['kind' => 'photo', 'file' => "$base.jpg", 'thumb' => "$base.thumb.jpg", 'mime' => 'image/jpeg',
                'width' => imagesx($full), 'height' => imagesy($full), 'bytes' => (int) filesize("$dir/$base.jpg")];
            imagedestroy($src);
            imagedestroy($full);
            imagedestroy($thumb);

            return $out;
        }

        if ($declaredKind === 'audio' && isset(self::AUDIO_MIMES[$mime])) {
            if ($bytes > $maxAudioMb * 1048576) {
                throw new RuntimeException(__('guest.err.audio_long'));
            }
            $name = "$base.".self::AUDIO_MIMES[$mime];
            $file->move($dir, $name);

            return ['kind' => 'audio', 'file' => $name, 'thumb' => null, 'mime' => $mime === 'video/webm' ? 'audio/webm' : $mime,
                'width' => null, 'height' => null, 'bytes' => $bytes];
        }

        if (isset(self::VIDEO_MIMES[$mime])) {
            if ($maxVideoMb <= 0) {
                throw new RuntimeException(__('guest.err.video_plan'));
            }
            if ($bytes > $maxVideoMb * 1048576) {
                throw new RuntimeException(__('guest.err.video_big', ['mb' => $maxVideoMb]));
            }
            $name = "$base.".self::VIDEO_MIMES[$mime];
            $file->move($dir, $name);

            return ['kind' => 'video', 'file' => $name, 'thumb' => null, 'mime' => $mime,
                'width' => null, 'height' => null, 'bytes' => $bytes];
        }

        throw new RuntimeException(__('guest.err.type', ['mime' => $mime]));
    }

    private function orient(GdImage $img, string $path, string $mime): GdImage
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data')) {
            return $img;
        }
        $o = (int) (@exif_read_data($path)['Orientation'] ?? 1);

        return match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    private function resize(GdImage $src, int $edge): GdImage
    {
        [$w, $h] = [imagesx($src), imagesy($src)];
        $s = min(1, $edge / max($w, $h));
        [$nw, $nh] = [max(1, (int) round($w * $s)), max(1, (int) round($h * $s))];
        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // průhledné PNG → bílé pozadí
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $dst;
    }
}
