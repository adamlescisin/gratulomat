<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShowSettings extends Model
{
    protected $table = 'show_settings';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['public_from' => 'datetime', 'order' => 'array', 'game_enabled' => 'boolean'];

    public function track(): BelongsTo
    {
        return $this->belongsTo(MusicTrack::class, 'music_track_id');
    }

    /**
     * Zdroj podkresu pro Kino: skladba z knihovny (jen aktivní), jinak starší pevná cesta v public/.
     * Offline balíček má soubor v music/.
     */
    public function musicSrc(bool $offline = false): ?string
    {
        $t = $this->track;
        if ($t && $t->active && is_file($t->path())) {
            return $offline ? 'music/'.$t->file : $t->url();
        }
        if ($this->music && is_file(public_path($this->music))) {
            return $offline ? $this->music : asset($this->music);
        }

        return null;
    }
}
