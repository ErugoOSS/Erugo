<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeshChunk extends Model
{
    protected $fillable = [
        'mesh_object_id',
        'chunk_index',
        'hash',
        'size',
    ];

    protected $casts = [
        'chunk_index' => 'integer',
        'size' => 'integer',
    ];

    public function object()
    {
        return $this->belongsTo(MeshObject::class);
    }

    public function placements()
    {
        return $this->hasMany(MeshPlacement::class, 'chunk_id');
    }
}
