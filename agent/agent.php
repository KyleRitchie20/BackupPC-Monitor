#!/usr/bin/env php
<?php
/**
 * BackupPC Monitor Agent
 *
 * This agent runs on each BackupPC server and pushes real-time data
 * to the central dashboard via HTTP/WebSocket.
 *
 * Usage:
 *   php agent.php --site-id=1 --agent-token=xxx --dashboard-url=http://dashboard.example.com
 *
 * Configuration:
 *   - Can be configured via command line arguments or config.php file
 *   - Supports polling BackupPC CGI API for metrics
 *   - Sends data to central dashboard for real-time updates
 */

declare(strict_types=1);

// Configuration
$CONFIG = [
    // Dashboard configuration
    'dashboard_url' => getenv('DASHBOARD_URL') ?: 'http://localhost:8000',
    'site_id' => (int)(getenv('SITE_ID') ?: '0'),
    'agent_token' => getenv('AGENT_TOKEN') ?: '',

    // BackupPC configuration
    'backuppc_url' => getenv('BACKUPPC_URL') ?: 'http://localhost/BackupPC',
    'backuppc_username' => getenv('BACKUPPC_USERNAME') ?: '',
    'backuppc_password' => getenv('BACKUPPC_PASSWORD') ?: '',
    'api_key' => getenv('BACKUPPC_API_KEY') ?: '',

    // Agent configuration
    'polling_interval' => (int)(getenv('POLLING_INTERVAL') ?: '60'),  // seconds
    'log_file' => getenv('LOG_FILE') ?: '/var/log/backuppc-monitor-agent.log',
    'pid_file' => getenv('PID_FILE') ?: '/var/run/backuppc-monitor-agent.pid',
    'heartbeat_interval' => (int)(getenv('HEARTBEAT_INTERVAL') ?: '300'), // seconds
];

// Parse command line arguments
$OPTIONS = getopt('', [
    'site-id:',
    'agent-token:',
    'dashboard-url:',
    'backuppc-url:',
    'username:',
    'password:',
    'api-key:',
    'interval:',
    'log:',
    'help'
]);

if (isset($OPTIONS['help'])) {
    echo <<<HELP
BackupPC Monitor Agent
======================

This agent monitors a BackupPC server and sends real-time data
to the central BackupPC Monitor dashboard.

Usage:
    php agent.php [OPTIONS]

Options:
    --site-id           Site ID in the dashboard
    --agent-token       Agent authentication token
    --dashboard-url     URL of the central dashboard
    --backuppc-url      URL to BackupPC server (default: http://localhost/BackupPC)
    --username          BackupPC username for authentication
    --password          BackupPC password for authentication
    --api-key           BackupPC API key (alternative to username/password)
    --interval          Polling interval in seconds (default: 60)
    --log               Path to log file

Environment Variables:
    SITE_ID             Site ID in the dashboard
    AGENT_TOKEN         Agent authentication token
    DASHBOARD_URL       URL of the central dashboard
    BACKUPPC_URL        URL to BackupPC server
    BACKUPPC_USERNAME   BackupPC username
    BACKUPPC_PASSWORD   BackupPC password
    BACKUPPC_API_KEY    BackupPC API key
    POLLING_INTERVAL    Polling interval in seconds
    LOG_FILE            Path to log file

Example:
    php agent.php --site-id=1 --agent-token=abc123 --dashboard-url=https://monitor.example.com

Installation:
    1. Copy this script to your BackupPC server
    2. Configure with your dashboard URL and site credentials
    3. Run as a systemd service or cron job
    4. See README.md for detailed setup instructions

HELP;
    exit(0);
}

// Override config with command line options
if (!empty($OPTIONS['site-id'])) $CONFIG['site_id'] = (int)$OPTIONS['site-id'];
if (!empty($OPTIONS['agent-token'])) $CONFIG['agent_token'] = $OPTIONS['agent-token'];
if (!empty($OPTIONS['dashboard-url'])) $CONFIG['dashboard_url'] = $OPTIONS['dashboard-url'];
if (!empty($OPTIONS['backuppc-url'])) $CONFIG['backuppc_url'] = $OPTIONS['backuppc-url'];
if (!empty($OPTIONS['username'])) $CONFIG['backuppc_username'] = $OPTIONS['username'];
if (!empty($OPTIONS['password'])) $CONFIG['backuppc_password'] = $OPTIONS['password'];
if (!empty($OPTIONS['api-key'])) $CONFIG['api_key'] = $OPTIONS['api-key'];
if (!empty($OPTIONS['interval'])) $CONFIG['polling_interval'] = (int)$OPTIONS['interval'];
if (!empty($OPTIONS['log'])) $CONFIG['log_file'] = $OPTIONS['log'];

// Validate required configuration
if (empty($CONFIG['site_id']) || empty($CONFIG['agent_token'])) {
    fwrite(STDERR, "ERROR: Site ID and Agent Token are required.\n");
    fwrite(STDERR, "Use --help for usage information.\n");
    exit(1);
}

/**
 * Logger class for the agent
 */
class AgentLogger
{
    private string $logFile;
    private bool $initialized = false;

    public function __construct(string $logFile)
    {
        $this->logFile = $logFile;
    }

    private function init(): void
    {
        if ($this->initialized) return;

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $this->initialized = true;
    }

    public function log(string $level, string $message, array $context = []): void
    {
        $this->init();

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logLine = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        @file_put_contents($this->logFile, $logLine, FILE_APPEND | LOCK_EX);

        // Also output to stdout for debugging
        if ($level === 'ERROR' || $level === 'WARNING') {
            fwrite(STDERR, $logLine);
        } else {
            echo $logLine;
        }
    }

    public function info(string $message, array $context = []): void { $this->log('INFO', $message, $context); }
    public function error(string $message, array $context = []): void { $this->log('ERROR', $message, $context); }
    public function warning(string $message, array $context = []): void { $this->log('WARNING', $message, $context); }
    public function debug(string $message, array $context = []): void { $this->log('DEBUG', $message, $context); }
}

/**
 * BackupPC API Client
 */
class BackupPCClient
{
    private string $baseUrl;
    private ?string $username;
    private ?string $password;
    private ?string $apiKey;
    private AgentLogger $logger;

    public function __construct(array $config, AgentLogger $logger)
    {
        $this->baseUrl = rtrim($config['backuppc_url'], '/');
        $this->username = $config['backuppc_username'] ?: null;
        $this->password = $config['backuppc_password'] ?: null;
        $this->apiKey = $config['api_key'] ?: null;
        $this->logger = $logger;
    }

    /**
     * Fetch metrics from BackupPC
     */
    public function fetchMetrics(): ?array
    {
        $url = $this->baseUrl . '/BackupPC_Admin?action=metrics&format=json';

        $this->logger->debug("Fetching metrics from: {$url}");

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'BackupPC-Monitor-Agent/1.0',
        ]);

        // Set authentication
        if ($this->apiKey) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $this->apiKey
            ]);
        } elseif ($this->username && $this->password) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->username . ':' . $this->password);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error("cURL error: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            $this->logger->warning("HTTP error {$httpCode} fetching metrics");
            return null;
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Failed to parse JSON response: " . json_last_error_msg());
            return null;
        }

        return $data;
    }

    /**
     * Get current host status for change detection
     */
    public function getHostStatus(): array
    {
        $metrics = $this->fetchMetrics();
        if (!$metrics || !isset($metrics['hosts'])) {
            return [];
        }

        $status = [];
        foreach ($metrics['hosts'] as $host => $data) {
            $status[$host] = $data['state'] ?? 'unknown';
        }
        return $status;
    }
}

/**
 * Dashboard Client for sending data
 */
class DashboardClient
{
    private string $dashboardUrl;
    private int $siteId;
    private string $agentToken;
    private AgentLogger $logger;

    public function __construct(array $config, AgentLogger $logger)
    {
        $this->dashboardUrl = rtrim($config['dashboard_url'], '/');
        $this->siteId = $config['site_id'];
        $this->agentToken = $config['agent_token'];
        $this->logger = $logger;
    }

    /**
     * Register this agent with the dashboard
     */
    public function register(string $version, string $hostname): bool
    {
        $url = "{$this->dashboardUrl}/api/agent/register";

        $data = [
            'site_id' => $this->siteId,
            'agent_token' => $this->agentToken,
            'agent_version' => $version,
            'hostname' => $hostname,
        ];

        $this->logger->info("Registering agent with dashboard", ['url' => $url]);

        $response = $this->post($url, $data);

        if ($response && isset($response['success']) && $response['success']) {
            $this->logger->info("Agent registered successfully");
            return true;
        }

        $this->logger->warning("Agent registration failed", ['response' => $response]);
        return false;
    }

    /**
     * Send backup data to dashboard
     */
    public function sendData(array $backupData, string $eventType = 'full_update'): bool
    {
        $url = "{$this->dashboardUrl}/api/agent/data";

        $data = [
            'site_id' => $this->siteId,
            'agent_token' => $this->agentToken,
            'data' => $backupData,
            'event_type' => $eventType,
        ];

        $response = $this->post($url, $data);

        if ($response && isset($response['success']) && $response['success']) {
            $this->logger->debug("Data sent successfully", ['event_type' => $eventType]);
            return true;
        }

        $this->logger->warning("Failed to send data", ['response' => $response]);
        return false;
    }

    /**
     * Send heartbeat to dashboard
     */
    public function sendHeartbeat(): bool
    {
        return $this->sendData(['type' => 'heartbeat', 'timestamp' => date('c')], 'heartbeat');
    }

    /**
     * Get site configuration from dashboard
     */
    public function getSiteConfig(): ?array
    {
        $url = "{$this->dashboardUrl}/api/agent/config";

        $data = [
            'site_id' => $this->siteId,
            'agent_token' => $this->agentToken,
        ];

        return $this->post($url, $data);
    }

    /**
     * Make POST request to dashboard
     */
    private function post(string $url, array $data): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logger->error("cURL error: {$error}");
            return null;
        }

        if ($httpCode === 401) {
            $this->logger->error("Authentication failed - check agent token");
            return null;
        }

        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->logger->warning("HTTP error {$httpCode}: {$response}");
            return null;
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("Failed to parse JSON response: " . json_last_error_msg());
            return null;
        }

        return $result;
    }
}

/**
 * Main Agent class
 */
class BackupPCAgent
{
    private array $config;
    private AgentLogger $logger;
    private BackupPCClient $backuppc;
    private DashboardClient $dashboard;
    private array $previousHostStatus = [];
    private bool $running = true;
    private int $heartbeatCounter = 0;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->logger = new AgentLogger($config['log_file']);
        $this->backuppc = new BackupPCClient($config, $this->logger);
        $this->dashboard = new DashboardClient($config, $this->logger);
    }

    /**
     * Run the agent
     */
    public function run(): void
    {
        $this->logger->info("Starting BackupPC Monitor Agent", [
            'site_id' => $this->config['site_id'],
            'polling_interval' => $this->config['polling_interval'],
        ]);

        // Register with dashboard
        $hostname = gethostname();
        $version = '1.0.0';

        if (!$this->dashboard->register($version, $hostname)) {
            $this->logger->warning("Failed to register with dashboard, continuing anyway...");
        }

        // Get site configuration from dashboard
        $config = $this->dashboard->getSiteConfig();
        if ($config && isset($config['polling_interval'])) {
            $this->config['polling_interval'] = $config['polling_interval'];
            $this->logger->info("Using polling interval from dashboard: {$config['polling_interval']}s");
        }

        // Initial data fetch
        $this->fetchAndSend();

        // Main loop
        $heartbeatInterval = $this->config['heartbeat_interval'] / $this->config['polling_interval'];
        $lastHeartbeat = 0;

        while ($this->running) {
            sleep($this->config['polling_interval']);

            // Check for shutdown
            if (!$this->running) break;

            // Fetch and send data
            $this->fetchAndSend();

            // Send heartbeat periodically
            $lastHeartbeat++;
            if ($lastHeartbeat >= $heartbeatInterval) {
                $this->dashboard->sendHeartbeat();
                $lastHeartbeat = 0;
            }
        }

        $this->logger->info("Agent shutdown complete");
    }

    /**
     * Fetch data from BackupPC and send to dashboard
     */
    private function fetchAndSend(): void
    {
        $this->logger->info("Fetching backup data from BackupPC");

        $metrics = $this->backuppc->fetchMetrics();
        if (!$metrics) {
            $this->logger->warning("Failed to fetch metrics from BackupPC");
            return;
        }

        // Detect status changes
        $eventType = 'full_update';
        $currentStatus = [];

        if (isset($metrics['hosts'])) {
            foreach ($metrics['hosts'] as $host => $data) {
                $currentStatus[$host] = $data['state'] ?? 'unknown';

                // Check if this is a status change
                if (isset($this->previousHostStatus[$host])) {
                    if ($this->previousHostStatus[$host] !== $currentStatus[$host]) {
                        $eventType = 'status_change';
                        $this->logger->info("Status change detected", [
                            'host' => $host,
                            'old_status' => $this->previousHostStatus[$host],
                            'new_status' => $currentStatus[$host],
                        ]);
                    }
                }
            }
        }

        $this->previousHostStatus = $currentStatus;

        // Send to dashboard
        if ($this->dashboard->sendData($metrics, $eventType)) {
            $this->logger->debug("Data sent to dashboard successfully");
        } else {
            $this->logger->warning("Failed to send data to dashboard");
        }
    }

    /**
     * Stop the agent gracefully
     */
    public function stop(): void
    {
        $this->running = false;
        $this->logger->info("Stopping agent...");
    }

    /**
     * Write PID file
     */
    public function writePidFile(): bool
    {
        $pid = getmypid();
        $dir = dirname($this->config['pid_file']);

        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (@file_put_contents($this->config['pid_file'], (string)$pid, LOCK_EX) === false) {
            $this->logger->error("Failed to write PID file: {$this->config['pid_file']}");
            return false;
        }

        return true;
    }

    /**
     * Clean up PID file
     */
    public function cleanupPidFile(): void
    {
        if (file_exists($this->config['pid_file'])) {
            @unlink($this->config['pid_file']);
        }
    }
}

// Signal handlers for graceful shutdown
$agent = null;

function signalHandler(int $signal): void
{
    global $agent;
    if ($agent instanceof BackupPCAgent) {
        $agent->stop();
    }
    exit(0);
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

// Create and run agent
try {
    $agent = new BackupPCAgent($CONFIG);
    $agent->writePidFile();
    $agent->run();
    $agent->cleanupPidFile();
} catch (\Exception $e) {
    fwrite(STDERR, "ERROR: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
