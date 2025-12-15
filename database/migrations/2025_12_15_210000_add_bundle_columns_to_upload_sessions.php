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
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->boolean('is_bundle')->default(false)->after('file_id');
            $table->text('bundle_file_ids')->nullable()->after('is_bundle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('upload_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_bundle', 'bundle_file_ids']);
        });
    }
};

