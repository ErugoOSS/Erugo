<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replaces the JWT-based invite token approach with a secure random token
     * stored directly in the database. The JWT approach was broken because the
     * token TTL (default 60 min) was far shorter than the invite expiry (7 days),
     * causing invite links to stop working after one hour.
     */
    public function up(): void
    {
        Schema::table('reverse_share_invites', function (Blueprint $table) {
            $table->string('guest_token', 64)->nullable()->after('guest_user_id');
            $table->index('guest_token');
        });
    }

    public function down(): void
    {
        Schema::table('reverse_share_invites', function (Blueprint $table) {
            $table->dropIndex(['guest_token']);
            $table->dropColumn('guest_token');
        });
    }
};
