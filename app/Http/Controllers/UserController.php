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

class UserController extends Controller
{
    /**
     * Display a listing of admin users
     */
    public function index()
    {
        $users = User::whereHas('role', function($query) {
            $query->where('name', 'admin');
        })->with(['role', 'site'])->get();

        $sites = Site::all();
        $roles = Role::where('name', 'admin')->get(); // Only admin role for this interface

        return view('users.index', compact('users', 'sites', 'roles'));
    }

    /**
     * Show the form for creating a new user
     */
    public function create()
    {
        $sites = Site::all();
        $roles = Role::all();
        return view('users.create', compact('sites', 'roles'));
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'site_id' => 'nullable|exists:sites,id'
        ]);

        // Ensure only admins can create admin users
        if ($validated['role_id'] == Role::where('name', 'admin')->first()->id && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can create admin users.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id' => $validated['role_id'],
            'site_id' => $validated['site_id']
        ]);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user
     */
    public function show(User $user)
    {
        return view('users.show', compact('user'));
    }

    /**
     * Show the form for editing a user
     */
    public function edit(User $user)
    {
        // Prevent non-admins from editing admin users
        if ($user->isAdmin() && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can edit admin users.');
        }

        $sites = Site::all();
        $roles = Role::all();
        return view('users.edit', compact('user', 'sites', 'roles'));
    }

    /**
     * Update a user
     */
    public function update(Request $request, User $user)
    {
        // Prevent non-admins from editing admin users
        if ($user->isAdmin() && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can edit admin users.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'site_id' => 'nullable|exists:sites,id'
        ]);

        // Ensure only admins can update users to admin role
        if ($validated['role_id'] == Role::where('name', 'admin')->first()->id && !Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can assign admin role.');
        }

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $validated['role_id'],
            'site_id' => $validated['site_id']
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Send a test backup report to a specific user
     */
    public function sendTestReport(User $user)
    {
        // Only allow admins to send test reports
        if (!Auth::user()->isAdmin()) {
            abort(403, 'Only administrators can send test reports.');
        }

        // Check if user has a site assigned
        if (!$user->site) {
            return redirect()->back()->with('error', 'User must be assigned to a site to receive test reports.');
        }

        try {
            // Generate PDF for the user's site
            $pdf = $this->generatePdfForSite($user->site);
            $filename = "test-backup-report-{$user->site->name}-" . now()->format('Y-m-d') . '.pdf';
            $path = storage_path("reports/{$filename}");

            // Ensure directory exists
            $directory = storage_path('reports');
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Save PDF to storage
            file_put_contents($path, $pdf);

            // Send email to the specific user
            $this->sendTestReportEmail($user, $user->site, $path);

            // Clean up the temporary file
            if (file_exists($path)) {
                unlink($path);
            }

            return redirect()->back()->with('success', 'Test report sent successfully to ' . $user->email);

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
     * Send test report email to a specific user.
     */
    protected function sendTestReportEmail(User $user, Site $site, $pdfPath)
    {
        $htmlContent = "
            <html>
            <body>
                <p>Hi {$user->name},</p>
                <p>This is a test backup report for {$site->name}. Please find the report attached.</p>
                <p>This report was generated on: " . now()->format('Y-m-d H:i:s') . "</p>
                <p>Best regards,<br>BackupPC Monitor</p>
            </body>
            </html>
        ";

        Mail::raw($htmlContent, function ($message) use ($user, $site, $pdfPath) {
            $message->to($user->email)
                ->subject("Test Backup Report - {$site->name} - " . now()->format('Y-m-d'))
                ->attach($pdfPath);
        });
    }

    /**
     * Remove a user
     */
    public function destroy(User $user)
    {
        // Prevent users from deleting themselves or other admins if not admin
        if (($user->isAdmin() && !Auth::user()->isAdmin()) || $user->id === Auth::id()) {
            abort(403, 'You cannot delete this user.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
