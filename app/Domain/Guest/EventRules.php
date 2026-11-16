<?php

namespace App\Domain\Guest;

use App\Models\Event;

/** Limity a funkce konkrétní události podle zmraženého tarifu. */
class EventRules
{
    public static function has(Event $event, string $feature): bool
    {
        if ($event->isFuneral() && in_array($feature, config('gratulomat.funeral.disabled_features'), true)) {
            return false;
        }
        if (isset($event->limits['features'])) {
            return in_array($feature, $event->limits['features'], true);
        }
        $codes = array_keys(config('gratulomat.plans'));
        $need = config("gratulomat.features.$feature");

        return $need !== null && array_search($event->plan, $codes, true) >= array_search($need, $codes, true);
    }

    public static function maxVideoMb(Event $event): int
    {
        return self::has($event, 'videos') ? (int) config('gratulomat.memory_limits.video_mb') : 0;
    }

    public static function photoLimit(Event $event): ?int
    {
        return $event->limit('photos');
    }

    public static function tags(Event $event): array
    {
        return $event->setting('tags')
            ?? config("gratulomat.default_tags.{$event->type}")
            ?? config('gratulomat.default_tags.default');
    }
}
