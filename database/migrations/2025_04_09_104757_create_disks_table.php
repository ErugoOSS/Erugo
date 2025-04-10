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
        Schema::create('disks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('use_for_shares')->default(false)->comment('should this disk should be used for new shares');
            $table->string('driver');
            $table->string('root')->nullable();
            $table->string('key')->nullable();
            $table->string('secret')->nullable();
            $table->string('region')->nullable();
            $table->string('bucket')->nullable();
            $table->string('url')->nullable();
            $table->string('endpoint')->nullable();
            $table->boolean('use_path_style_endpoint')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('disks');
    }
};
