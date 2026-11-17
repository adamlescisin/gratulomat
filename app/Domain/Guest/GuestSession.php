<?php

namespace App\Domain\Guest;

use App\Models\Event;
use App\Models\Household;

/** Přihlášení hosta je vázané na událost; jedna relace může nést více událostí. */
class GuestSession
{
    public static function household(Event $event): ?Household
    {
        $id = session("guest.{$event->id}");

        return $id ? $event->households()->find($id) : null;
    }

    public static function login(Event $event, string $token): ?Household
    {
        $household = $event->households()->where('token', strtolower(trim($token)))->first();
        if (! $household) {
            return null;
        }
        session()->regenerate();
        session(["guest.{$event->id}" => $household->id]);
        $household->forceFill(['last_seen_at' => now()])->save();

        return $household;
    }

    public static function logout(Event $event): void
    {
        session()->forget("guest.{$event->id}");
    }

    /** Relace otevřená odkazem předání (oslavenec / rodina). Zneplatněný odkaz přístup okamžitě ruší. */
    public static function isRecipient(Event $event): bool
    {
        $token = session("recipient.{$event->id}");

        return is_string($token) && $event->handoverTokenValid($token);
    }

    public static function loginRecipient(Event $event, string $token): void
    {
        session()->regenerate();
        session(["recipient.{$event->id}" => $token]);
    }

    /** @return list<int> všechny domácnosti přihlášené v této relaci */
    public static function householdIds(): array
    {
        return array_values(array_map('intval', (array) session('guest', [])));
    }
}
