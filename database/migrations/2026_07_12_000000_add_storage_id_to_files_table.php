<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RZA-SF's images already ran this. Skip if the column is there anyway,
        // for example if the migrations table was restored from elsewhere.
        if (Schema::hasColumn('files', 'storage_id')) {
            return;
        }

        Schema::table('files', function (Blueprint $table) {
            $table->string('storage_id', 36)->nullable()->after('full_path');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('storage_id');
        });
    }
};
