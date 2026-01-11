<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Site;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;

class ClientUserController extends Controller
{
    // Middleware is applied in routes/web.php

    /**
     * Display a listing of client users
     */
    public function index()
    {
        $clients = User::whereHas('role', function($query) {
            $query->where('name', 'client');
        })->with('site')->get();

        $sites = Site::all();

        return view('client-users.index', compact('clients', 'sites'));
    }

    /**
     * Show the form for creating a new client user
     */
    public function create()
    {
        $sites = Site::all();
        return view('client-users.create', compact('sites'));
    }

    /**
     * Store a newly created client user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'site_id' => 'required|exists:sites,id'
        ]);

        $clientRole = Role::where('name', 'client')->first();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $clientRole->id,
            'site_id' => $validated['site_id']
        ]);

        return redirect()->route('client-users.index')->with('success', 'Client user created successfully.');
    }

    /**
     * Show the form for editing a client user
     */
    public function edit(User $client_user)
    {
        $sites = Site::all();
        return view('client-users.edit', compact('client_user', 'sites'));
    }

    /**
     * Update a client user
     */
    public function update(Request $request, User $client_user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$client_user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'site_id' => 'required|exists:sites,id'
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'site_id' => $validated['site_id']
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $client_user->update($updateData);

        return redirect()->route('client-users.index')->with('success', 'Client user updated successfully.');
    }

    /**
     * Remove a client user
     */
    public function destroy(User $client_user)
    {
        $client_user->delete();
        return redirect()->route('client-users.index')->with('success', 'Client user deleted successfully.');
    }

    /**
     * Send a test backup report to a specific client user
     */
    public function sendTestReport(User $client_user)
    {
        // Only allow admins to send test reports
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can send test reports.');
        }

        // Check if client user has a site assigned
        if (!$client_user->site) {
            return redirect()->back()->with('error', 'Client user must be assigned to a site to receive test reports.');
        }

        try {
            // Generate PDF for the client's site
            $pdf = $this->generatePdfForSite($client_user->site);
            $filename = "test-backup-report-{$client_user->site->name}-" . now()->format('Y-m-d') . '.pdf';
            $path = storage_path("reports/{$filename}");

            // Ensure directory exists
            $directory = storage_path('reports');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save PDF to storage
            file_put_contents($path, $pdf);

            // Send email to the specific client user
            $this->sendTestReportEmail($client_user, $client_user->site, $path);

            // Clean up the temporary file
            if (file_exists($path)) {
                unlink($path);
            }

            return redirect()->back()->with('success', 'Test report sent successfully to ' . $client_user->email);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to send test report: ' . $e->getMessage());
        }
    }

    /**
     * Generate PDF report for a specific site (extracted from GenerateBackupReport command)
     */
    protected function generatePdfForSite(Site $site)
    {
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

        $pdf = Pdf::loadView('emails.backup-report', compact('site', 'backupData', 'generatedAt', 'stats', 'processedData'));

        return $pdf->output();
    }

    /**
     * Calculate full backup age in days with 1 decimal precision.
     */
    protected function calculateFullBackupAge($backup)
    {
        if (!$backup->last_backup_time || !$backup->full_backup_count || $backup->full_backup_count == 0) {
            return 0;
        }

        // Full backup time is stored in raw_data as full_backup_time
        $rawData = $backup->raw_data;
        if (isset($rawData['fullBackupTime']) && $rawData['fullBackupTime']) {
            $fullBackupTime = \Carbon\Carbon::parse($rawData['fullBackupTime']);
            return round($fullBackupTime->diffInHours(now()) / 24, 1);
        }

        return 0;
    }

    /**
     * Calculate incremental backup age in days with 1 decimal precision.
     */
    protected function calculateIncrementalAge($backup)
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

    /**
     * Send test report email to a specific client user.
     */
    protected function sendTestReportEmail(User $user, Site $site, $pdfPath)
    {
        Mail::send([], [], function ($message) use ($user, $site, $pdfPath) {
            $message->to($user->email)
                ->subject("Test Backup Report - {$site->name} - " . now()->format('Y-m-d'))
                ->attach($pdfPath)
                ->setBody("
                    <html>
                    <body>
                        <p>Hi {$user->name},</p>
                        <p>This is a test backup report for {$site->name}. Please find the report attached.</p>
                        <p>This report was generated on: " . now()->format('Y-m-d H:i:s') . "</p>
                        <p>Best regards,<br>BackupPC Monitor</p>
                    </body>
                    </html>
                ", 'text/html');
        });
    }

    /**
     * Show client management dashboard
     */
    public function dashboard()
    {
        $clients = User::whereHas('role', function($query) {
            $query->where('name', 'client');
        })->with('site')->get();

        $sites = Site::all();

        // Get statistics
        $totalClients = $clients->count();
        $totalSites = $sites->count();
        $clientsWithSites = $clients->whereNotNull('site_id')->count();

        return view('client-users.dashboard', compact('clients', 'sites', 'totalClients', 'totalSites', 'clientsWithSites'));
    }
}
