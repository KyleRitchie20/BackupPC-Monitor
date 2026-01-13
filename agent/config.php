<?php
/**
 * BackupPC Monitor Agent Configuration
 *
 * This file contains configuration settings for the BackupPC Monitor Agent.
 * Copy from config.example.php and modify with your actual settings.
 *
 * WARNING: This file contains sensitive credentials.
 * Ensure proper file permissions (600) and security measures.
 */

return [
    // Dashboard configuration
    'dashboard_url' => 'https://your-dashboard.example.com',
    'site_id' => 1,
    'agent_token' => 'your-agent-token-from-dashboard',

    // BackupPC server configuration
    'backuppc_url' => 'http://localhost/BackupPC',
    'backuppc_username' => '',  // Leave empty if using API key
    'backuppc_password' => '',  // Leave empty if using API key
    'api_key' => '',            // Alternative to username/password

    // Agent settings
    'polling_interval' => 60,   // Seconds between data fetches
    'heartbeat_interval' => 300, // Seconds between heartbeats
    'log_file' => '/var/log/backuppc-monitor-agent.log',
    'pid_file' => '/var/run/backuppc-monitor-agent.pid',
    'ws_poll_interval' => 30,   // Seconds between command polling
    'dashboard_pubkey' => '',   // Optional: dashboard public key for certificate pinning
];
