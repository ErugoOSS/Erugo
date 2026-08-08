<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveshareTag extends Model
{
    protected $fillable = [
        'liveshare_id',
        'name',
        'type',
        'color',
        'created_by',
    ];

    /**
     * Valid tag types.
     */
    public const TYPES = ['custom', 'auto'];

    /**
     * The liveshare this tag belongs to.
     */
    public function liveshare()
    {
        return $this->belongsTo(Liveshare::class);
    }

    /**
     * The user who created this tag (null for auto-tags).
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The files that have this tag.
     */
    public function files()
    {
        return $this->belongsToMany(LiveshareFile::class, 'liveshare_file_tags');
    }

    /**
     * Check if this is a custom tag.
     */
    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    /**
     * Check if this is an auto-generated tag.
     */
    public function isAuto(): bool
    {
        return $this->type === 'auto';
    }
}
