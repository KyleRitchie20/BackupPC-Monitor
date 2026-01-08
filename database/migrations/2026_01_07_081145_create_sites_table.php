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
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('backuppc_url');
            $table->string('connection_method')->default('ssh');
            $table->string('ssh_host')->nullable();
            $table->integer('ssh_port')->default(22);
            $table->string('ssh_username')->nullable();
            $table->string('ssh_password')->nullable();
            $table->string('api_key')->nullable();
            $table->integer('polling_interval')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
