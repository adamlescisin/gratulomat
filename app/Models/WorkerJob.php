<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Úloha pro pomocný worker na Mac Mini (doklady v MyÚčtu, videa). Worker si úlohy vyzvedává přes API. */
class WorkerJob extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'result' => 'array', 'locked_until' => 'datetime'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
