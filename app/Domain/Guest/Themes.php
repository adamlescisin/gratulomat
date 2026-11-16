<?php

namespace App\Domain\Guest;

use App\Models\Event;

/**
 * Grafické šablony stránky pro hosty. Každá má světlý a tmavý režim a vlastní párování písem
 * (CSS: public/assets/event/css/themes.css). Videoprezentace (Kino) zůstává filmová.
 */
class Themes
{
    /** klíč => [název, popis, písma, swatch light [pozadí, text, akcent], swatch dark] */
    public const ALL = [
        'vesela' => ['Veselá', 'Hravé barvy a výrazné písmo – narozeniny, svatby, rozlučky.', 'Bricolage Grotesque + Inter',
            ['#FFF8EE', '#1C1633', '#FF5A3C'], ['#1C1633', '#FFF8EE', '#FFC433']],
        'neutralni' => ['Neutrální', 'Čistý a klidný vzhled, který se hodí ke všemu.', 'Inter',
            ['#F6F5F2', '#1F2328', '#3E63DD'], ['#15171A', '#EDEEF0', '#8DA4EF']],
        'exkluzivni' => ['Exkluzivní', 'Filmový archiv se zlatem a patkovým písmem (jako Adamových 50).', 'Fraunces + Inter',
            ['#F5EFE3', '#2A2118', '#A8741F'], ['#14110E', '#F3EAD8', '#E3A94B']],
        'smutecni' => ['Smuteční', 'Tlumené barvy a tiché písmo pro rozloučení a vzpomínky.', 'Fraunces + Inter',
            ['#EEF0EE', '#2F3A40', '#5E6B73'], ['#1B2023', '#E6EAE8', '#A3B18A']],
    ];

    /** starší názvy (průvodce v1, Adamových 50) => [šablona, výchozí režim] */
    public const LEGACY = [
        'kino' => ['exkluzivni', 'dark'], 'zaklad' => ['neutralni', null], 'archiv' => ['exkluzivni', null],
        'zahrada' => ['vesela', null], 'elegant' => ['exkluzivni', null], 'ticho' => ['smutecni', null],
    ];

    public static function normalize(?string $key): string
    {
        return isset(self::ALL[$key]) ? $key : (self::LEGACY[$key][0] ?? 'neutralni');
    }

    /** Šablona zdarma (Ochutnávka): neutrální, u smutečního rozloučení i smuteční. */
    public static function isFree(string $theme, bool $funeral): bool
    {
        return $theme === 'neutralni' || ($funeral && $theme === 'smutecni');
    }

    public static function allowed(Event $event, string $theme, string $mode): bool
    {
        $okTheme = self::isFree($theme, $event->isFuneral()) || EventRules::has($event, 'all_themes');
        $okMode = $mode === 'light' || EventRules::has($event, 'dark_mode') || EventRules::has($event, 'all_themes');

        return isset(self::ALL[$theme]) && $okTheme && $okMode;
    }

    /** Pro administraci: seznam šablon s dostupností. */
    public static function forEvent(Event $event): array
    {
        $dark = EventRules::has($event, 'dark_mode') || EventRules::has($event, 'all_themes');

        return collect(self::ALL)->map(fn ($t, $k) => [
            'key' => $k, 'name' => $t[0], 'desc' => $t[1], 'fonts' => $t[2], 'light' => $t[3], 'dark' => $t[4],
            'available' => self::isFree($k, $event->isFuneral()) || EventRules::has($event, 'all_themes'), 'dark_available' => $dark,
        ])->values()->all();
    }
}
