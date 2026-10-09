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
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('host_name');
            $table->integer('backup_num')->nullable();
            $table->enum('type', ['full', 'incremental', 'unknown'])->default('unknown');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('completed_at')->index();
            $table->integer('duration_seconds')->nullable();
            $table->bigInteger('backup_size')->nullable();
            $table->integer('error_count')->default(0);
            $table->text('error_message')->nullable();
            $table->enum('status', ['success', 'warning', 'failed', 'in_progress'])->default('success');
            $table->json('raw_data')->nullable();
            $table->timestamps();

            // Indexes for fast date range querying
            $table->index(['site_id', 'completed_at']);
            $table->index(['site_id', 'host_name', 'completed_at']);
            // Unique key to prevent duplicating the same completed backup run
            $table->unique(['site_id', 'host_name', 'type', 'completed_at'], 'backup_runs_unique_entry');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
