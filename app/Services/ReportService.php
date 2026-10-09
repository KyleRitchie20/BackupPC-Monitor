<?php

namespace App\Services;

use App\Models\BackupData;
use App\Models\BackupRun;
use App\Models\Site;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReportService
{
    /**
     * Gather and aggregate report data for specified sites and date range.
     *
     * @param array|int $siteIds
     * @param Carbon|string $startDate
     * @param Carbon|string $endDate
     * @param array $options
     * @return array
     */
    public function getReportData($siteIds, $startDate, $endDate, array $options = []): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $siteIdList = is_array($siteIds) ? $siteIds : [$siteIds];
        $sites = Site::whereIn('id', $siteIdList)->get();

        $includeExcluded = $options['include_excluded'] ?? false;
        $statusFilter = $options['status_filter'] ?? 'all'; // all, healthy, issues

        // Query backup runs completed in date range
        $runsQuery = BackupRun::whereIn('site_id', $siteIdList)
            ->whereBetween('completed_at', [$start, $end])
            ->orderBy('completed_at', 'desc');

        $allRuns = $runsQuery->get();

        // Calculate statistics
        $totalRuns = $allRuns->count();
        $fullRuns = $allRuns->where('type', 'full')->count();
        $incrRuns = $allRuns->where('type', 'incremental')->count();
        $successfulRuns = $allRuns->where('status', 'success')->count();
        $warningRuns = $allRuns->where('status', 'warning')->count();
        $failedRuns = $allRuns->where('status', 'failed')->count();
        $totalSize = $allRuns->sum('backup_size');

        $successRate = $totalRuns > 0 ? round(($successfulRuns / $totalRuns) * 100, 1) : 0;

        // Process host-level summary and SLA compliance across the sites
        $hostSummaries = [];
        $totalHosts = 0;
        $healthyHosts = 0;
        $warningHosts = 0;
        $criticalHosts = 0;

        foreach ($sites as $site) {
            $hostQuery = BackupData::where('site_id', $site->id)
                ->whereNotIn('host_name', ['server', 'disk_usage', 'cpool']);

            if (!$includeExcluded) {
                $hostQuery->where('exclude_from_report', false);
            }

            $hosts = $hostQuery->get();

            foreach ($hosts as $host) {
                $totalHosts++;

                // Get runs for this host in the selected period
                $hostRuns = $allRuns->where('site_id', $site->id)
                    ->where('host_name', $host->host_name);

                $latestRunInPeriod = $hostRuns->first();
                $runsInPeriodCount = $hostRuns->count();

                // Calculate current full backup age and incremental age
                $fullBackupAge = $this->calculateFullBackupAge($host);
                $incrementalAge = $this->calculateIncrementalAge($host);

                // Determine compliance / health status
                $status = 'Healthy';
                $statusClass = 'status-good';

                if ($fullBackupAge > 14 || $incrementalAge > 7 || $host->error_message) {
                    $status = 'Critical';
                    $statusClass = 'status-danger';
                    $criticalHosts++;
                } elseif ($fullBackupAge > 7 || $incrementalAge > 3 || $runsInPeriodCount === 0) {
                    $status = 'Warning';
                    $statusClass = 'status-warning';
                    $warningHosts++;
                } else {
                    $healthyHosts++;
                }

                // Filter by status if requested
                if ($statusFilter === 'healthy' && $status !== 'Healthy') {
                    continue;
                }
                if ($statusFilter === 'issues' && $status === 'Healthy') {
                    continue;
                }

                $fullAgeClass = $fullBackupAge > 14 ? 'status-danger' : ($fullBackupAge > 7 ? 'status-warning' : 'status-good');
                $incrAgeClass = $incrementalAge > 7 ? 'status-danger' : ($incrementalAge > 3 ? 'status-warning' : 'status-good');

                $hostSummaries[] = [
                    'site_id' => $site->id,
                    'site_name' => $site->name,
                    'host_name' => $host->host_name,
                    'state' => $host->state,
                    'runs_in_period' => $runsInPeriodCount,
                    'last_run_in_period' => $latestRunInPeriod ? $latestRunInPeriod->completed_at->format('Y-m-d H:i') : 'None in range',
                    'full_backup_age' => $fullBackupAge > 0 ? "{$fullBackupAge} d" : 'N/A',
                    'full_backup_age_class' => $fullAgeClass,
                    'incremental_age' => $incrementalAge > 0 ? "{$incrementalAge} d" : 'N/A',
                    'incremental_age_class' => $incrAgeClass,
                    'last_size_formatted' => $this->formatBytes($host->last_backup_size ?? 0),
                    'error_message' => $host->error_message,
                    'status' => $status,
                    'status_class' => $statusClass,
                ];
            }
        }

        return [
            'sites' => $sites,
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'date_range_label' => $start->format('M d, Y') . ' — ' . $end->format('M d, Y'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'stats' => [
                'total_runs' => $totalRuns,
                'full_runs' => $fullRuns,
                'incremental_runs' => $incrRuns,
                'successful_runs' => $successfulRuns,
                'warning_runs' => $warningRuns,
                'failed_runs' => $failedRuns,
                'success_rate' => $successRate,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'total_hosts' => $totalHosts,
                'healthy_hosts' => $healthyHosts,
                'warning_hosts' => $warningHosts,
                'critical_hosts' => $criticalHosts,
            ],
            'hosts' => $hostSummaries,
            'runs' => $allRuns,
        ];
    }

    /**
     * Generate PDF from report data.
     */
    public function generatePdf(array $reportData)
    {
        $pdf = Pdf::loadView('reports.pdf', $reportData);
        $pdf->setPaper('a4', 'landscape');
        return $pdf;
    }

    /**
     * Generate CSV string from report data.
     */
    public function generateCsv(array $reportData): string
    {
        $handle = fopen('php://temp', 'r+');

        // Title and metadata
        fputcsv($handle, ['BackupPC Monitor Report']);
        fputcsv($handle, ['Date Range', $reportData['date_range_label']]);
        fputcsv($handle, ['Generated At', $reportData['generated_at']]);
        fputcsv($handle, []);

        // Summary Stats
        fputcsv($handle, ['Summary Metrics']);
        fputcsv($handle, ['Total Runs', $reportData['stats']['total_runs']]);
        fputcsv($handle, ['Full Backups', $reportData['stats']['full_runs']]);
        fputcsv($handle, ['Incremental Backups', $reportData['stats']['incremental_runs']]);
        fputcsv($handle, ['Successful Runs', $reportData['stats']['successful_runs']]);
        fputcsv($handle, ['Failed Runs', $reportData['stats']['failed_runs']]);
        fputcsv($handle, ['Success Rate', $reportData['stats']['success_rate'] . '%']);
        fputcsv($handle, ['Total Data', $reportData['stats']['total_size_formatted']]);
        fputcsv($handle, ['Healthy Hosts', $reportData['stats']['healthy_hosts']]);
        fputcsv($handle, ['Warning/Critical Hosts', ($reportData['stats']['warning_hosts'] + $reportData['stats']['critical_hosts'])]);
        fputcsv($handle, []);

        // Hosts overview
        fputcsv($handle, ['Host SLA Compliance']);
        fputcsv($handle, [
            'Site',
            'Host',
            'Status',
            'Runs in Period',
            'Last Run in Period',
            'Full Backup Age',
            'Incremental Age',
            'Last Size',
            'Error'
        ]);

        foreach ($reportData['hosts'] as $host) {
            fputcsv($handle, [
                $host['site_name'],
                $host['host_name'],
                $host['status'],
                $host['runs_in_period'],
                $host['last_run_in_period'],
                $host['full_backup_age'],
                $host['incremental_age'],
                $host['last_size_formatted'],
                $host['error_message'] ?? 'None',
            ]);
        }
        fputcsv($handle, []);

        // Detailed Runs Log
        fputcsv($handle, ['Backup Runs Log']);
        fputcsv($handle, [
            'Site',
            'Host',
            'Type',
            'Completed At',
            'Duration',
            'Size',
            'Status',
            'Error Count',
            'Error Message'
        ]);

        foreach ($reportData['runs'] as $run) {
            fputcsv($handle, [
                $run->site->name ?? 'Unknown',
                $run->host_name,
                strtoupper($run->type),
                $run->completed_at ? $run->completed_at->format('Y-m-d H:i:s') : 'N/A',
                $run->formatted_duration,
                $run->formatted_size,
                strtoupper($run->status),
                $run->error_count,
                $run->error_message ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Send email with attached report (PDF and/or CSV).
     */
    public function sendReportEmail(array $reportData, array $recipients, string $subject = null, string $format = 'pdf'): bool
    {
        $subject = $subject ?: 'BackupPC Backup Report — ' . $reportData['date_range_label'];
        $pdf = ($format === 'pdf' || $format === 'both') ? $this->generatePdf($reportData)->output() : null;
        $csv = ($format === 'csv' || $format === 'both') ? $this->generateCsv($reportData) : null;

        $filenameBase = 'backuppc-report-' . $reportData['start_date'] . '-to-' . $reportData['end_date'];

        foreach ($recipients as $email) {
            $email = trim($email);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            try {
                Mail::send('emails.report-notification', ['reportData' => $reportData], function ($message) use ($email, $subject, $pdf, $csv, $filenameBase) {
                    $message->to($email)
                        ->subject($subject);

                    if ($pdf) {
                        $message->attachData($pdf, "{$filenameBase}.pdf", ['mime' => 'application/pdf']);
                    }
                    if ($csv) {
                        $message->attachData($csv, "{$filenameBase}.csv", ['mime' => 'text/csv']);
                    }
                });
                Log::info("Backup report emailed successfully to {$email}");
            } catch (\Exception $e) {
                Log::error("Failed to email backup report to {$email}: " . $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate full backup age in days.
     */
    protected function calculateFullBackupAge(BackupData $backup): float
    {
        $rawData = $backup->raw_data;
        if (isset($rawData['full_age']) && $rawData['full_age'] > 0) {
            $fullBackupTime = Carbon::createFromTimestamp($rawData['full_age']);
            return round($fullBackupTime->diffInHours(now()) / 24, 1);
        }
        if (isset($rawData['fullBackupTime']) && $rawData['fullBackupTime']) {
            $fullBackupTime = Carbon::parse($rawData['fullBackupTime']);
            return round($fullBackupTime->diffInHours(now()) / 24, 1);
        }
        return 0;
    }

    /**
     * Calculate incremental backup age in days.
     */
    protected function calculateIncrementalAge(BackupData $backup): float
    {
        $rawData = $backup->raw_data;
        if (isset($rawData['incr_age']) && $rawData['incr_age'] > 0) {
            $incrBackupTime = Carbon::createFromTimestamp($rawData['incr_age']);
            return round($incrBackupTime->diffInHours(now()) / 24, 1);
        }
        return 0;
    }

    /**
     * Format bytes to readable size string.
     */
    public function formatBytes($bytes): string
    {
        $bytes = (float)$bytes;
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $i = (int) floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
