<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShowSettings extends Model
{
    protected $table = 'show_settings';

    protected $primaryKey = 'event_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = ['public_from' => 'datetime', 'order' => 'array', 'game_enabled' => 'boolean'];
}
