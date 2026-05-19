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
        // Check if the setting already exists to avoid duplicate entries
        $existing = DB::table('settings')->where('key', 'allow_unlimited_expiry')->first();
        
        if (!$existing) {
            DB::table('settings')->insert([
                'key' => 'allow_unlimited_expiry',
                'value' => 'false',
                'previous_value' => null,
                'group' => 'system.shares',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'allow_unlimited_expiry')->delete();
    }
};
