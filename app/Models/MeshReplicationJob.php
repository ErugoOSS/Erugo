<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeshReplicationJob extends Model
{
    protected $fillable = [
        'mesh_object_id',
        'node_id',
        'state',
        'attempts',
        'available_at',
        'last_error',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'available_at' => 'datetime',
    ];

    public function object()
    {
        return $this->belongsTo(MeshObject::class);
    }

    public function node()
    {
        return $this->belongsTo(MeshNode::class, 'node_id');
    }
}
