<?php
/**
 * BackupPC Monitor Agent Configuration
 *
 * Copy this file to config.php and modify with your settings.
 *
 * WARNING: This file contains sensitive credentials.
 * Ensure proper file permissions (600) and security measures.
 */

return [
    // Dashboard configuration
    'dashboard_url' => 'http://your-dashboard.example.com',
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
];
