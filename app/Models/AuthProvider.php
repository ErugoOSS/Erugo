<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuthProvider extends Model
{
    protected $fillable = ['name', 'provider_class', 'provider_config', 'enabled', 'uuid'];
    protected $casts = [
        'provider_config' => 'object',
        'enabled' => 'boolean',
        'provider_data' => 'array',
        'allow_registration' => 'boolean',
        'trust_email' => 'boolean'
    ];

    /**
     * The users that have linked to this authentication provider
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_auth_provider')
            ->withPivot(['provider_user_id', 'provider_email', 'access_token', 
                        'refresh_token', 'token_expires_at', 'provider_data'])
            ->withTimestamps();
    }
}
