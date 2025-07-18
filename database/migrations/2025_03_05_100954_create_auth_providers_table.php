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
        Schema::create('auth_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider_class');
            $table->json('provider_config')->nullable();
            $table->boolean('enabled')->default(true);
            $table->boolean('allow_registration')->default(false);
            $table->timestamps();
        });

        Schema::table('user_auth_provider', function (Blueprint $table) {
            $table->foreignId('auth_provider_id')->constrained()->onDelete('cascade');

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
        Schema::table('user_auth_provider', function (Blueprint $table) {

            // Ensure a user can only link to a provider once
            $table->dropUnique(['user_id', 'auth_provider_id']);
            // Ensure provider_user_id is unique per auth_provider_id
            $table->dropUnique(['auth_provider_id', 'provider_user_id']);

            $table->dropConstrainedForeignId('auth_provider_id');
        });

        Schema::dropIfExists('auth_providers');
    }
};
