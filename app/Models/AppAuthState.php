<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppAuthState extends Model
{
    protected $fillable = [
        'state',
        'provider_id',
        'callback_scheme',
        'device_name',
        'action',
        'user_id',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * The authentication provider associated with this state
     */
    public function provider()
    {
        return $this->belongsTo(AuthProvider::class, 'provider_id');
    }

    /**
     * The user associated with this state (for linking accounts)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this state has expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Clean up expired states
     */
    public static function cleanupExpired(): int
    {
        return static::where('expires_at', '<', now())->delete();
    }
}
