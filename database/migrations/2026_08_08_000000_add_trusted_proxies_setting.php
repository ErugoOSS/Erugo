<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Loopback only. Caddy passes forwarded headers on from private addresses,
     * so trusting the private ranges here would let any device on the LAN set
     * X-Forwarded-For and dodge the per-IP rate limits. Admins behind a reverse
     * proxy add its address in System settings; the settings page warns when it
     * sees forwarded headers from an address that isn't trusted.
     */
    private const DEFAULT_TRUSTED_PROXIES = '127.0.0.1,::1';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'trusted_proxies',
            'value' => self::DEFAULT_TRUSTED_PROXIES,
            'previous_value' => null,
            'group' => 'system',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // An earlier revision of this migration seeded an empty value. Nothing has
        // shipped with that default, so an empty value here can only be the old
        // default rather than a deliberate "trust nothing" choice.
        DB::table('settings')
            ->where('key', 'trusted_proxies')
            ->where('value', '')
            ->update([
                'value' => self::DEFAULT_TRUSTED_PROXIES,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'trusted_proxies')->delete();
    }
};
