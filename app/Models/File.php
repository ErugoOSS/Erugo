<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    protected $fillable = [
        'name',
        'original_name',
        'size',
        'type',
        'share_id',
        'temp_path'
    ];

    public function share()
    {
        return $this->belongsTo(Share::class);
    }

    public function user()
    {
        return $this->share->user();
    }

    /**
     * Get the display name for the file (original name if available, otherwise sanitized name)
     */
    public function getDisplayNameAttribute()
    {
        return $this->original_name ?? $this->name;
    }

}
