<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeshTombstone extends Model
{
    protected $fillable = [
        'object_id',
        'generation',
        'state',
        'attempts',
        'available_at',
        'last_error',
    ];

    protected $casts = [
        'generation' => 'integer',
        'attempts' => 'integer',
        'available_at' => 'datetime',
    ];
}
