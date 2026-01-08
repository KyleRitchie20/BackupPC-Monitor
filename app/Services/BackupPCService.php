<?php

namespace App\Services;

use App\Models\Site;
use App\Models\BackupData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BackupPCService
{
    /**
     * Fetch backup data from BackupPC server using the appropriate connection method
     */
    public function fetchBackupData(Site $site)
    {
        try {
            Log::info('Starting backup data fetch for site: ' . $site->name . ' (ID: ' . $site->id . ')');
            Log::info('Connection method: ' . $site->connection_method);
            Log::info('BackupPC URL: ' . $site->backuppc_url);

            if ($site->connection_method === 'ssh') {
                Log::info('Using SSH connection for site: ' . $site->name);
                return $this->fetchDataViaSSH($site);
            } else {
                Log::info('Using agent connection for site: ' . $site->name);
                return $this->fetchDataViaAgent($site);
            }
        } catch (\Exception $e) {
            Log::error('Failed to fetch backup data for site ' . $site->id . ': ' . $e->getMessage());
            Log::error('Exception trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Fetch data via SSH tunnel
     */
    protected function fetchDataViaSSH(Site $site)
    {
        Log::info('Attempting SSH connection to: ' . $site->ssh_host . ':' . $site->ssh_port);
        Log::info('SSH username: ' . $site->ssh_username);

        // Decrypt SSH password
        $sshPassword = $site->ssh_password ? Crypt::decryptString($site->ssh_password) : null;
        Log::info('SSH password decrypted successfully');

        // Extract host from BackupPC URL
        $backupHost = parse_url($site->backuppc_url, PHP_URL_HOST);
        Log::info('BackupPC host extracted: ' . $backupHost);

        if (!$backupHost) {
            Log::error('Failed to extract host from BackupPC URL: ' . $site->backuppc_url);
            return null;
        }

        // Try alternative SSH connection methods
        $methods = [
            'sshpass' => sprintf(
                'sshpass -p "%s" ssh -o StrictHostKeyChecking=no -L 8080:%s:80 %s@%s -p %d -N',
                addslashes($sshPassword),
                $backupHost,
                $site->ssh_username,
                $site->ssh_host,
                $site->ssh_port
            ),
            'expect' => sprintf(
                'expect -c "spawn ssh -o StrictHostKeyChecking=no -L 8080:%s:80 %s@%s -p %d -N; expect \"password:\"; send \"%s\r\"; interact"',
                $backupHost,
                $site->ssh_username,
                $site->ssh_host,
                $site->ssh_port,
                addslashes($sshPassword)
            ),
            'direct' => sprintf(
                'ssh -o StrictHostKeyChecking=no -L 8080:%s:80 %s@%s -p %d -N',
                $backupHost,
                $site->ssh_username,
                $site->ssh_host,
                $site->ssh_port
            )
        ];

        $tunnelProcess = null;

        foreach ($methods as $method => $sshCommand) {
            Log::info("Trying SSH method: $method");
            Log::info("SSH command: $sshCommand");

            try {
                $process = new Process(explode(' ', $sshCommand));
                $process->start();

                // Wait for tunnel to establish
                sleep(3);

                if ($process->isRunning()) {
                    Log::info("SSH tunnel established using method: $method");
                    $tunnelProcess = $process;
                    break;
                } else {
                    Log::error("SSH method $method failed to start");
                    Log::error("Output: " . $process->getOutput());
                    Log::error("Error: " . $process->getErrorOutput());
                }
            } catch (\Exception $e) {
                Log::error("SSH method $method failed: " . $e->getMessage());
            }
        }

        if (!$tunnelProcess) {
            Log::error('All SSH tunnel methods failed');
            return null;
        }

        try {
            // Get BackupPC credentials for basic auth
            $backuppcUsername = $site->backuppc_username;
            $backuppcPassword = $site->backuppc_password ? Crypt::decryptString($site->backuppc_password) : null;

            // Make API request through the tunnel with increased timeout
            $request = Http::timeout(60);

            // Add basic auth if credentials are provided
            if ($backuppcUsername && $backuppcPassword) {
                Log::info('Using BackupPC basic authentication through SSH tunnel');
                $request->withBasicAuth($backuppcUsername, $backuppcPassword);
            }

            $response = $request->get('http://localhost:8080/BackupPC/BackupPC_Admin?action=metrics&format=json');

            Log::info('API request completed with status: ' . $response->status());

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Successfully fetched backup data for site: ' . $site->name);
                $this->storeBackupData($site, $data);
                return $data;
            } else {
                Log::error('BackupPC API request failed with status: ' . $response->status());
                Log::error('API response body: ' . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('SSH tunnel request failed: ' . $e->getMessage());
            Log::error('Exception details: ' . $e->getTraceAsString());

            // Try direct API connection as fallback
            Log::info('Attempting direct API connection as fallback');
            return $this->fetchDataViaAgent($site);
        } finally {
            // Terminate the SSH tunnel if it was established
            if ($tunnelProcess) {
                Log::info('Terminating SSH tunnel');
                $tunnelProcess->stop();
                $tunnelProcess->wait();
                Log::info('SSH tunnel terminated');
            }
        }
    }

    /**
     * Fetch data via polling agent
     */
    protected function fetchDataViaAgent(Site $site)
    {
        // Decrypt API key
        $apiKey = $site->api_key ? Crypt::decryptString($site->api_key) : null;
        $backuppcUsername = $site->backuppc_username;
        $backuppcPassword = $site->backuppc_password ? Crypt::decryptString($site->backuppc_password) : null;

        Log::info('Agent connection attempt to: ' . $site->backuppc_url);
        Log::info('Using API key: ' . ($apiKey ? 'yes' : 'no'));
        Log::info('Using BackupPC credentials: ' . ($backuppcUsername ? 'yes' : 'no'));

        try {
            $request = Http::timeout(30)
                ->withHeaders([
                    'Accept' => 'application/json'
                ]);

            // Add authentication if credentials are provided
            if ($backuppcUsername && $backuppcPassword) {
                Log::info('Using BackupPC basic authentication');
                $request->withBasicAuth($backuppcUsername, $backuppcPassword);
            } elseif ($apiKey) {
                Log::info('Using API key authentication');
                $request->withHeaders(['Authorization' => 'Bearer ' . $apiKey]);
            } else {
                Log::warning('No authentication credentials provided for agent connection');
            }

            $response = $request->get($site->backuppc_url . '/BackupPC/BackupPC_Admin?action=metrics&format=json');

            Log::info('Agent API request completed with status: ' . $response->status());

            if ($response->successful()) {
                $data = $response->json();
                Log::info('Successfully fetched backup data via agent for site: ' . $site->name);
                $this->storeBackupData($site, $data);
                return $data;
            } else {
                Log::error('BackupPC API request failed with status: ' . $response->status());
                Log::error('API response body: ' . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Agent API request failed: ' . $e->getMessage());
            Log::error('Exception details: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Store backup data in database
     */
    protected function storeBackupData(Site $site, array $data)
    {
        // Clear old data for this site
        BackupData::where('site_id', $site->id)->delete();

        // Store server data
        if (isset($data['server'])) {
            BackupData::create([
                'site_id' => $site->id,
                'host_name' => 'server',
                'state' => 'server',
                'last_backup_time' => now(),
                'raw_data' => $data['server']
            ]);
        }

        // Store host data
        if (isset($data['hosts']) && is_array($data['hosts'])) {
            foreach ($data['hosts'] as $hostName => $hostData) {
                BackupData::create([
                    'site_id' => $site->id,
                    'host_name' => $hostName,
                    'state' => $hostData['state'] ?? 'unknown',
                    'last_backup_time' => isset($hostData['full_age']) && $hostData['full_age'] > 0 ? date('Y-m-d H:i:s', $hostData['full_age']) : null,
                    'last_backup_size' => $hostData['full_size'] ?? null,
                    'full_backup_count' => $hostData['full_count'] ?? 0,
                    'incremental_backup_count' => $hostData['incr_count'] ?? 0,
                    'error_message' => $hostData['error'] ?? null,
                    'disabled' => $hostData['disabled'] ?? 0,
                    'raw_data' => $hostData
                ]);
            }
        }

        // Store disk and pool data
        if (isset($data['disk'])) {
            BackupData::create([
                'site_id' => $site->id,
                'host_name' => 'disk_usage',
                'state' => 'disk',
                'raw_data' => $data['disk']
            ]);
        }

        if (isset($data['cpool'])) {
            BackupData::create([
                'site_id' => $site->id,
                'host_name' => 'cpool',
                'state' => 'pool',
                'raw_data' => $data['cpool']
            ]);
        }
    }

    /**
     * Get cached backup data for a site
     */
    public function getCachedBackupData(Site $site)
    {
        return BackupData::where('site_id', $site->id)
            ->orderBy('host_name')
            ->get();
    }

    /**
     * Get backup status summary for a site
     */
    public function getBackupStatusSummary(Site $site)
    {
        $backupData = $this->getCachedBackupData($site);

        $summary = [
            'total_hosts' => 0,
            'successful_backups' => 0,
            'failed_backups' => 0,
            'idle_hosts' => 0,
            'backup_in_progress' => 0,
            'total_backup_size' => 0,
            'disk_usage' => 0,
            'hosts' => []
        ];

        foreach ($backupData as $data) {
            if ($data->host_name === 'server' || $data->host_name === 'disk_usage' || $data->host_name === 'cpool') {
                continue;
            }

            // Skip hosts where backups are disabled (check both disabled field and raw_data)
            $isDisabled = $data->disabled ?? 0;
            if ($isDisabled === 0 && $data->raw_data && isset($data->raw_data['disabled'])) {
                $isDisabled = $data->raw_data['disabled'];
            }
            
            if ($isDisabled > 0) {
                continue;
            }

            $summary['total_hosts']++;

            if ($data->state === 'Status_backup_in_progress') {
                $summary['backup_in_progress']++;
            } elseif ($data->state === 'Status_idle') {
                $summary['idle_hosts']++;
            } elseif ($data->state === 'Status_idle' && $data->error_message) {
                $summary['failed_backups']++;
            } else {
                $summary['successful_backups']++;
            }

            $summary['total_backup_size'] += $data->last_backup_size ?? 0;

            $summary['hosts'][] = [
                'name' => $data->host_name,
                'state' => $data->state,
                'last_backup' => $data->last_backup_time,
                'error' => $data->error_message
            ];
        }

        // Get disk usage if available
        $diskData = $backupData->where('host_name', 'disk_usage')->first();
        if ($diskData) {
            $summary['disk_usage'] = $diskData->raw_data['usage'] ?? 0;
        }

        return $summary;
    }

    /**
     * Check if a host has backups disabled
     * BackupPC returns "Status_backup_disabled" for hosts where backups are disabled
     */
    protected function isBackupDisabled(string $state): bool
    {
        return str_contains($state, 'backup_disabled');
    }
}
