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
        Schema::create('user_auth_provider', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('auth_provider_id')->constrained()->onDelete('cascade');
            $table->string('provider_user_id');  // User ID from the external provider
            $table->string('provider_email')->nullable();  // Email from the external provider
            $table->text('access_token')->nullable();  // OAuth access token if needed
            $table->text('refresh_token')->nullable();  // OAuth refresh token if needed
            $table->timestamp('token_expires_at')->nullable();  // Token expiration timestamp
            $table->json('provider_data')->nullable();  // Additional data from provider
            $table->timestamps();
            
            // Ensure a user can only link to a provider once
            $table->unique(['user_id', 'auth_provider_id']);
            // Ensure provider_user_id is unique per auth_provider_id
            $table->unique(['auth_provider_id', 'provider_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_auth_provider');
    }
}; 