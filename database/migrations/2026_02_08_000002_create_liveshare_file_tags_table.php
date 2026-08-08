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
        Schema::create('liveshare_file_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liveshare_file_id')->constrained('liveshare_files')->onDelete('cascade');
            $table->foreignId('liveshare_tag_id')->constrained('liveshare_tags')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['liveshare_file_id', 'liveshare_tag_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liveshare_file_tags');
    }
};
