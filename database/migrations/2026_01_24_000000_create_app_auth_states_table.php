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
        Schema::create('app_auth_states', function (Blueprint $table) {
            $table->id();
            $table->string('state', 64)->unique();
            $table->unsignedBigInteger('provider_id');
            $table->string('callback_scheme', 50);
            $table->string('device_name')->nullable();
            $table->enum('action', ['login', 'link'])->default('login');
            $table->unsignedBigInteger('user_id')->nullable(); // For linking existing accounts
            $table->timestamp('expires_at');
            $table->timestamps();
            
            $table->foreign('provider_id')->references('id')->on('auth_providers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_auth_states');
    }
};
