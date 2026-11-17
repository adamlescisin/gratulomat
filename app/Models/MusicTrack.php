<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Hudební podkres z knihovny provozovatele (MP3 v storage/app/music). */
class MusicTrack extends Model
{
    protected $guarded = [];

    protected $casts = ['tags' => 'array', 'active' => 'boolean'];

    /** Navržené štítky; provozovatel může přidávat vlastní. */
    public const SUGGESTED_TAGS = ['veselá', 'klidná', 'slavnostní', 'dojemná', 'smuteční', 'akustická', 'piano', 'orchestr', 'retro', 'tempo', 'firemní', 'svatební'];

    /** Štítky, které se organizátorovi doporučí podle vzhledu a typu události. */
    public static function recommendedTags(Event $event): array
    {
        return match (true) {
            $event->type === 'smutecni' || $event->themeKey() === 'smutecni' => ['smuteční', 'klidná', 'dojemná'],
            in_array($event->type, ['svatba', 'vyroci-svatby'], true) => ['svatební', 'dojemná', 'slavnostní'],
            $event->type === 'firemni-jubileum' => ['firemní', 'slavnostní', 'tempo'],
            $event->themeKey() === 'exkluzivni' => ['slavnostní', 'orchestr', 'piano'],
            $event->themeKey() === 'vesela' => ['veselá', 'tempo', 'akustická'],
            default => ['klidná', 'dojemná', 'veselá'],
        };
    }

    public function path(): string
    {
        return storage_path('app/music/'.$this->file);
    }

    public function url(): string
    {
        return route('music.file', ['file' => $this->file]);
    }

    public function durationLabel(): string
    {
        return $this->duration ? sprintf('%d:%02d', intdiv($this->duration, 60), $this->duration % 60) : '–';
    }
}
