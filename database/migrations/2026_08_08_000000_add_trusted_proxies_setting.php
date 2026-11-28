<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Loopback plus the RFC1918/ULA ranges. Reverse proxies (Nginx Proxy Manager,
     * Traefik, cloudflared, a sidecar Caddy) reach Erugo from a Docker bridge or
     * LAN address, so this makes proxied installs key on the real client IP without
     * the admin having to configure anything. Spoofing X-Forwarded-* still requires
     * the attacker's packets to arrive from a private address.
     */
    private const DEFAULT_TRUSTED_PROXIES = '127.0.0.1,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,fc00::/7';

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
