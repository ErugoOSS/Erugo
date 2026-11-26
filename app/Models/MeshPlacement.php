<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeshPlacement extends Model
{
    protected $fillable = [
        'chunk_id',
        'node_id',
        'state',
        'verified_hash',
        'acknowledged_at',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function chunk()
    {
        return $this->belongsTo(MeshChunk::class, 'chunk_id');
    }

    public function node()
    {
        return $this->belongsTo(MeshNode::class, 'node_id');
    }
}
