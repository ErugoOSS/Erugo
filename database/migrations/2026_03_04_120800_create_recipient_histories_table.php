<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipient_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->string('name')->nullable();
            $table->unsignedInteger('use_count')->default(1);
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'email'], 'uniq_recipient_per_user_email');
            $table->index(['user_id', 'last_used_at'], 'idx_recipient_user_lastused');
            $table->index(['user_id', 'email'], 'idx_recipient_user_email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipient_histories');
    }
};
