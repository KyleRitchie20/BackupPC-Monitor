<?php

namespace App\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            
            // Schedule weekly backup report generation and email
            // Runs every Sunday at 8:00 AM
            $schedule->command('backup:generate-report --send')
                ->weeklyOn(0, '08:00')
                ->withoutOverlapping()
                ->runInBackground()
                ->appendOutputTo(storage_path('logs/backup-report.log'))
                ->onSuccess(function () {
                    \Illuminate\Support\Facades\Log::info('Weekly backup report generated and sent successfully');
                })
                ->onFailure(function () {
                    \Illuminate\Support\Facades\Log::error('Failed to generate or send weekly backup report');
                });
        });
    }
}
