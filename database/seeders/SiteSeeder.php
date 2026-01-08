<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

class SiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo sites
        $site1 = Site::create([
            'name' => 'Demo Site 1',
            'description' => 'First demo site with SSH connection',
            'backuppc_url' => 'http://backuppc-demo-1.local',
            'connection_method' => 'ssh',
            'ssh_host' => 'backuppc-demo-1.local',
            'ssh_port' => 22,
            'ssh_username' => 'backuppc',
            'ssh_password' => Crypt::encryptString('demo_password'),
            'polling_interval' => 30,
            'is_active' => true
        ]);

        $site2 = Site::create([
            'name' => 'Demo Site 2',
            'description' => 'Second demo site with Agent connection',
            'backuppc_url' => 'http://backuppc-demo-2.local',
            'connection_method' => 'agent',
            'api_key' => Crypt::encryptString('demo_api_key_12345'),
            'polling_interval' => 60,
            'is_active' => true
        ]);

        // Assign demo client to first site
        $clientUser = User::where('email', 'client@example.com')->first();
        if ($clientUser) {
            $clientUser->update(['site_id' => $site1->id]);
        }
    }
}
