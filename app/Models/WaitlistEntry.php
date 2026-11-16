<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    protected $table = 'waitlist';

    protected $guarded = ['id'];

    protected $casts = ['consent_at' => 'datetime', 'invited_at' => 'datetime'];
}
