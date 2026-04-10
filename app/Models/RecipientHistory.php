<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecipientHistory extends Model
{
    protected $fillable = [
        'user_id',
        'email',
        'name',
        'use_count',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];
}
