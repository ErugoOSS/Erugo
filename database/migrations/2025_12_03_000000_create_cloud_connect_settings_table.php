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
        // Create the new cloud_connect_settings table
        Schema::create('cloud_connect_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('api_url')->default('https://api.erugo.cloud/v1');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->string('token_expires_at')->nullable();
            $table->text('instance_token')->nullable();
            $table->string('instance_id')->nullable();
            $table->text('private_key')->nullable();
            $table->string('public_key')->nullable();
            $table->string('status')->default('disconnected');
            $table->string('subdomain')->nullable();
            $table->string('full_domain')->nullable();
            $table->string('tunnel_ip')->nullable();
            $table->text('last_error')->nullable();
            $table->string('user_email')->nullable();
            $table->string('subscription_status')->nullable();
            $table->string('subscription_plan')->nullable();
            $table->string('account_status')->nullable();
            $table->string('heartbeat_endpoint')->nullable();
            $table->string('heartbeat_interval')->nullable();
            $table->string('last_heartbeat_at')->nullable();
            $table->string('last_heartbeat_success')->nullable();
            $table->text('last_heartbeat_error')->nullable();
            $table->string('user_name')->nullable();
            $table->timestamps();
        });

        // Migrate existing data from settings table if it exists
        $this->migrateExistingData();

        // Remove old cloud_connect_* entries from settings table
        DB::table('settings')->where('group', 'system.cloud_connect')->delete();
    }

    /**
     * Migrate existing cloud connect settings from the settings table
     */
    protected function migrateExistingData(): void
    {
        // Check if there's any existing cloud connect data in settings
        $existingSettings = DB::table('settings')
            ->where('group', 'system.cloud_connect')
            ->pluck('value', 'key')
            ->toArray();

        if (empty($existingSettings)) {
            // No existing data, create default row
            DB::table('cloud_connect_settings')->insert([
                'enabled' => false,
                'api_url' => 'https://api.erugo.cloud/v1',
                'status' => 'disconnected',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        // Map old key names to new column names
        $keyToColumn = [
            'cloud_connect_enabled' => 'enabled',
            'cloud_connect_api_url' => 'api_url',
            'cloud_connect_access_token' => 'access_token',
            'cloud_connect_refresh_token' => 'refresh_token',
            'cloud_connect_token_expires_at' => 'token_expires_at',
            'cloud_connect_instance_token' => 'instance_token',
            'cloud_connect_instance_id' => 'instance_id',
            'cloud_connect_private_key' => 'private_key',
            'cloud_connect_public_key' => 'public_key',
            'cloud_connect_status' => 'status',
            'cloud_connect_subdomain' => 'subdomain',
            'cloud_connect_full_domain' => 'full_domain',
            'cloud_connect_tunnel_ip' => 'tunnel_ip',
            'cloud_connect_last_error' => 'last_error',
            'cloud_connect_user_email' => 'user_email',
            'cloud_connect_subscription_status' => 'subscription_status',
            'cloud_connect_subscription_plan' => 'subscription_plan',
            'cloud_connect_account_status' => 'account_status',
            'cloud_connect_heartbeat_endpoint' => 'heartbeat_endpoint',
            'cloud_connect_heartbeat_interval' => 'heartbeat_interval',
            'cloud_connect_last_heartbeat_at' => 'last_heartbeat_at',
            'cloud_connect_last_heartbeat_success' => 'last_heartbeat_success',
            'cloud_connect_last_heartbeat_error' => 'last_heartbeat_error',
            'cloud_connect_user_name' => 'user_name',
        ];

        // Build the new row data
        $newData = [
            'enabled' => false,
            'api_url' => 'https://api.erugo.cloud/v1',
            'status' => 'disconnected',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        foreach ($existingSettings as $key => $value) {
            if (isset($keyToColumn[$key])) {
                $column = $keyToColumn[$key];
                
                // Handle boolean conversion for 'enabled'
                if ($column === 'enabled') {
                    $newData[$column] = $value === 'true' || $value === '1';
                } else {
                    $newData[$column] = $value;
                }
            }
        }

        DB::table('cloud_connect_settings')->insert($newData);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally migrate data back to settings table before dropping
        $cloudConnectSettings = DB::table('cloud_connect_settings')->first();
        
        if ($cloudConnectSettings) {
            $columnToKey = [
                'enabled' => 'cloud_connect_enabled',
                'api_url' => 'cloud_connect_api_url',
                'access_token' => 'cloud_connect_access_token',
                'refresh_token' => 'cloud_connect_refresh_token',
                'token_expires_at' => 'cloud_connect_token_expires_at',
                'instance_token' => 'cloud_connect_instance_token',
                'instance_id' => 'cloud_connect_instance_id',
                'private_key' => 'cloud_connect_private_key',
                'public_key' => 'cloud_connect_public_key',
                'status' => 'cloud_connect_status',
                'subdomain' => 'cloud_connect_subdomain',
                'full_domain' => 'cloud_connect_full_domain',
                'tunnel_ip' => 'cloud_connect_tunnel_ip',
                'last_error' => 'cloud_connect_last_error',
                'user_email' => 'cloud_connect_user_email',
                'subscription_status' => 'cloud_connect_subscription_status',
                'subscription_plan' => 'cloud_connect_subscription_plan',
                'account_status' => 'cloud_connect_account_status',
                'heartbeat_endpoint' => 'cloud_connect_heartbeat_endpoint',
                'heartbeat_interval' => 'cloud_connect_heartbeat_interval',
                'last_heartbeat_at' => 'cloud_connect_last_heartbeat_at',
                'last_heartbeat_success' => 'cloud_connect_last_heartbeat_success',
                'last_heartbeat_error' => 'cloud_connect_last_heartbeat_error',
                'user_name' => 'cloud_connect_user_name',
            ];

            foreach ($columnToKey as $column => $key) {
                $value = $cloudConnectSettings->$column ?? null;
                
                // Convert boolean back to string for settings table
                if ($column === 'enabled') {
                    $value = $value ? 'true' : 'false';
                }

                DB::table('settings')->insertOrIgnore([
                    'key' => $key,
                    'value' => $value,
                    'previous_value' => null,
                    'group' => 'system.cloud_connect',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::dropIfExists('cloud_connect_settings');
    }
};

