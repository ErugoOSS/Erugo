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
        Schema::table('shares', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['user_id']);

            // Make the column nullable
            $table->unsignedBigInteger('user_id')->nullable()->change();

            // Add the new foreign key with onDelete('SET NULL')
            $table->foreign('user_id')->references('id')->on('users')->onDelete('SET NULL');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shares', function (Blueprint $table) {
            // Drop the modified foreign key
            $table->dropForeign(['user_id']);

            // Restore the column to non-nullable
            $table->unsignedBigInteger('user_id')->nullable(false)->change();

            // Restore the original foreign key constraint without onDelete
            $table->foreign('user_id')->references('id')->on('users');
        });
    }
};
