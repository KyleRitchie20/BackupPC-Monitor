<?php

namespace App\Services;

use App\Models\Site;
use App\Models\BackupData;
use App\Models\BackupRun;
use Carbon\Carbon;
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
     * Get an available local TCP port for port forwarding
     */
    protected function getAvailableLocalPort(int $default = 18080): int
    {
        $sock = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($sock && @socket_bind($sock, '127.0.0.1', 0)) {
            @socket_getsockname($sock, $addr, $port);
            @socket_close($sock);
            return (int) $port;
        }
        return $default;
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

        // Extract host and port from BackupPC URL
        $backupHost = parse_url($site->backuppc_url, PHP_URL_HOST);
        $backupPort = parse_url($site->backuppc_url, PHP_URL_PORT) ?: 80;
        Log::info('BackupPC host extracted: ' . $backupHost . ' (port ' . $backupPort . ')');

        if (!$backupHost) {
            Log::error('Failed to extract host from BackupPC URL: ' . $site->backuppc_url);
            return null;
        }

        $localPort = $this->getAvailableLocalPort();
        Log::info("Using local tunnel port: {$localPort}");

        // Build connection methods
        $methods = [];

        // 1. Password-based authentication via sshpass if password is provided
        if (!empty($sshPassword)) {
            $methods[] = [
                'name' => 'sshpass',
                'command' => [
                    'sshpass', '-e',
                    'ssh',
                    '-o', 'BatchMode=no',
                    '-o', 'StrictHostKeyChecking=no',
                    '-o', 'UserKnownHostsFile=/dev/null',
                    '-L', "{$localPort}:{$backupHost}:{$backupPort}",
                    "{$site->ssh_username}@{$site->ssh_host}",
                    '-p', (string) $site->ssh_port,
                    '-N'
                ],
                'env' => ['SSHPASS' => $sshPassword]
            ];
        }

        // 2. Key-based authentication (Strictly BatchMode=yes to avoid hanging on terminal password prompts)
        $methods[] = [
            'name' => 'key-based',
            'command' => [
                'ssh',
                '-o', 'BatchMode=yes',
                '-o', 'StrictHostKeyChecking=no',
                '-o', 'UserKnownHostsFile=/dev/null',
                '-L', "{$localPort}:{$backupHost}:{$backupPort}",
                "{$site->ssh_username}@{$site->ssh_host}",
                '-p', (string) $site->ssh_port,
                '-N'
            ],
            'env' => []
        ];

        $tunnelProcess = null;

        foreach ($methods as $methodConfig) {
            $method = $methodConfig['name'];
            Log::info("Trying SSH method: $method");

            try {
                $process = new Process($methodConfig['command'], null, $methodConfig['env']);
                $process->start();

                // Wait up to 3 seconds for tunnel to be established
                $established = false;
                for ($attempt = 0; $attempt < 6; $attempt++) {
                    usleep(500000); // 500ms
                    if (!$process->isRunning()) {
                        break;
                    }
                    $fp = @fsockopen('127.0.0.1', $localPort, $errno, $errstr, 0.5);
                    if ($fp) {
                        fclose($fp);
                        $established = true;
                        break;
                    }
                }

                if ($established && $process->isRunning()) {
                    Log::info("SSH tunnel established using method: $method on port $localPort");
                    $tunnelProcess = $process;
                    break;
                } else {
                    Log::error("SSH method $method failed to establish tunnel");
                    if ($process->isRunning()) {
                        $process->stop();
                    }
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

            $response = $request->get("http://localhost:{$localPort}/BackupPC/BackupPC_Admin?action=metrics&format=json");

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
     * Store backup data in database (upserts state and tracks historical runs)
     */
    public function storeBackupData(Site $site, array $data)
    {
        // 1. Store/update server data
        if (isset($data['server'])) {
            BackupData::updateOrCreate(
                ['site_id' => $site->id, 'host_name' => 'server'],
                [
                    'state' => 'server',
                    'last_backup_time' => now(),
                    'raw_data' => $data['server']
                ]
            );
        }

        // 2. Store/update host data and record historical runs
        if (isset($data['hosts']) && is_array($data['hosts'])) {
            foreach ($data['hosts'] as $hostName => $hostData) {
                // Preserve user-configured settings if host already exists
                $existing = BackupData::where('site_id', $site->id)
                    ->where('host_name', $hostName)
                    ->first();

                $excludeFromReport = $existing ? (bool)$existing->exclude_from_report : false;
                $disabled = $existing ? (int)$existing->disabled : ($hostData['disabled'] ?? 0);
                if (isset($hostData['disabled'])) {
                    $disabled = (int)$hostData['disabled'];
                }

                $fullAge = isset($hostData['full_age']) && $hostData['full_age'] > 0 ? (int)$hostData['full_age'] : null;
                $incrAge = isset($hostData['incr_age']) && $hostData['incr_age'] > 0 ? (int)$hostData['incr_age'] : null;

                $lastBackupTime = $fullAge ? date('Y-m-d H:i:s', $fullAge) : null;
                if ($incrAge && (!$fullAge || $incrAge > $fullAge)) {
                    $lastBackupTime = date('Y-m-d H:i:s', $incrAge);
                }

                BackupData::updateOrCreate(
                    ['site_id' => $site->id, 'host_name' => $hostName],
                    [
                        'state' => $hostData['state'] ?? 'unknown',
                        'last_backup_time' => $lastBackupTime,
                        'last_backup_size' => $hostData['full_size'] ?? null,
                        'full_backup_count' => $hostData['full_count'] ?? 0,
                        'incremental_backup_count' => $hostData['incr_count'] ?? 0,
                        'error_message' => $hostData['error'] ?? null,
                        'disabled' => $disabled,
                        'exclude_from_report' => $excludeFromReport,
                        'raw_data' => $hostData
                    ]
                );

                // --- RECORD HISTORICAL BACKUP RUNS ---
                // Full backup run
                if ($fullAge) {
                    $fullCompletedAt = Carbon::createFromTimestamp($fullAge);
                    $fullDuration = isset($hostData['full_dur']) && $hostData['full_dur'] > 0 ? (int)$hostData['full_dur'] : null;
                    $fullStartedAt = ($fullDuration && $fullCompletedAt) ? $fullCompletedAt->copy()->subSeconds($fullDuration) : null;
                    $fullStatus = !empty($hostData['error']) ? 'warning' : 'success';

                    BackupRun::updateOrCreate(
                        [
                            'site_id' => $site->id,
                            'host_name' => $hostName,
                            'type' => 'full',
                            'completed_at' => $fullCompletedAt,
                        ],
                        [
                            'started_at' => $fullStartedAt,
                            'duration_seconds' => $fullDuration,
                            'backup_size' => $hostData['full_size'] ?? null,
                            'error_message' => $hostData['error'] ?? null,
                            'status' => $fullStatus,
                            'raw_data' => $hostData,
                        ]
                    );
                }

                // Incremental backup run
                if ($incrAge) {
                    $incrCompletedAt = Carbon::createFromTimestamp($incrAge);
                    BackupRun::updateOrCreate(
                        [
                            'site_id' => $site->id,
                            'host_name' => $hostName,
                            'type' => 'incremental',
                            'completed_at' => $incrCompletedAt,
                        ],
                        [
                            'status' => 'success',
                            'raw_data' => $hostData,
                        ]
                    );
                }

                // Record backups list if detailed array is present
                if (isset($hostData['backups']) && is_array($hostData['backups'])) {
                    foreach ($hostData['backups'] as $b) {
                        $bTime = $b['endTime'] ?? $b['startTime'] ?? null;
                        if (!$bTime) continue;
                        $bCompleted = is_numeric($bTime) ? Carbon::createFromTimestamp($bTime) : Carbon::parse($bTime);
                        $bType = (isset($b['type']) && strtolower($b['type']) === 'full') ? 'full' : 'incremental';
                        $bStarted = isset($b['startTime']) ? (is_numeric($b['startTime']) ? Carbon::createFromTimestamp($b['startTime']) : Carbon::parse($b['startTime'])) : null;
                        $bDuration = ($bStarted && $bCompleted) ? $bCompleted->diffInSeconds($bStarted) : null;

                        BackupRun::updateOrCreate(
                            [
                                'site_id' => $site->id,
                                'host_name' => $hostName,
                                'type' => $bType,
                                'completed_at' => $bCompleted,
                            ],
                            [
                                'backup_num' => $b['num'] ?? null,
                                'started_at' => $bStarted,
                                'duration_seconds' => $bDuration,
                                'backup_size' => $b['size'] ?? null,
                                'error_count' => $b['errorCnt'] ?? 0,
                                'error_message' => $b['error'] ?? null,
                                'status' => (!empty($b['errorCnt']) && $b['errorCnt'] > 0) ? 'warning' : 'success',
                                'raw_data' => $b,
                            ]
                        );
                    }
                }
            }
        }

        // 3. Store disk and pool data
        if (isset($data['disk'])) {
            BackupData::updateOrCreate(
                ['site_id' => $site->id, 'host_name' => 'disk_usage'],
                ['state' => 'disk', 'raw_data' => $data['disk']]
            );
        }

        if (isset($data['cpool'])) {
            BackupData::updateOrCreate(
                ['site_id' => $site->id, 'host_name' => 'cpool'],
                ['state' => 'pool', 'raw_data' => $data['cpool']]
            );
        }

        $site->updateAgentContact();
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

            if ($data->error_message) {
                $summary['failed_backups']++;
            } elseif ($data->state === 'Status_backup_in_progress') {
                $summary['backup_in_progress']++;
            } elseif ($data->state === 'Status_idle') {
                $summary['successful_backups']++;
                $summary['idle_hosts']++;
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
