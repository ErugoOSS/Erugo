<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeshNode extends Model
{
    protected $fillable = [
        'node_id',
        'endpoint',
        'capacity_bytes',
        'healthy',
        'last_seen_at',
    ];

    protected $casts = [
        'capacity_bytes' => 'integer',
        'healthy' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function placements()
    {
        return $this->hasMany(MeshPlacement::class, 'node_id');
    }
}
