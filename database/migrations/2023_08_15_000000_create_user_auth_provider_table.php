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
            $table->string('provider_user_id');  // User ID from the external provider
            $table->string('provider_email')->nullable();  // Email from the external provider
            $table->text('access_token')->nullable();  // OAuth access token if needed
            $table->text('refresh_token')->nullable();  // OAuth refresh token if needed
            $table->timestamp('token_expires_at')->nullable();  // Token expiration timestamp
            $table->json('provider_data')->nullable();  // Additional data from provider
            $table->timestamps();
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
