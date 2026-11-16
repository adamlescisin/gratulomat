<?php

namespace App\Domain\Guest;

use App\Models\Event;
use App\Models\Memory;

/** Vzpomínka → pole pro API, Kino a export (stejný formát jako v prototypu). */
class MemoryPresenter
{
    public static function toArray(Memory $m, bool $withHousehold = true): array
    {
        $out = [
            'id' => $m->id,
            'authors' => $m->authors,
            'title' => $m->title,
            'body' => $m->body,
            'year' => $m->year,
            'tags' => $m->tags,
            'wish_text' => $m->wish_text,
            'wish_public' => $m->wish_public,
            'status' => $m->status,
            'created_at' => $m->created_at?->toIso8601String(),
            'updated_at' => $m->updated_at?->toIso8601String(),
            'media' => $m->media->map(fn ($x) => [
                'id' => $x->id, 'kind' => $x->kind, 'file' => $x->file, 'thumb' => $x->thumb, 'mime' => $x->mime,
                'width' => $x->width, 'height' => $x->height, 'bytes' => $x->bytes,
            ])->values()->all(),
        ];
        if ($withHousehold) {
            $out['household'] = ['id' => $m->household_id, 'label' => $m->household?->label, 'relation' => $m->household?->relation];
        }

        return $out;
    }

    /** Soukromé přání (text i hlas) vidí jen autoři, adresát a organizátor. */
    public static function stripPrivateWish(array $m): array
    {
        if ($m['wish_public']) {
            return $m;
        }
        $m['wish_text'] = null;
        $m['media'] = array_values(array_filter($m['media'], fn ($x) => $x['kind'] !== 'audio'));

        return $m;
    }

    /** @return list<array> */
    public static function all(Event $event, bool $includeHidden = false): array
    {
        return $event->memories()
            ->with(['media', 'household'])
            ->when(! $includeHidden, fn ($q) => $q->where('status', 'visible'))
            ->orderByRaw('COALESCE(year, 9999)')->orderBy('created_at')
            ->get()->map(fn ($m) => self::toArray($m))->all();
    }

    /** Ke každé vzpomínce memory.json do její složky – složka je samopopisná i bez databáze. */
    public static function writeSidecar(Memory $m): void
    {
        $m->load(['media', 'household']);
        file_put_contents($m->dir().'/memory.json',
            json_encode(self::toArray($m), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** Dataset pro Kino i offline balíček. */
    public static function show(Event $event, string $mediaBase, bool $includePrivate = false): array
    {
        $memories = self::all($event);

        return [
            'schema' => 'gratulomat.show/1',
            'generated_at' => now()->toIso8601String(),
            'locale' => $event->locale,
            'title' => $event->title,
            'celebrant' => $event->recipient_name,
            'celebrant_dative' => $event->form('dat'),
            'celebrant_vocative' => $event->form('voc'),
            'birth_year' => $event->start_year ?? $event->event_date->year,
            'age' => $event->age() ?? 0,
            'party_date' => $event->event_date->toIso8601String(),
            'finale_title' => $event->setting('finale_title'),
            'music' => $event->show?->music,
            'game' => (bool) $event->show?->game_enabled && ! $event->isFuneral(),
            'media_base' => $mediaBase,
            'memories' => $includePrivate ? $memories : array_map([self::class, 'stripPrivateWish'], $memories),
        ];
    }
}
