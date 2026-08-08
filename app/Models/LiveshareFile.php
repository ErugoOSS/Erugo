<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveshareFile extends Model
{
    protected $fillable = [
        'liveshare_id',
        'uploaded_by',
        'name',
        'original_name',
        'size',
        'type',
        'full_path',
    ];

    /**
     * The liveshare this file belongs to.
     */
    public function liveshare()
    {
        return $this->belongsTo(Liveshare::class);
    }

    /**
     * The user who uploaded this file.
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The tags on this file.
     */
    public function tags()
    {
        return $this->belongsToMany(LiveshareTag::class, 'liveshare_file_tags');
    }

    /**
     * Get the display name for the file.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->original_name ?? $this->name;
    }
}
