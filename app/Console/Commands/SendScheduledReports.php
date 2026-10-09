<?php

namespace App\Console\Commands;

use App\Models\ReportSchedule;
use App\Models\Site;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-scheduled {--force : Force execute all active schedules regardless of time}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Evaluate and execute active automated backup report schedules';

    /**
     * Execute the console command.
     */
    public function handle(ReportService $reportService): int
    {
        $force = (bool)$this->option('force');
        $schedules = ReportSchedule::where('is_active', true)->get();

        if ($schedules->isEmpty()) {
            $this->info('No active report schedules configured.');
            return 0;
        }

        $executedCount = 0;

        foreach ($schedules as $schedule) {
            if (!$force && !$schedule->isDue()) {
                $this->line("Schedule '{$schedule->name}' [ID: {$schedule->id}] is not due at this time.");
                continue;
            }

            $this->info("Running schedule: '{$schedule->name}' [ID: {$schedule->id}]...");

            [$start, $end] = $schedule->resolveDateRange();
            $targetSiteIds = $schedule->site_id ? [$schedule->site_id] : Site::pluck('id')->toArray();

            $reportData = $reportService->getReportData($targetSiteIds, $start, $end);

            $subject = "BackupPC Scheduled Report: {$schedule->name} ({$reportData['date_range_label']})";
            $success = $reportService->sendReportEmail(
                $reportData,
                $schedule->recipients,
                $subject,
                $schedule->format
            );

            if ($success) {
                $schedule->update(['last_run_at' => now()]);
                $this->info("✓ Report emailed to " . implode(', ', $schedule->recipients));
                $executedCount++;
            } else {
                $this->error("✗ Failed to send report for schedule ID: {$schedule->id}");
            }
        }

        $this->info("Completed scheduled reports evaluation. {$executedCount} schedule(s) processed.");
        return 0;
    }
}
