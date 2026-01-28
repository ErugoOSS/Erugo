<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('share_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('share_id')->constrained('shares')->cascadeOnDelete();

            $table->string('email');
            $table->string('name')->nullable();

            $table->timestamp('last_emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['share_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('share_recipients');
    }
};
