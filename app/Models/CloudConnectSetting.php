<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloudConnectSetting extends Model
{
    protected $fillable = [
        'enabled',
        'api_url',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'instance_token',
        'instance_id',
        'private_key',
        'public_key',
        'status',
        'subdomain',
        'full_domain',
        'tunnel_ip',
        'last_error',
        'user_email',
        'subscription_status',
        'subscription_plan',
        'account_status',
        'heartbeat_endpoint',
        'heartbeat_interval',
        'last_heartbeat_at',
        'last_heartbeat_success',
        'last_heartbeat_error',
        'user_name',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    /**
     * Get the singleton instance of cloud connect settings.
     * Creates one if it doesn't exist.
     */
    public static function getInstance(): self
    {
        return self::firstOrCreate([], [
            'enabled' => false,
            'api_url' => 'https://api.erugo.cloud/v1',
            'status' => 'disconnected',
        ]);
    }
}

