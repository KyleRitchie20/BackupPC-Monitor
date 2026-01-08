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
        Schema::create('backup_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained();
            $table->string('host_name');
            $table->string('state');
            $table->dateTime('last_backup_time')->nullable();
            $table->bigInteger('last_backup_size')->nullable();
            $table->integer('full_backup_count')->default(0);
            $table->integer('incremental_backup_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('raw_data')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_data');
    }
};
