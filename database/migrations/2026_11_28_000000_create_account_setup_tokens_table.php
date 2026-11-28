<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens for the account-created email, kept apart from password_reset_tokens
 * so they can last 24 hours while forgot-password tokens stay at 60 minutes.
 * Same columns as password_reset_tokens, which Laravel's token repository uses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('account_setup_tokens')) {
            return;
        }

        Schema::create('account_setup_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_setup_tokens');
    }
};
