<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liveshare_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liveshare_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->string('type'); // 'email' or 'link'
            $table->string('email')->nullable(); // only for email invites
            $table->string('token')->unique(); // URL token
            $table->string('role'); // 'manager', 'collaborator', 'viewer'
            $table->integer('max_uses')->nullable(); // null = unlimited (link invites)
            $table->integer('use_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liveshare_invites');
    }
};
