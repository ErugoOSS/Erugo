<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 1 mesh metadata. The coordinator's SQLite database remains the
     * single writer; these tables are the seam a shared DB can replace later.
     */
    public function up(): void
    {
        Schema::create('mesh_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('node_id')->unique();
            $table->string('endpoint');
            $table->unsignedBigInteger('capacity_bytes')->nullable();
            $table->boolean('healthy')->default(false);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mesh_upload_intents', function (Blueprint $table) {
            $table->id();
            $table->string('intent_id')->unique();
            $table->string('object_id')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('filename');
            $table->unsignedBigInteger('size');
            $table->char('total_hash', 64);
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('chunk_count');
            $table->json('chunk_hashes');
            $table->string('status')->default('pending'); // pending, committed, failed, expired
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });

        Schema::create('mesh_objects', function (Blueprint $table) {
            $table->id();
            $table->string('object_id')->unique();
            $table->foreignId('share_id')->nullable()->constrained('shares')->nullOnDelete();
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('chunk_size');
            $table->unsignedInteger('chunk_count');
            $table->char('total_hash', 64);
            $table->unsignedInteger('generation')->default(1);
            $table->string('status')->default('sharding'); // sharding, sharded, replicated, deleted
            $table->timestamps();

            $table->index(['status', 'generation']);
        });

        Schema::create('mesh_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesh_object_id')->constrained('mesh_objects')->onDelete('cascade');
            $table->unsignedInteger('chunk_index');
            $table->char('hash', 64);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->unique(['mesh_object_id', 'chunk_index']);
        });

        Schema::create('mesh_placements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chunk_id')->constrained('mesh_chunks')->onDelete('cascade');
            $table->foreignId('node_id')->constrained('mesh_nodes')->onDelete('cascade');
            $table->string('state')->default('pending'); // pending, acknowledged, present, absent
            $table->char('verified_hash', 64)->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['chunk_id', 'node_id']);
            $table->index(['node_id', 'state']);
        });

        Schema::create('mesh_replication_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mesh_object_id')->constrained('mesh_objects')->onDelete('cascade');
            $table->foreignId('node_id')->constrained('mesh_nodes')->onDelete('cascade');
            $table->string('state')->default('queued'); // queued, running, done, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['mesh_object_id', 'node_id']);
            $table->index(['state', 'available_at']);
        });

        Schema::create('mesh_tombstones', function (Blueprint $table) {
            $table->id();
            $table->string('object_id');
            $table->unsignedInteger('generation');
            $table->string('state')->default('pending'); // pending, applied, failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['object_id', 'generation']);
            $table->index(['state', 'available_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesh_tombstones');
        Schema::dropIfExists('mesh_replication_jobs');
        Schema::dropIfExists('mesh_placements');
        Schema::dropIfExists('mesh_chunks');
        Schema::dropIfExists('mesh_objects');
        Schema::dropIfExists('mesh_upload_intents');
        Schema::dropIfExists('mesh_nodes');
    }
};
