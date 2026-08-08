<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveshareMember extends Model
{
    protected $fillable = [
        'liveshare_id',
        'user_id',
        'role',
    ];

    /**
     * Valid member roles.
     */
    public const ROLES = ['manager', 'collaborator', 'viewer'];

    /**
     * The liveshare this membership belongs to.
     */
    public function liveshare()
    {
        return $this->belongsTo(Liveshare::class);
    }

    /**
     * The user this membership belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this member has a management role.
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Check if this member can add files.
     */
    public function canAddFiles(): bool
    {
        return in_array($this->role, ['manager', 'collaborator']);
    }
}
