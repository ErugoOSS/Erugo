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
        Schema::table('cloud_connect_settings', function (Blueprint $table) {
            $table->string('subscription_cancel_at_period_end')->nullable()->after('subscription_plan');
            $table->string('subscription_current_period_end')->nullable()->after('subscription_cancel_at_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cloud_connect_settings', function (Blueprint $table) {
            $table->dropColumn(['subscription_cancel_at_period_end', 'subscription_current_period_end']);
        });
    }
};

