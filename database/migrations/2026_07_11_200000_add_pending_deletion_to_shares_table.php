<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RZA-SF's images already ran this. Skip if the columns are there
        // anyway, for example if the migrations table was restored from elsewhere.
        if (Schema::hasColumn('shares', 'deletion_requested_at')) {
            return;
        }

        Schema::table('shares', function (Blueprint $table) {
            // Tracks when a user or admin requested early deletion of a share.
            // Status 'pending_deletion' is the in-flight state between request and
            // actual file cleanup. Share link stops working immediately; files are
            // cleaned on the next admin action or cleanup job run.
            $table->timestamp('deletion_requested_at')->nullable()->after('status');
            $table->string('deletion_requested_by', 10)->nullable()->after('deletion_requested_at'); // 'user' | 'admin'
        });
    }

    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            $table->dropColumn(['deletion_requested_at', 'deletion_requested_by']);
        });
    }
};
