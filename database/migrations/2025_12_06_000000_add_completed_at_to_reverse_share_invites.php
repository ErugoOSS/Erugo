<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reverse_share_invites', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('used_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reverse_share_invites', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};

