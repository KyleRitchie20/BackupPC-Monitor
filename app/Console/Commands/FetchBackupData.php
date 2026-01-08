<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\BackupPCService;
use Illuminate\Console\Command;

class FetchBackupData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:fetch-data {site_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch backup data from BackupPC servers';

    /**
     * Execute the console command.
     */
    public function handle(BackupPCService $backupPCService)
    {
        if ($this->argument('site_id')) {
            // Fetch data for specific site
            $site = Site::find($this->argument('site_id'));
            if (!$site) {
                $this->error('Site not found!');
                return 1;
            }

            $this->info("Fetching data for site: {$site->name}");
            $result = $backupPCService->fetchBackupData($site);

            if ($result) {
                $this->info('Data fetched successfully!');
            } else {
                $this->error('Failed to fetch data.');
            }
        } else {
            // Fetch data for all active sites
            $sites = Site::where('is_active', true)->get();

            if ($sites->isEmpty()) {
                $this->info('No active sites found.');
                return 0;
            }

            $this->info("Fetching data for {$sites->count()} active sites...");

            foreach ($sites as $site) {
                $this->info("Processing site: {$site->name}");
                $result = $backupPCService->fetchBackupData($site);

                if ($result) {
                    $this->info("✓ Successfully fetched data for {$site->name}");
                } else {
                    $this->error("✗ Failed to fetch data for {$site->name}");
                }
            }
        }

        return 0;
    }
}
