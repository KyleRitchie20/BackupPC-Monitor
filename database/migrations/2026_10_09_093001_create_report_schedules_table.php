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
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('site_id')->nullable()->constrained()->onDelete('cascade');
            $table->enum('frequency', ['daily', 'weekly', 'monthly'])->default('weekly');
            $table->integer('day_of_week')->default(1); // 0=Sunday, 1=Monday... 6=Saturday
            $table->integer('day_of_month')->default(1); // 1-28
            $table->string('time')->default('08:00'); // HH:MM
            $table->string('date_range_type')->default('last_7_days'); // yesterday, last_7_days, last_14_days, last_30_days, last_month
            $table->json('recipients'); // Array of email strings
            $table->enum('format', ['pdf', 'csv', 'both'])->default('pdf');
            $table->boolean('include_healthy')->default(true);
            $table->boolean('include_warnings')->default(true);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_run_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['is_active', 'frequency']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};
