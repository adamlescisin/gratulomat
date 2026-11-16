<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $guarded = ['id'];

    public function memory(): BelongsTo
    {
        return $this->belongsTo(Memory::class);
    }
}
