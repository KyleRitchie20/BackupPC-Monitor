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

## License

[MIT License](LICENSE)