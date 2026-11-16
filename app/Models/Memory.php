<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Memory extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $casts = ['authors' => 'array', 'tags' => 'array', 'wish_public' => 'boolean'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class)->orderBy('sort')->orderBy('id');
    }

    public function dir(): string
    {
        return $this->event->mediaDir($this->id);
    }
}
