<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeshUploadIntent extends Model
{
    protected $fillable = [
        'intent_id',
        'object_id',
        'user_id',
        'filename',
        'size',
        'total_hash',
        'chunk_size',
        'chunk_count',
        'chunk_hashes',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'size' => 'integer',
        'chunk_size' => 'integer',
        'chunk_count' => 'integer',
        'chunk_hashes' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
