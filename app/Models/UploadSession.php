<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'upload_id',
        'user_id',
        'filename',
        'filesize',
        'filetype',
        'total_chunks',
        'chunks_received',
        'status',
        'file_id',
        'is_bundle',
        'bundle_file_ids'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_bundle' => 'boolean'
    ];

    /**
     * Get the bundle file IDs as an array
     */
    public function getBundleFileIdsArray(): array
    {
        if (!$this->bundle_file_ids) {
            return [];
        }
        return json_decode($this->bundle_file_ids, true) ?? [];
    }

    /**
     * Get the user that owns the upload session.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the file associated with the upload session.
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Get the chunks for this upload session.
     */
    public function chunks()
    {
        return $this->hasMany(ChunkUpload::class);
    }
}