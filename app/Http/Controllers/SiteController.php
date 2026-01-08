<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\BackupData;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class SiteController extends Controller
{
    public function __construct()
    {
        // Middleware is applied in routes
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->isAdmin()) {
            $sites = Site::all();
        } else {
            $sites = Site::where('id', Auth::user()->site_id)->get();
        }

        return view('sites.index', compact('sites'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('sites.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'backuppc_url' => 'required|url',
            'connection_method' => 'required|in:ssh,agent',
            'ssh_host' => 'required_if:connection_method,ssh|nullable|string',
            'ssh_port' => 'required_if:connection_method,ssh|nullable|integer|min:1|max:65535',
            'ssh_username' => 'required_if:connection_method,ssh|nullable|string',
            'ssh_password' => 'required_if:connection_method,ssh|nullable|string',
            'api_key' => 'required_if:connection_method,agent|nullable|string',
            'backuppc_username' => 'nullable|string',
            'backuppc_password' => 'nullable|string',
            'polling_interval' => 'required|integer|min:5|max:1440',
            'is_active' => 'boolean'
        ]);

        $validated['ssh_password'] = $validated['ssh_password'] ? Crypt::encryptString($validated['ssh_password']) : null;
        $validated['api_key'] = $validated['api_key'] ? Crypt::encryptString($validated['api_key']) : null;
        $validated['backuppc_password'] = $validated['backuppc_password'] ? Crypt::encryptString($validated['backuppc_password']) : null;

        Site::create($validated);

        return redirect()->route('sites.index')->with('success', 'Site created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Site $site)
    {
        if (!Auth::user()->isAdmin() && Auth::user()->site_id != $site->id) {
            abort(403, 'Unauthorized access.');
        }

        return view('sites.show', compact('site'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Site $site)
    {
        return view('sites.edit', compact('site'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Site $site)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'backuppc_url' => 'required|url',
            'connection_method' => 'required|in:ssh,agent',
            'ssh_host' => 'required_if:connection_method,ssh|nullable|string',
            'ssh_port' => 'required_if:connection_method,ssh|nullable|integer|min:1|max:65535',
            'ssh_username' => 'required_if:connection_method,ssh|nullable|string',
            'ssh_password' => 'nullable|string',
            'api_key' => 'nullable|string',
            'backuppc_username' => 'nullable|string',
            'backuppc_password' => 'nullable|string',
            'polling_interval' => 'required|integer|min:5|max:1440',
            'is_active' => 'boolean'
        ]);

        if ($request->filled('ssh_password')) {
            $validated['ssh_password'] = Crypt::encryptString($validated['ssh_password']);
        } else {
            unset($validated['ssh_password']);
        }

        if ($request->filled('api_key')) {
            $validated['api_key'] = Crypt::encryptString($validated['api_key']);
        } else {
            unset($validated['api_key']);
        }

        if ($request->filled('backuppc_password')) {
            $validated['backuppc_password'] = Crypt::encryptString($validated['backuppc_password']);
        } else {
            unset($validated['backuppc_password']);
        }

        $site->update($validated);

        return redirect()->route('sites.index')->with('success', 'Site updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Site $site)
    {
        $site->delete();
        return redirect()->route('sites.index')->with('success', 'Site deleted successfully.');
    }

    /**
     * Download backup report PDF for a specific site.
     */
    public function downloadReport(Site $site)
    {
        if (!Auth::user()->isAdmin() && Auth::user()->site_id != $site->id) {
            abort(403, 'Unauthorized access.');
        }

        $backupData = $site->backupData()->where('disabled', false)->get();
        $generatedAt = now()->format('Y-m-d H:i:s');

        // Process backup data for the report
        $processedData = [];
        $healthyClients = 0;
        $warningClients = 0;

        foreach ($backupData as $backup) {
            $fullBackupAge = $this->calculateFullBackupAge($backup);
            $incrementalAge = $this->calculateIncrementalAge($backup);

            // Determine status classes
            $fullBackupAgeClass = $fullBackupAge > 14 ? 'status-danger' : ($fullBackupAge > 7 ? 'status-warning' : 'status-good');
            $incrementalAgeClass = $incrementalAge > 7 ? 'status-danger' : ($incrementalAge > 3 ? 'status-warning' : 'status-good');

            // Determine overall status
            $status = 'Healthy';
            $statusClass = 'status-good';

            if ($fullBackupAge > 14 || $incrementalAge > 7) {
                $status = 'Critical';
                $statusClass = 'status-danger';
                $warningClients++;
            } elseif ($fullBackupAge > 7 || $incrementalAge > 3) {
                $status = 'Warning';
                $statusClass = 'status-warning';
                $warningClients++;
            } else {
                $healthyClients++;
            }

            $processedData[] = [
                'host_name' => $backup->host_name,
                'fullBackupAge' => $fullBackupAge === 0 ? 'N/A' : $fullBackupAge . ' days',
                'fullBackupAgeClass' => $fullBackupAgeClass,
                'incrementalAge' => $incrementalAge === 0 ? 'N/A' : $incrementalAge . ' days',
                'incrementalAgeClass' => $incrementalAgeClass,
                'sizeFormatted' => $this->formatSize($backup->last_backup_size ?? 0),
                'status' => $status,
                'statusClass' => $statusClass,
            ];
        }

        $stats = [
            'totalClients' => count($processedData),
            'healthyClients' => $healthyClients,
            'warningClients' => $warningClients,
        ];

        $pdf = Pdf::loadView('emails.backup-report', [
            'site' => $site,
            'backupData' => [],
            'generatedAt' => $generatedAt,
            'stats' => $stats,
            'processedData' => $processedData
        ]);
        $pdf->setPaper('a4', 'landscape');

        $filename = 'backup-report-' . str_replace(' ', '-', $site->name) . '-' . now()->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Calculate full backup age in days with 1 decimal precision.
     */
    protected function calculateFullBackupAge(BackupData $backup)
    {
        $rawData = $backup->raw_data;
        if (isset($rawData['full_age']) && $rawData['full_age'] > 0) {
            $fullAgeTimestamp = $rawData['full_age'];
            $fullBackupTime = Carbon::createFromTimestamp($fullAgeTimestamp);
            return round($fullBackupTime->diffInHours(now()) / 24, 1);
        }

        return 0;
    }

    /**
     * Calculate incremental backup age in days with 1 decimal precision.
     */
    protected function calculateIncrementalAge(BackupData $backup)
    {
        $rawData = $backup->raw_data;
        if (isset($rawData['incr_age']) && $rawData['incr_age'] > 0) {
            $incrAgeTimestamp = $rawData['incr_age'];
            $incrBackupTime = Carbon::createFromTimestamp($incrAgeTimestamp);
            return round($incrBackupTime->diffInHours(now()) / 24, 1);
        }

        return 0;
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
