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
        // Create upload_sessions table
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('upload_id')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('filename');
            $table->bigInteger('filesize');
            $table->string('filetype');
            $table->integer('total_chunks');
            $table->integer('chunks_received')->default(0);
            $table->string('status')->default('pending'); // pending, complete, processed, failed
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->timestamps();

            // Add index for faster lookups
            $table->index(['upload_id', 'user_id']);
        });

        // Create chunk_uploads table
        Schema::create('chunk_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('upload_session_id')->constrained('upload_sessions')->onDelete('cascade');
            $table->integer('chunk_index');
            $table->integer('chunk_size');
            $table->string('chunk_path');
            $table->timestamps();

            // Ensure uniqueness of chunks within a session
            $table->unique(['upload_session_id', 'chunk_index']);
        });

        // Modify files table to add temp_path column
        Schema::table('files', function (Blueprint $table) {
            $table->string('temp_path')->nullable()->after('size');
            // Make share_id nullable to allow files to exist before being associated with a share
            $table->foreignId('share_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the chunk_uploads table
        Schema::dropIfExists('chunk_uploads');

        // Drop the upload_sessions table
        Schema::dropIfExists('upload_sessions');

        // Revert changes to files table
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('temp_path');
            // Restore share_id to non-nullable
            $table->foreignId('share_id')->change();
        });
    }
};
