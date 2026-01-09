<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\BackupData;
use App\Services\BackupPCService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    protected $backupPCService;

    public function __construct(BackupPCService $backupPCService)
    {
        $this->backupPCService = $backupPCService;
    }

    /**
     * Fetch backup data for a specific site
     */
    public function fetchBackupData(Request $request)
    {
        Log::info('DashboardController::fetchBackupData called');
        Log::info('Request data: ' . json_encode($request->all()));

        try {
            $request->validate([
                'site_id' => 'required|exists:sites,id'
            ]);

            $site = Site::find($request->site_id);
            Log::info('Site found: ' . $site->name . ' (ID: ' . $site->id . ')');

            // Check if user has access to this site
            if (!Auth::user()->isAdmin() && Auth::user()->site_id != $site->id) {
                Log::warning('Unauthorized access attempt to site ' . $site->id . ' by user ' . Auth::user()->id);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this site.'
                ], 403);
            }

            // Dispatch the fetch job
            Log::info('Starting backup data fetch for site: ' . $site->name);
            $result = $this->backupPCService->fetchBackupData($site);

            if ($result) {
                Log::info('Backup data fetch completed successfully for site: ' . $site->name);
                return response()->json([
                    'success' => true,
                    'message' => 'Backup data fetch initiated successfully.'
                ]);
            } else {
                Log::error('Backup data fetch failed for site: ' . $site->name);
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch backup data.'
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Exception in fetchBackupData: ' . $e->getMessage());
            Log::error('Exception trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Fetch backup data for all sites (admin only)
     */
    public function fetchAllBackupData()
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $sites = Site::where('is_active', true)->get();

        foreach ($sites as $site) {
            $this->backupPCService->fetchBackupData($site);
        }

        return response()->json([
            'success' => true,
            'message' => 'Backup data fetch initiated for all sites.'
        ]);
    }

    /**
     * Get backup status for user's site
     */
    public function getBackupStatus()
    {
        $site = Site::find(Auth::user()->site_id);

        if (!$site) {
            return response()->json([
                'success' => false,
                'message' => 'No site assigned to your account.'
            ], 404);
        }

        $summary = $this->backupPCService->getBackupStatusSummary($site);

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Show reports page with all sites and download options
     */
    public function reports()
    {
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Unauthorized access.');
        }

        $sites = Site::all();

        return view('reports.index', compact('sites'));
    }

    /**
     * Download backup report PDF for all sites (admin only)
     */
    public function downloadReport()
    {
        if (!Auth::user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access.'
            ], 403);
        }

        $sites = Site::where('is_active', true)->get();
        $generatedAt = now()->format('Y-m-d H:i:s');
        
        // Generate combined report for all sites
        $allProcessedData = [];
        $totalHealthy = 0;
        $totalWarning = 0;
        $totalClients = 0;

        foreach ($sites as $site) {
            $backupData = $site->backupData()->where('disabled', false)->get();
            
            foreach ($backupData as $backup) {
                $fullBackupAge = $this->calculateFullBackupAge($backup);
                $incrementalAge = $this->calculateIncrementalAge($backup);
                
                $fullBackupAgeClass = $fullBackupAge > 14 ? 'status-danger' : ($fullBackupAge > 7 ? 'status-warning' : 'status-good');
                $incrementalAgeClass = $incrementalAge > 7 ? 'status-danger' : ($incrementalAge > 3 ? 'status-warning' : 'status-good');
                
                $status = 'Healthy';
                $statusClass = 'status-good';
                
                if ($fullBackupAge > 14 || $incrementalAge > 7) {
                    $status = 'Critical';
                    $statusClass = 'status-danger';
                    $totalWarning++;
                } elseif ($fullBackupAge > 7 || $incrementalAge > 3) {
                    $status = 'Warning';
                    $statusClass = 'status-warning';
                    $totalWarning++;
                } else {
                    $totalHealthy++;
                }
                
                $allProcessedData[] = [
                    'site_name' => $site->name,
                    'host_name' => $backup->host_name,
                    'fullBackupAge' => $fullBackupAge === 0 ? 'N/A' : $fullBackupAge . ' days',
                    'fullBackupAgeClass' => $fullBackupAgeClass,
                    'incrementalAge' => $incrementalAge === 0 ? 'N/A' : $incrementalAge . ' days',
                    'incrementalAgeClass' => $incrementalAgeClass,
                    'sizeFormatted' => $this->formatSize($backup->last_backup_size ?? 0),
                    'status' => $status,
                    'statusClass' => $statusClass,
                ];
                $totalClients++;
            }
        }

        $stats = [
            'totalClients' => $totalClients,
            'healthyClients' => $totalHealthy,
            'warningClients' => $totalWarning,
        ];

        $pdf = Pdf::loadView('emails.backup-report', [
            'site' => $sites->first(),
            'backupData' => [],
            'generatedAt' => $generatedAt,
            'stats' => $stats,
            'processedData' => $allProcessedData
        ]);
        $pdf->setPaper('a4', 'landscape');
        
        $filename = 'backup-report-all-sites-' . now()->format('Y-m-d') . '.pdf';
        
        return $pdf->download($filename);
    }

    /**
     * Calculate full backup age in days with 1 decimal precision.
     */
    protected function calculateFullBackupAge(BackupData $backup)
    {
        if (!$backup->last_backup_time || !$backup->full_backup_count || $backup->full_backup_count == 0) {
            return 0;
        }

        $rawData = $backup->raw_data;
        if (isset($rawData['fullBackupTime']) && $rawData['fullBackupTime']) {
            $fullBackupTime = Carbon::parse($rawData['fullBackupTime']);
            return round($fullBackupTime->diffInHours(now()) / 24, 1);
        }

        return 0;
    }

    /**
     * Calculate incremental backup age in days with 1 decimal precision.
     */
    protected function calculateIncrementalAge(BackupData $backup)
    {
        // Use incr_age from raw_data if available
        $rawData = $backup->raw_data;
        if (isset($rawData['incr_age']) && $rawData['incr_age'] > 0) {
            $incrAgeTimestamp = $rawData['incr_age'];
            $incrBackupTime = \Carbon\Carbon::createFromTimestamp($incrAgeTimestamp);
            return round($incrBackupTime->diffInHours(now()) / 24, 1);
        }
        
        // Fallback to last_backup_time if no incr_age
        if (!$backup->last_backup_time) {
            return 0;
        }

        return round($backup->last_backup_time->diffInHours(now()) / 24, 1);
    }

    /**
     * Format size in human readable format.
     */
    protected function formatSize($bytes)
    {
        if ($bytes == 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
    }
}
