# BackupPC Monitor Agent Installation Guide for Rocky Linux

This guide provides complete instructions for installing the BackupPC Monitor Agent as a systemd service on Rocky Linux.

## 🚀 Quick Installation (Automated)

For most users, use the automated installation script:

```bash
# Download and run the installation script
sudo ./install-rocky-linux.sh
```

The script will handle all installation steps automatically.

## 📋 Manual Installation (Step-by-Step)

If you prefer manual installation or need customization, follow these steps:

### Prerequisites

- Rocky Linux 8 or 9
- Root or sudo access
- BackupPC server running
- BackupPC Monitor dashboard accessible

### Step 1: Install PHP and Dependencies

```bash
# Install PHP and required extensions
sudo dnf install -y php php-cli php-curl php-json php-mbstring php-xml

# Verify PHP installation
php --version
```

### Step 2: Create Dedicated User

```bash
# Create system user for the agent
sudo useradd --system --shell /sbin/nologin --home-dir /opt/backuppc-monitor --create-home backuppc-agent

# Verify user creation
id backuppc-agent
```

### Step 3: Create Directories

```bash
# Create installation and data directories
sudo mkdir -p /opt/backuppc-monitor/agent
sudo mkdir -p /var/log/backuppc-monitor
sudo mkdir -p /var/run/backuppc-monitor

# Set ownership
sudo chown -R backuppc-agent:backuppc-agent /opt/backuppc-monitor
sudo chown backuppc-agent:backuppc-agent /var/log/backuppc-monitor
sudo chown backuppc-agent:backuppc-agent /var/run/backuppc-monitor
```

### Step 4: Install Agent Files

```bash
# Copy agent files to installation directory
sudo cp agent/agent.php /opt/backuppc-monitor/agent/
sudo cp agent/config.php /opt/backuppc-monitor/agent/
sudo cp agent/config.example.php /opt/backuppc-monitor/agent/

# Set proper permissions
sudo chmod 600 /opt/backuppc-monitor/agent/config.php
sudo chmod 644 /opt/backuppc-monitor/agent/agent.php
```

### Step 5: Configure Agent

Edit the configuration file with your settings:

```bash
sudo vi /opt/backuppc-monitor/agent/config.php
```

Configure the following settings:

```php
return [
    // REQUIRED: Dashboard configuration
    'dashboard_url' => 'https://your-dashboard.example.com',  // HTTPS required
    'site_id' => 1,                                           // Your site ID
    'agent_token' => 'your-agent-token-from-dashboard',       // Agent token

    // OPTIONAL: BackupPC configuration
    'backuppc_url' => 'http://localhost/BackupPC',            // BackupPC URL
    'backuppc_username' => '',                                // If authentication required
    'backuppc_password' => '',                                // If authentication required
    'api_key' => '',                                          // API key if available

    // OPTIONAL: Agent settings
    'polling_interval' => 60,                                 // Polling interval (seconds)
    'heartbeat_interval' => 300,                              // Heartbeat interval (seconds)
    'log_file' => '/var/log/backuppc-monitor/agent.log',      // Log file path
    'pid_file' => '/var/run/backuppc-monitor/agent.pid',      // PID file path
    'ws_poll_interval' => 30,                                 // Command polling interval

    // OPTIONAL: Certificate pinning
    'dashboard_pubkey' => '',                                 // Path to dashboard public key
];
```

### Step 6: Install Systemd Service

```bash
# Install service file
sudo cp agent/backuppc-monitor-agent.service /etc/systemd/system/

# Reload systemd daemon
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable backuppc-monitor-agent
```

### Step 7: Configure Log Rotation

Create log rotation configuration:

```bash
sudo tee /etc/logrotate.d/backuppc-monitor-agent > /dev/null << 'EOF'
/var/log/backuppc-monitor/agent.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    create 0644 backuppc-agent backuppc-agent
    postrotate
        systemctl reload backuppc-monitor-agent.service || true
    endscript
}
EOF
```

### Step 8: Start Service

```bash
# Start the service
sudo systemctl start backuppc-monitor-agent

# Check service status
sudo systemctl status backuppc-monitor-agent
```

### Step 9: Verify Installation

```bash
# Check if service is running
sudo systemctl is-active backuppc-monitor-agent

# View service logs
sudo journalctl -u backuppc-monitor-agent -f

# Check agent-specific logs
sudo tail -f /var/log/backuppc-monitor/agent.log
```

## 🔧 Configuration Options

### Certificate Pinning (Advanced Security)

For maximum security, you can pin the dashboard's certificate:

1. **Obtain the dashboard's public key:**
   ```bash
   # Connect to dashboard and extract certificate
   openssl s_client -connect your-dashboard.example.com:443 -servername your-dashboard.example.com < /dev/null | openssl x509 -pubkey -noout > /etc/ssl/dashboard-pubkey.pem
   ```

2. **Configure certificate pinning:**
   ```php
   // In config.php
   'dashboard_pubkey' => '/etc/ssl/dashboard-pubkey.pem',
   ```

3. **Restart service:**
   ```bash
   sudo systemctl restart backuppc-monitor-agent
   ```

### Environment Variables (Alternative Configuration)

Instead of config.php, you can use environment variables:

```bash
# Create environment file
sudo tee /etc/sysconfig/backuppc-monitor-agent > /dev/null << EOF
SITE_ID=1
AGENT_TOKEN=your-token-here
DASHBOARD_URL=https://your-dashboard.example.com
DASHBOARD_PUBKEY=/etc/ssl/dashboard-pubkey.pem
EOF

# Update service file to use environment file
sudo sed -i 's|# Environment variables|EnvironmentFile=/etc/sysconfig/backuppc-monitor-agent|' /etc/systemd/system/backuppc-monitor-agent.service
sudo systemctl daemon-reload
sudo systemctl restart backuppc-monitor-agent
```

## 🔍 Troubleshooting

### Common Issues

1. **Service won't start:**
   ```bash
   # Check service status
   sudo systemctl status backuppc-monitor-agent

   # View detailed logs
   sudo journalctl -u backuppc-monitor-agent -n 50
   ```

2. **Permission errors:**
   ```bash
   # Check file permissions
   ls -la /opt/backuppc-monitor/agent/
   ls -la /var/log/backuppc-monitor/

   # Fix permissions if needed
   sudo chown -R backuppc-agent:backuppc-agent /opt/backuppc-monitor
   sudo chown backuppc-agent:backuppc-agent /var/log/backuppc-monitor
   ```

3. **PHP not found:**
   ```bash
   # Verify PHP installation
   which php
   php --version

   # Install if missing
   sudo dnf install -y php php-cli php-curl
   ```

4. **Network connectivity:**
   ```bash
   # Test dashboard connectivity
   curl -I https://your-dashboard.example.com

   # Test BackupPC connectivity
   curl -I http://localhost/BackupPC
   ```

### Log Files

- **Systemd logs:** `sudo journalctl -u backuppc-monitor-agent -f`
- **Agent logs:** `/var/log/backuppc-monitor/agent.log`
- **BackupPC logs:** `/var/log/BackupPC/LOG` (if available)

## 📋 Service Management

### Basic Commands

```bash
# Check status
sudo systemctl status backuppc-monitor-agent

# Start service
sudo systemctl start backuppc-monitor-agent

# Stop service
sudo systemctl stop backuppc-monitor-agent

# Restart service
sudo systemctl restart backuppc-monitor-agent

# Enable auto-start
sudo systemctl enable backuppc-monitor-agent

# Disable auto-start
sudo systemctl disable backuppc-monitor-agent
```

### Advanced Commands

```bash
# View real-time logs
sudo journalctl -u backuppc-monitor-agent -f

# View last 100 log entries
sudo journalctl -u backuppc-monitor-agent -n 100

# Reload service configuration
sudo systemctl reload backuppc-monitor-agent

# Check if service is enabled
sudo systemctl is-enabled backuppc-monitor-agent
```

## 🔄 Upgrading

To upgrade the agent:

```bash
# Stop service
sudo systemctl stop backuppc-monitor-agent

# Backup current config
sudo cp /opt/backuppc-monitor/agent/config.php /opt/backuppc-monitor/agent/config.php.backup

# Install new files
sudo cp new-agent-files/agent.php /opt/backuppc-monitor/agent/
# ... copy other updated files

# Restore config
sudo cp /opt/backuppc-monitor/agent/config.php.backup /opt/backuppc-monitor/agent/config.php

# Start service
sudo systemctl start backuppc-monitor-agent
```

## 📞 Support

If you encounter issues:

1. Check the troubleshooting section above
2. Review log files for error messages
3. Verify network connectivity to dashboard and BackupPC
4. Ensure proper file permissions and ownership
5. Check PHP version and installed extensions

## 📁 File Locations

- **Agent binary:** `/opt/backuppc-monitor/agent/agent.php`
- **Configuration:** `/opt/backuppc-monitor/agent/config.php`
- **Service file:** `/etc/systemd/system/backuppc-monitor-agent.service`
- **Log files:** `/var/log/backuppc-monitor/agent.log`
- **PID file:** `/var/run/backuppc-monitor/agent.pid`
- **Log rotation:** `/etc/logrotate.d/backuppc-monitor-agent`
