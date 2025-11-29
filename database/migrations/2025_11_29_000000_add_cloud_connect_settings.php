<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cloud connect settings are stored in the settings table
        // This migration ensures the settings exist with default values
        $settings = [
            [
                'key' => 'cloud_connect_enabled',
                'value' => 'false',
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_api_url',
                'value' => 'https://api.erugo.cloud/v1',
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_access_token',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_refresh_token',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_token_expires_at',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_instance_token',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_instance_id',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_private_key',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_public_key',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_status',
                'value' => 'disconnected',
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_subdomain',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_full_domain',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_tunnel_ip',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_last_error',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_user_email',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_subscription_status',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'key' => 'cloud_connect_subscription_plan',
                'value' => null,
                'previous_value' => null,
                'group' => 'system.cloud_connect',
                'created_at' => now(),
                'updated_at' => now()
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->insertOrIgnore($setting);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('group', 'system.cloud_connect')->delete();
    }
};

