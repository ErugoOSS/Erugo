<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeshObject extends Model
{
    protected $fillable = [
        'object_id',
        'share_id',
        'file_id',
        'original_name',
        'size',
        'chunk_size',
        'chunk_count',
        'total_hash',
        'generation',
        'status',
    ];

    protected $casts = [
        'size' => 'integer',
        'chunk_size' => 'integer',
        'chunk_count' => 'integer',
        'generation' => 'integer',
    ];

    public function share()
    {
        return $this->belongsTo(Share::class);
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function chunks()
    {
        return $this->hasMany(MeshChunk::class);
    }

    public function replicationJobs()
    {
        return $this->hasMany(MeshReplicationJob::class);
    }
}
