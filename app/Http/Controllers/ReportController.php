<?php

namespace App\Http\Controllers;

use App\Models\ReportSchedule;
use App\Models\Site;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    protected ReportService $reportService;

    public function __construct(ReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    /**
     * Show reports hub with date-range selection, interactive preview, and schedule management.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Site scoping: admin sees all; client sees their site
        if ($user->isAdmin()) {
            $sites = Site::all();
        } else {
            if (!$user->site_id) {
                abort(403, 'No site assigned to this client user.');
            }
            $sites = Site::where('id', $user->site_id)->get();
        }

        // Determine selected site(s)
        $selectedSiteId = $request->get('site_id');
        if (!$user->isAdmin()) {
            $targetSiteIds = [$user->site_id];
            $selectedSiteId = $user->site_id;
        } elseif ($selectedSiteId && $selectedSiteId !== 'all') {
            $targetSiteIds = [(int)$selectedSiteId];
        } else {
            $targetSiteIds = $sites->pluck('id')->toArray();
            $selectedSiteId = 'all';
        }

        // Date selection: default to Last 7 Days if not specified
        $datePreset = $request->get('preset', 'last_7_days');
        [$startDate, $endDate] = $this->resolveDates($datePreset, $request->get('start_date'), $request->get('end_date'));

        $statusFilter = $request->get('status', 'all');

        // Generate report data for the interactive preview
        $reportData = $this->reportService->getReportData(
            $targetSiteIds,
            $startDate,
            $endDate,
            ['status_filter' => $statusFilter]
        );

        // Fetch schedules (scoped to user/admin)
        $schedulesQuery = ReportSchedule::with(['site', 'creator'])->orderBy('created_at', 'desc');
        if (!$user->isAdmin()) {
            $schedulesQuery->where('site_id', $user->site_id);
        }
        $schedules = $schedulesQuery->get();

        return view('reports.index', compact(
            'sites',
            'selectedSiteId',
            'datePreset',
            'startDate',
            'endDate',
            'statusFilter',
            'reportData',
            'schedules'
        ));
    }

    /**
     * Download report as PDF with the selected date range.
     */
    public function downloadPdf(Request $request)
    {
        $user = Auth::user();
        $targetSiteIds = $this->resolveSiteIds($user, $request->get('site_id'));
        [$startDate, $endDate] = $this->resolveDates($request->get('preset'), $request->get('start_date'), $request->get('end_date'));

        $reportData = $this->reportService->getReportData(
            $targetSiteIds,
            $startDate,
            $endDate,
            ['status_filter' => $request->get('status', 'all')]
        );

        $pdf = $this->reportService->generatePdf($reportData);
        $filename = 'backup-report-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Export report data as CSV with the selected date range.
     */
    public function exportCsv(Request $request)
    {
        $user = Auth::user();
        $targetSiteIds = $this->resolveSiteIds($user, $request->get('site_id'));
        [$startDate, $endDate] = $this->resolveDates($request->get('preset'), $request->get('start_date'), $request->get('end_date'));

        $reportData = $this->reportService->getReportData(
            $targetSiteIds,
            $startDate,
            $endDate,
            ['status_filter' => $request->get('status', 'all')]
        );

        $csv = $this->reportService->generateCsv($reportData);
        $filename = 'backup-report-' . $startDate->format('Y-m-d') . '-to-' . $endDate->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Email the generated report on demand.
     */
    public function emailReport(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email' => 'required|email',
            'format' => 'required|in:pdf,csv,both',
        ]);

        $targetSiteIds = $this->resolveSiteIds($user, $request->get('site_id'));
        [$startDate, $endDate] = $this->resolveDates($request->get('preset'), $request->get('start_date'), $request->get('end_date'));

        $reportData = $this->reportService->getReportData($targetSiteIds, $startDate, $endDate);

        $success = $this->reportService->sendReportEmail(
            $reportData,
            [$request->email],
            $request->subject,
            $request->format
        );

        if ($success) {
            return back()->with('success', "Report successfully sent to {$request->email}!");
        }

        return back()->with('error', "Failed to send report email. Please check your mail settings.");
    }

    /**
     * Store a new automated recurring report schedule.
     */
    public function storeSchedule(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'site_id' => 'nullable|exists:sites,id',
            'frequency' => 'required|in:daily,weekly,monthly',
            'day_of_week' => 'nullable|integer|between:0,6',
            'day_of_month' => 'nullable|integer|between:1,28',
            'time' => 'required|string',
            'date_range_type' => 'required|in:today,yesterday,last_7_days,last_14_days,last_30_days,this_month,last_month',
            'recipients' => 'required|string', // Comma-separated emails
            'format' => 'required|in:pdf,csv,both',
        ]);

        if (!$user->isAdmin()) {
            $validated['site_id'] = $user->site_id;
        }

        // Convert comma/newline separated recipients into an array
        $emails = array_filter(array_map('trim', preg_split('/[,\n]+/', $validated['recipients'])));
        $validated['recipients'] = array_values($emails);
        $validated['created_by'] = $user->id;

        ReportSchedule::create($validated);

        return back()->with('success', 'Automated report schedule created successfully!');
    }

    /**
     * Trigger an automated report schedule immediately.
     */
    public function runScheduleNow(ReportSchedule $schedule)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && $schedule->site_id !== $user->site_id) {
            abort(403, 'Unauthorized.');
        }

        [$start, $end] = $schedule->resolveDateRange();
        $targetSiteIds = $schedule->site_id ? [$schedule->site_id] : Site::pluck('id')->toArray();

        $reportData = $this->reportService->getReportData($targetSiteIds, $start, $end);
        $this->reportService->sendReportEmail(
            $reportData,
            $schedule->recipients,
            "{$schedule->name} (Manual Trigger) — " . $reportData['date_range_label'],
            $schedule->format
        );

        $schedule->update(['last_run_at' => now()]);

        return back()->with('success', "Schedule '{$schedule->name}' executed and emailed to recipients.");
    }

    /**
     * Toggle active status of a report schedule.
     */
    public function toggleSchedule(ReportSchedule $schedule)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && $schedule->site_id !== $user->site_id) {
            abort(403, 'Unauthorized.');
        }

        $schedule->update(['is_active' => !$schedule->is_active]);

        return back()->with('success', 'Schedule status updated.');
    }

    /**
     * Delete a report schedule.
     */
    public function destroySchedule(ReportSchedule $schedule)
    {
        $user = Auth::user();
        if (!$user->isAdmin() && $schedule->site_id !== $user->site_id) {
            abort(403, 'Unauthorized.');
        }

        $schedule->delete();

        return back()->with('success', 'Schedule deleted.');
    }

    /**
     * Helper to resolve target site IDs based on user permissions and requested site ID.
     */
    protected function resolveSiteIds($user, $siteIdParam): array
    {
        if (!$user->isAdmin()) {
            return [$user->site_id];
        }
        if ($siteIdParam && $siteIdParam !== 'all') {
            return [(int)$siteIdParam];
        }
        return Site::pluck('id')->toArray();
    }

    /**
     * Helper to resolve start and end dates from preset or custom input.
     *
     * @return array [Carbon $startDate, Carbon $endDate]
     */
    protected function resolveDates(?string $preset, ?string $customStart, ?string $customEnd): array
    {
        $now = now();

        if ($preset === 'custom' && $customStart && $customEnd) {
            return [Carbon::parse($customStart), Carbon::parse($customEnd)];
        }

        switch ($preset) {
            case 'today':
                return [$now->copy()->startOfDay(), $now->copy()->endOfDay()];
            case 'yesterday':
                $y = $now->copy()->subDay();
                return [$y->copy()->startOfDay(), $y->copy()->endOfDay()];
            case 'last_14_days':
                return [$now->copy()->subDays(14)->startOfDay(), $now->copy()->endOfDay()];
            case 'last_30_days':
                return [$now->copy()->subDays(30)->startOfDay(), $now->copy()->endOfDay()];
            case 'this_month':
                return [$now->copy()->startOfMonth(), $now->copy()->endOfDay()];
            case 'last_month':
                $lm = $now->copy()->subMonthNoOverflow();
                return [$lm->copy()->startOfMonth(), $lm->copy()->endOfMonth()];
            case 'last_7_days':
            default:
                return [$now->copy()->subDays(7)->startOfDay(), $now->copy()->endOfDay()];
        }
    }
}
