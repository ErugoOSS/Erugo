<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mesh_nodes', function (Blueprint $table) {
            $table->text('public_key')->nullable()->after('endpoint');
            $table->string('status')->default('legacy')->after('public_key'); // legacy, active, revoked
            $table->timestamp('revoked_at')->nullable()->after('status');
        });

        Schema::create('mesh_enrollment_nonces', function (Blueprint $table) {
            $table->id();
            $table->string('nonce')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mesh_enrollment_nonces');

        Schema::table('mesh_nodes', function (Blueprint $table) {
            $table->dropColumn(['public_key', 'status', 'revoked_at']);
        });
    }
};
