<?php

namespace Tests\Feature;

use App\Models\BackupData;
use App\Models\BackupRun;
use App\Models\ReportSchedule;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;
    protected Site $siteA;
    protected Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'admin', 'description' => 'Administrator']);
        $clientRole = Role::create(['name' => 'client', 'description' => 'Client']);

        $this->siteA = Site::create([
            'name' => 'Client Site Alpha',
            'backuppc_url' => 'https://alpha.backuppc.local',
            'connection_method' => 'agent',
            'polling_interval' => 30,
            'is_active' => true,
        ]);

        $this->siteB = Site::create([
            'name' => 'Client Site Beta',
            'backuppc_url' => 'https://beta.backuppc.local',
            'connection_method' => 'agent',
            'polling_interval' => 30,
            'is_active' => true,
        ]);

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
        ]);

        $this->client = User::create([
            'name' => 'Client User Alpha',
            'email' => 'client@alpha.com',
            'password' => bcrypt('password'),
            'role_id' => $clientRole->id,
            'site_id' => $this->siteA->id,
        ]);

        // Seed some backup data
        BackupData::create([
            'site_id' => $this->siteA->id,
            'host_name' => 'srv-app01',
            'state' => 'idle',
            'last_backup_time' => now()->subHours(12),
            'last_backup_size' => 10737418240, // 10 GB
            'full_backup_count' => 3,
            'incremental_backup_count' => 14,
            'disabled' => 0,
            'exclude_from_report' => false,
            'raw_data' => [
                'full_age' => now()->subDays(2)->timestamp,
                'incr_age' => now()->subHours(12)->timestamp,
                'full_size' => 10737418240,
            ],
        ]);

        BackupData::create([
            'site_id' => $this->siteB->id,
            'host_name' => 'db-cluster01',
            'state' => 'idle',
            'last_backup_time' => now()->subDays(10),
            'last_backup_size' => 53687091200, // 50 GB
            'full_backup_count' => 1,
            'incremental_backup_count' => 2,
            'disabled' => 0,
            'exclude_from_report' => false,
            'raw_data' => [
                'full_age' => now()->subDays(10)->timestamp,
                'incr_age' => now()->subDays(5)->timestamp,
                'full_size' => 53687091200,
            ],
        ]);

        // Seed some historical backup runs
        BackupRun::create([
            'site_id' => $this->siteA->id,
            'host_name' => 'srv-app01',
            'type' => 'full',
            'completed_at' => now()->subDays(2),
            'backup_size' => 10737418240,
            'duration_seconds' => 3600,
            'status' => 'success',
        ]);

        BackupRun::create([
            'site_id' => $this->siteA->id,
            'host_name' => 'srv-app01',
            'type' => 'incremental',
            'completed_at' => now()->subHours(12),
            'backup_size' => 524288000,
            'duration_seconds' => 300,
            'status' => 'success',
        ]);

        BackupRun::create([
            'site_id' => $this->siteB->id,
            'host_name' => 'db-cluster01',
            'type' => 'full',
            'completed_at' => now()->subDays(10),
            'backup_size' => 53687091200,
            'duration_seconds' => 7200,
            'status' => 'warning',
        ]);
    }

    /** @test */
    public function admin_can_view_reports_page_with_all_sites()
    {
        $response = $this->actingAs($this->admin)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee('Backup Reports & Analytics');
        $response->assertSee('Client Site Alpha');
        $response->assertSee('Client Site Beta');
        $response->assertSee('srv-app01');
        $response->assertSee('db-cluster01');
    }

    /** @test */
    public function client_can_view_reports_scoped_to_their_assigned_site()
    {
        $response = $this->actingAs($this->client)->get(route('reports.index'));

        $response->assertStatus(200);
        $response->assertSee('srv-app01');
        $response->assertDontSee('db-cluster01');
    }

    /** @test */
    public function reports_filter_by_date_range()
    {
        // Query window that only includes the last 3 days (should contain Alpha's runs, but not Beta's 10-day-old run)
        $start = now()->subDays(3)->format('Y-m-d');
        $end = now()->format('Y-m-d');

        $response = $this->actingAs($this->admin)->get(route('reports.index', [
            'preset' => 'custom',
            'start_date' => $start,
            'end_date' => $end,
        ]));

        $response->assertStatus(200);
        $reportData = $response->viewData('reportData');

        $this->assertEquals(2, $reportData['stats']['total_runs']);
        $this->assertEquals(1, $reportData['stats']['full_runs']);
        $this->assertEquals(1, $reportData['stats']['incremental_runs']);
    }

    /** @test */
    public function report_pdf_can_be_downloaded_with_date_selection()
    {
        $response = $this->actingAs($this->admin)->get(route('reports.download-pdf', [
            'preset' => 'last_7_days',
            'site_id' => $this->siteA->id,
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.pdf', $response->headers->get('Content-Disposition'));
    }

    /** @test */
    public function report_csv_can_be_exported_with_date_selection()
    {
        $response = $this->actingAs($this->admin)->get(route('reports.export-csv', [
            'preset' => 'last_7_days',
            'site_id' => 'all',
        ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('srv-app01', $response->getContent());
    }

    /** @test */
    public function admin_can_create_automated_report_schedule()
    {
        $response = $this->actingAs($this->admin)->post(route('reports.schedules.store'), [
            'name' => 'Weekly Alpha SLA',
            'site_id' => $this->siteA->id,
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'time' => '08:00',
            'date_range_type' => 'last_7_days',
            'recipients' => 'manager@alpha.com, tech@msp.com',
            'format' => 'pdf',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('report_schedules', [
            'name' => 'Weekly Alpha SLA',
            'site_id' => $this->siteA->id,
            'frequency' => 'weekly',
            'date_range_type' => 'last_7_days',
        ]);

        $schedule = ReportSchedule::first();
        $this->assertEquals(['manager@alpha.com', 'tech@msp.com'], $schedule->recipients);
    }

    /** @test */
    public function scheduled_reports_command_evaluates_active_schedules()
    {
        ReportSchedule::create([
            'name' => 'Test Nightly Run',
            'site_id' => $this->siteA->id,
            'frequency' => 'daily',
            'time' => '00:00',
            'date_range_type' => 'yesterday',
            'recipients' => ['alerts@example.com'],
            'format' => 'pdf',
            'is_active' => true,
        ]);

        $this->artisan('reports:send-scheduled', ['--force' => true])
            ->expectsOutputToContain('Running schedule')
            ->expectsOutputToContain('Completed scheduled reports evaluation')
            ->assertExitCode(0);

        $schedule = ReportSchedule::first();
        $this->assertNotNull($schedule->last_run_at);
    }
}
