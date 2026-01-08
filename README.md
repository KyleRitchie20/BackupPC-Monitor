# BackupPC Monitoring Dashboard

A comprehensive Laravel-based monitoring dashboard for BackupPC servers with SSH tunnel and polling agent support.

## Features

- **Role-Based Access Control**: Admin and Client roles with different access levels
- **Multi-Site Management**: Monitor multiple BackupPC servers from one dashboard
- **Dual Connection Methods**: SSH Tunnel or Polling Agent connectivity
- **Secure Credential Storage**: Encrypted storage of SSH passwords and API keys
- **Comprehensive Monitoring**: Server health, backup status, disk usage, and more
- **Real-time Updates**: Manual and scheduled data fetching
- **Responsive Design**: Mobile-friendly interface with color-coded status indicators

## Requirements

- PHP 8.2+
- Laravel 12
- SQLite (default) or PostgreSQL/MySQL
- Composer
- Node.js (for frontend assets)
- SSH client (for SSH tunnel functionality)
- BackupPC 4.x servers

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/KyleRitchie20/BackupPC-Monitor.git
cd BackupPC-Monitor
```

### 2. Install Dependencies

```bash
composer install
npm install
npm run build
```

### 3. Configure Environment

Copy `.env.example` to `.env` and configure your database and application settings:

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database credentials:

```
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=backuppc_dashboard
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. Run Migrations and Seeders

```bash
php artisan migrate:fresh --seed
```

This will:
- Create all necessary database tables
- Seed roles (admin/client)
- Create demo users
- Create demo sites

### 5. Run the Application

Start the development server:

```bash
php artisan serve
```

The application will be available at `http://localhost:8000`

### 6. Configure Web Server

Set up your web server (Apache/Nginx) to point to the `public` directory.

### 7. Set Up Scheduled Tasks

Add the following cron job to fetch backup data periodically:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

To set up automatic data fetching, edit `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Fetch data every 30 minutes for all active sites
    $schedule->command('backup:fetch-data')->everyThirtyMinutes();

    // You can also schedule specific sites
    // $schedule->command('backup:fetch-data 1')->hourly();
}
```

## Usage

### Login Credentials

After seeding, you can log in with:

**Admin Account:**
- Email: `admin@example.com`
- Password: `admin123`

**Client Account:**
- Email: `client@example.com`
- Password: `client123`

### Dashboard Access

- **Admin Dashboard**: Full access to all sites, system overview, and management features
- **Client Dashboard**: Site-specific backup status and monitoring

### Site Management

1. Navigate to **Sites Management** from the admin dashboard
2. Click **Add New Site** to create a new BackupPC server connection
3. Choose connection method (SSH or Agent)
4. Enter connection details and credentials
5. Save the site configuration

### Manual Data Refresh

- Clients can refresh their site data from the dashboard
- Admins can refresh all sites data from the admin dashboard

## Configuration Options

### Connection Methods

**SSH Tunnel:**
- Requires SSH access to the BackupPC server
- Uses port forwarding to access the BackupPC API
- Encrypted password storage

**Polling Agent:**
- Direct API access via HTTP
- Requires API key authentication
- Suitable for servers with public API access

### Site Configuration Fields

| Field | Description | Required |
|-------|-------------|----------|
| Name | Display name for the site | Yes |
| BackupPC URL | URL to BackupPC server | Yes |
| Connection Method | SSH or Agent | Yes |
| SSH Host | SSH server hostname | SSH only |
| SSH Port | SSH server port | SSH only |
| SSH Username | SSH username | SSH only |
| SSH Password | SSH password | SSH only |
| API Key | BackupPC API key | Agent only |
| Polling Interval | Data refresh interval (minutes) | Yes |
| Active Status | Enable/disable monitoring | Yes |

## Security

- All credentials are encrypted in the database
- Role-based access control prevents unauthorized access
- CSRF protection on all forms
- Password hashing for user authentication

## Agent Setup (Push-Based Monitoring)

The BackupPC Monitor Agent enables real-time, push-based monitoring of BackupPC servers. Agents run on each BackupPC server and send data directly to the dashboard, eliminating the need for the dashboard to poll individual servers.

### How It Works

1. Create a site with **Connection Method: Agent**
2. Generate an agent token from the site management page
3. Deploy the agent script to the BackupPC server
4. Configure and start the agent
5. View real-time data on the dashboard

### Agent Architecture

```
┌─────────────────────┐         ┌─────────────────────┐
│   BackupPC Server   │         │  Dashboard Server   │
│                     │         │                     │
│  ┌───────────────┐  │   HTTP  │  ┌───────────────┐  │
│  │ BackupPC Agent│──┼────────>│  │   Dashboard   │  │
│  │ (PHP Script)  │  │  POST   │  │   (Laravel)   │  │
│  └───────────────┘  │         │  └───────────────┘  │
│         │           │         │         │           │
│         v           │         │         v           │
│  ┌───────────────┐  │         │  ┌───────────────┐  │
│  │ BackupPC CGI  │  │         │  │   Database    │  │
│  │   API         │  │         │  │   (SQLite/PG) │  │
│  └───────────────┘  │         │  └───────────────┘  │
└─────────────────────┘         └─────────────────────┘
```

### Dashboard Setup

#### 1. Create a Site with Agent Connection

1. Log in to the dashboard as an admin
2. Navigate to **Sites Management** → **Add New Site**
3. Enter site details:
   - **Name**: A descriptive name for this BackupPC server
   - **BackupPC URL**: URL to the BackupPC server (e.g., `http://backuppc.internal/BackupPC`)
   - **Connection Method**: Select **Agent**
   - **Polling Interval**: Set to desired interval (ignored for agents)
4. Save the site

#### 2. Generate Agent Token

1. Click on the site to view details
2. Click **Agent Config** button
3. The agent token will be auto-generated
4. Copy the token or use the "Copy to clipboard" button

### BackupPC Server Setup

#### 1. Copy Agent Files to BackupPC Server

Transfer the `agent/` directory from the dashboard to your BackupPC server:

```bash
# From your local machine or dashboard server
scp -r /path/to/BackupPC-Monitor/agent/ backuppc@backuppc-server:/opt/backuppc-monitor-agent/
```

#### 2. Configure the Agent

Copy the example configuration and edit it:

```bash
ssh backuppc@backuppc-server
cd /opt/backuppc-monitor-agent
cp config.example.php config.php
nano config.php
```

Edit `config.php` with your settings:

```php
<?php
return [
    // Dashboard configuration
    'dashboard_url' => 'http://your-dashboard.example.com',
    'site_id' => 1,  // Site ID from the dashboard
    'agent_token' => 'your-agent-token-from-dashboard',

    // BackupPC server configuration
    'backuppc_url' => 'http://localhost/BackupPC',
    'backuppc_username' => '',  // Leave empty if not needed
    'backuppc_password' => '',  // Leave empty if not needed
    'api_key' => '',            // Leave empty if using username/password

    // Agent settings
    'polling_interval' => 60,   // Seconds between data fetches
    'heartbeat_interval' => 300, // Seconds between heartbeats
    'log_file' => '/var/log/backuppc-monitor-agent.log',
    'pid_file' => '/var/run/backuppc-monitor-agent.pid',
];
```

#### 3. Test the Agent

Run the agent manually to verify it works:

```bash
php /opt/backuppc-monitor-agent/agent.php \
    --site-id=1 \
    --agent-token=your-token-here \
    --dashboard-url=http://your-dashboard.example.com
```

You should see logs indicating successful registration and data sending.

#### 4. Install as a Systemd Service (Recommended)

Copy the systemd service file:

```bash
sudo cp /opt/backuppc-monitor-agent/backuppc-monitor-agent.service /etc/systemd/system/
sudo chmod 644 /etc/systemd/system/backuppc-monitor-agent.service
```

Edit the service file to set your configuration:

```bash
sudo nano /etc/systemd/system/backuppc-monitor-agent.service
```

Update the environment variables:

```ini
[Service]
Environment=SITE_ID=1
Environment=AGENT_TOKEN=your-agent-token-here
Environment=DASHBOARD_URL=http://your-dashboard.example.com
Environment=BACKUPPC_URL=http://localhost/BackupPC
Environment=POLLING_INTERVAL=60
Environment=HEARTBEAT_INTERVAL=300
Environment=LOG_FILE=/var/log/backuppc-monitor-agent.log
Environment=PID_FILE=/var/run/backuppc-monitor-agent.pid
```

Enable and start the service:

```bash
sudo systemctl daemon-reload
sudo systemctl enable backuppc-monitor-agent
sudo systemctl start backuppc-monitor-agent
```

Check the status:

```bash
sudo systemctl status backuppc-monitor-agent
sudo journalctl -u backuppc-monitor-agent -f
```

### Agent Command Line Options

```
Usage: php agent.php [OPTIONS]

Options:
    --site-id           Site ID in the dashboard (required)
    --agent-token       Agent authentication token (required)
    --dashboard-url     URL of the central dashboard
    --backuppc-url      URL to BackupPC server (default: http://localhost/BackupPC)
    --username          BackupPC username for authentication
    --password          BackupPC password for authentication
    --api-key           BackupPC API key (alternative to username/password)
    --interval          Polling interval in seconds (default: 60)
    --log               Path to log file
    --help              Show this help message
```

### Agent Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| SITE_ID | Site ID from dashboard | Yes |
| AGENT_TOKEN | Agent authentication token | Yes |
| DASHBOARD_URL | Dashboard URL | Yes |
| BACKUPPC_URL | BackupPC server URL | No |
| BACKUPPC_USERNAME | BackupPC username | No |
| BACKUPPC_PASSWORD | BackupPC password | No |
| BACKUPPC_API_KEY | BackupPC API key | No |
| POLLING_INTERVAL | Seconds between fetches | No |
| HEARTBEAT_INTERVAL | Seconds between heartbeats | No |
| LOG_FILE | Path to log file | No |
| PID_FILE | Path to PID file | No |

### Monitoring Agent Status

From the dashboard:

1. Navigate to **Sites Management**
2. Click on the site with agent connection
3. Click **Agent Config**
4. View the agent status:
   - **Agent Connected** (green): Agent is actively sending data
   - **Agent Configured (Offline)** (yellow): Token configured but no recent contact
   - **Agent Not Configured** (gray): No token generated

### Troubleshooting

**Agent Won't Start:**
```bash
# Check PHP is installed
php --version

# Check file permissions
chmod +x agent.php
ls -la /opt/backuppc-monitor-agent/

# Test configuration
php agent.php --help
```

**Agent Can't Connect to Dashboard:**
```bash
# Verify dashboard URL is accessible from BackupPC server
curl -I http://your-dashboard.example.com

# Check agent token is correct
# View the token in Dashboard → Sites → Agent Config

# Check firewall allows outbound connections
sudo ufw status
```

**Agent Can't Connect to BackupPC:**
```bash
# Verify BackupPC URL is accessible locally
curl -I http://localhost/BackupPC

# Check BackupPC CGI is installed
ls /usr/local/BackupPC/cgi-bin/BackupPC_Admin

# Check BackupPC logs
sudo tail -f /var/log/BackupPC/BackupPC.log
```

**No Data Appearing on Dashboard:**
```bash
# Check agent logs
tail -f /var/log/backuppc-monitor-agent.log

# Verify agent token matches site
# Dashboard → Sites → Agent Config

# Check dashboard receives data
# Look for POST requests to /api/agent/data
```

**Service Fails to Start:**
```bash
# Check systemd service status
sudo systemctl status backuppc-monitor-agent

# Check service logs
sudo journalctl -u backuppc-monitor-agent -n 100

# Check file permissions
ls -la /var/log/backuppc-monitor-agent.log
ls -la /var/run/backuppc-monitor-agent.pid
```

### Agent File Structure

```
agent/
├── agent.php                    # Main agent script
├── backuppc-monitor-agent.service  # Systemd service file
├── config.example.php           # Configuration template
└── README.md                    # Agent-specific documentation
```

### Security Considerations

1. **Agent Token**: Keep the agent token secure. It authenticates the agent to the dashboard.
2. **File Permissions**: Set restrictive permissions on config files:
   ```bash
   chmod 600 /opt/backuppc-monitor-agent/config.php
   ```
3. **Firewall**: Only allow outbound HTTPS to the dashboard server.
4. **Service User**: Run the agent as a non-root user (backuppc):
   ```ini
   User=backuppc
   Group=backuppc
   ```

## Troubleshooting

### Common Issues

**SSH Connection Failed:**
- Verify SSH credentials
- Check firewall settings
- Ensure SSH server is running on the specified port

**API Request Failed:**
- Verify BackupPC URL is correct
- Check API key for agent connections
- Ensure BackupPC CGI is accessible

**Permission Denied:**
- Check user roles and assignments
- Verify site access permissions

## Customization

### Adding New Features

1. Create new controllers in `app/Http/Controllers`
2. Add routes in `routes/web.php`
3. Create views in `resources/views`
4. Update models as needed

### Modifying Dashboard

Edit the dashboard views:
- `resources/views/dashboard/admin.blade.php` (Admin dashboard)
- `resources/views/dashboard/client.blade.php` (Client dashboard)

### Adding New Status Indicators

Modify the `BackupPCService` to include additional metrics and update the dashboard views accordingly.

## API Endpoints

| Endpoint | Method | Description | Authentication |
|----------|--------|-------------|----------------|
| `/fetch-backup-data` | POST | Fetch data for specific site | Required |
| `/fetch-all-backup-data` | POST | Fetch data for all sites | Admin only |
| `/get-backup-status` | GET | Get backup status summary | Required |
| `/api/agent/data` | POST | Receive data from agents | Agent Token |
| `/api/agent/register` | POST | Register agent with dashboard | Agent Token |
| `/api/agent/config` | POST | Get site configuration | Agent Token |

## Deployment

### Production Environment

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Queue Workers (Optional)

For better performance with many sites:

```bash
php artisan queue:work --daemon
```

## Support

For issues and feature requests, please contact the development team.

## Apache Setup on Rocky Linux / RHEL-based Systems

This section provides detailed instructions for deploying the BackupPC Monitor dashboard on Apache web server running Rocky Linux 9 or similar RHEL-based distributions.

### 1. Server Preparation

```bash
# Update system
sudo dnf update -y

# Install required packages
sudo dnf install -y epel-release
sudo dnf install -y httpd php php-cli php-fpm php-mbstring php-xml php-curl php-sqlite3 php-pdo git unzip wget

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
sudo chmod +x /usr/local/bin/composer

# Install Node.js (for frontend assets)
curl -fsSL https://rpm.nodesource.com/setup_20.x | sudo bash -
sudo dnf install -y nodejs
```

### 2. Configure PHP

Edit PHP configuration:
```bash
sudo nano /etc/php.ini
```

Update the following settings:
```ini
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
```

### 3. Configure PHP-FPM (if using)

```bash
sudo systemctl enable php-fpm
sudo systemctl start php-fpm
```

Edit the PHP-FPM pool configuration:
```bash
sudo nano /etc/php-fpm.d/www.conf
```

Ensure these lines are present:
```ini
listen = 127.0.0.1:9000
listen.owner = apache
listen.group = apache
listen.mode = 0660
```

### 4. Deploy the Application

```bash
# Create application directory
sudo mkdir -p /var/www/html
cd /var/www/html

# Clone the repository
sudo git clone https://github.com/KyleRitchie20/BackupPC-Monitor.git backuppc-monitor
cd backuppc-monitor

# Set permissions
sudo chown -R apache:apache /var/www/html/backuppc-monitor
sudo chmod -R 755 /var/www/html/backuppc-monitor
sudo chmod -R 775 storage bootstrap/cache

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install
npm run build

# Configure environment
cp .env.example .env
nano .env
```

Configure `.env` for production:
```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=sqlite
# Or for PostgreSQL:
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=backuppc_dashboard
# DB_USERNAME=your_user
# DB_PASSWORD=your_password

CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync
```

```bash
# Generate application key
php artisan key:generate --force

# Run migrations
php artisan migrate --force

# Seed database (optional - for demo data)
php artisan db:seed --force

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 5. Configure Apache Virtual Host

Create Apache configuration:
```bash
sudo nano /etc/httpd/conf.d/backuppc-monitor.conf
```

Add the following configuration:
```apache
<VirtualHost *:80>
    ServerName your-domain.com
    ServerAlias www.your-domain.com
    DocumentRoot /var/www/html/backuppc-monitor/public

    <Directory /var/www/html/backuppc-monitor/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # PHP-FPM configuration
    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:9000"
    </FilesMatch>

    # Enable URL rewriting
    <IfModule mod_rewrite.c>
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteRule ^ index.php [L]
    </IfModule>

    ErrorLog /var/log/httpd/backuppc-monitor-error.log
    CustomLog /var/log/httpd/backuppc-monitor-access.log combined
</VirtualHost>
```

### 6. Configure SELinux

```bash
# Allow Apache to access the application directory
sudo setsebool -P httpd_can_network_connect on
sudo setsebool -P httpd_read_user_content on
sudo chcon -R -t httpd_sys_content_t /var/www/html/backuppc-monitor
sudo chcon -R -t httpd_sys_rw_content_t /var/www/html/backuppc-monitor/storage
sudo chcon -R -t httpd_sys_rw_content_t /var/www/html/backuppc-monitor/bootstrap/cache
```

### 7. Configure Firewall

```bash
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 8. Start and Enable Services

```bash
sudo systemctl enable httpd
sudo systemctl enable php-fpm
sudo systemctl start httpd
sudo systemctl start php-fpm
```

### 9. Set Up Scheduled Tasks

```bash
# Edit crontab
sudo crontab -e
```

Add the following line:
```bash
* * * * * cd /var/www/html/backuppc-monitor && php artisan schedule:run >> /dev/null 2>&1
```

### 10. Optional: Configure HTTPS with Let's Encrypt

```bash
# Install Certbot
sudo dnf install -y certbot python3-certbot-apache

# Obtain SSL certificate
sudo certbot --apache -d your-domain.com -d www.your-domain.com

# Set up auto-renewal
sudo systemctl enable certbot-renew.timer
sudo systemctl start certbot-renew.timer
```

### 11. Verify Installation

1. Open your browser and navigate to `http://your-domain.com`
2. Log in with default credentials:
   - **Admin**: `admin@example.com` / `admin123`
   - **Client**: `client@example.com` / `client123`

### Troubleshooting

**Permission Errors:**
```bash
sudo chown -R apache:apache /var/www/html/backuppc-monitor
sudo chmod -R 755 /var/www/html/backuppc-monitor
```

**SELinux Issues:**
```bash
# Check SELinux context
sudo ls -Z /var/www/html/backuppc-monitor

# Reset context if needed
sudo restorecon -Rv /var/www/html/backuppc-monitor
```

**PHP-FPM Not Responding:**
```bash
sudo systemctl status php-fpm
sudo journalctl -u php-fpm -n 50
```

**Apache Error Logs:**
```bash
sudo tail -f /var/log/httpd/backuppc-monitor-error.log
sudo tail -f /var/log/httpd/error_log
```

### Updating the Application

```bash
cd /var/www/html/backuppc-monitor
sudo git pull origin main
sudo composer install --no-dev --optimize-autoloader
sudo npm install
sudo npm run build
sudo php artisan migrate --force
sudo php artisan config:cache
sudo php artisan route:cache
sudo php artisan view:cache
sudo systemctl restart httpd
```

## License

[MIT License](LICENSE)
