<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LiveshareInvite extends Model
{
    protected $fillable = [
        'liveshare_id',
        'created_by',
        'type',
        'email',
        'token',
        'role',
        'max_uses',
        'use_count',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'use_count' => 'integer',
        'max_uses' => 'integer',
    ];

    /**
     * The liveshare this invite belongs to.
     */
    public function liveshare()
    {
        return $this->belongsTo(Liveshare::class);
    }

    /**
     * The user who created this invite.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this invite has expired.
     */
    public function isExpired(): bool
    {
        if ($this->expires_at === null) {
            return false;
        }

        return Carbon::now()->greaterThan($this->expires_at);
    }

    /**
     * Check if this invite has been used up (use_count >= max_uses).
     */
    public function isExhausted(): bool
    {
        if ($this->max_uses === null) {
            return false;
        }

        return $this->use_count >= $this->max_uses;
    }

    /**
     * Check if this invite can still be used.
     */
    public function canBeUsed(): bool
    {
        return !$this->isExpired() && !$this->isExhausted();
    }

    /**
     * Record a use of this invite (increment use_count).
     */
    public function recordUse(): void
    {
        $this->increment('use_count');
    }

    /**
     * For email invites, check that the given email matches.
     */
    public function isValidForEmail(string $email): bool
    {
        if ($this->type !== 'email') {
            return true;
        }

        return strtolower($this->email) === strtolower($email);
    }
}
