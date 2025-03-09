<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserAuthProvider extends Pivot
{
    protected $table = 'user_auth_provider';
    
    protected $casts = [
        'provider_data' => 'array',
        'token_expires_at' => 'datetime',
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    public function authProvider()
    {
        return $this->belongsTo(AuthProvider::class);
    }
} 