<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Branding e-mailů (back-office → E-maily): jméno odesílatele, barva, logo, patička.
 * Bez nastavení platí výchozí vzhled Gratulomatu.
 */
class MailBrand
{
    public const DEFAULT_COLOR = '#FF5A3C';

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            return Setting::get("mail_$key", $default);
        } catch (Throwable) {
            return $default; // např. před migrací
        }
    }

    public static function fromName(): string
    {
        return (string) (self::get('from_name') ?: config('mail.from.name') ?: 'Gratulomat');
    }

    public static function color(): string
    {
        $c = (string) self::get('color', '');

        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtoupper($c) : self::DEFAULT_COLOR;
    }

    /** Čitelná barva textu na tlačítku (světlá barva → tmavý text). */
    public static function buttonText(): string
    {
        [$r, $g, $b] = sscanf(self::color(), '#%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#1C1633' : '#FFFFFF';
    }

    public static function footer(): string
    {
        return (string) (self::get('footer') ?: "Gratulomat · [gratulomat.cz](https://gratulomat.cz)\nDotazy: ".config('gratulomat.notify_email'));
    }

    public static function logoWidth(): int
    {
        return max(60, min(320, (int) self::get('logo_width', 180)));
    }

    public static function logoPath(): ?string
    {
        $file = self::get('logo');

        return $file && is_file(self::dir().'/'.$file) ? self::dir().'/'.$file : null;
    }

    public static function logoUrl(): ?string
    {
        $file = self::get('logo');

        return self::logoPath() ? url('/znacka/'.$file) : null;
    }

    public static function storeLogo(UploadedFile $file): void
    {
        self::removeLogo();
        File::ensureDirectoryExists(self::dir());
        // název se mění s obsahem → e-mailové klienty nedrží starou verzi v cache
        $name = 'logo-'.substr(sha1_file($file->getRealPath()), 0, 10).'.'.strtolower($file->guessExtension() ?: 'png');
        $file->move(self::dir(), $name);
        Setting::put('mail_logo', $name);
    }

    public static function removeLogo(): void
    {
        if ($path = self::logoPath()) {
            @unlink($path);
        }
        Setting::put('mail_logo', null);
    }

    public static function dir(): string
    {
        return storage_path('app/branding');
    }
}
