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
        Schema::create('liveshare_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liveshare_id')->constrained('liveshares')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('role'); // 'manager', 'collaborator', 'viewer'
            $table->timestamps();
            $table->unique(['liveshare_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liveshare_members');
    }
};
