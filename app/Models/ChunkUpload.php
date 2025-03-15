<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChunkUpload extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'upload_session_id',
        'chunk_index',
        'chunk_size',
        'chunk_path'
    ];

    /**
     * Get the upload session that owns the chunk.
     */
    public function uploadSession()
    {
        return $this->belongsTo(UploadSession::class);
    }
}