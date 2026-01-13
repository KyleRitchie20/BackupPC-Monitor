#!/bin/bash
#
# BackupPC Monitor Agent Installation Script for Rocky Linux
# This script installs and configures the BackupPC Monitor Agent as a systemd service
#

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
INSTALL_DIR="/opt/backuppc-monitor"
SERVICE_NAME="backuppc-monitor-agent"
SERVICE_FILE="/etc/systemd/system/${SERVICE_NAME}.service"
LOGROTATE_FILE="/etc/logrotate.d/${SERVICE_NAME}"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Check if running as root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root (sudo)"
    exit 1
fi

log_info "Starting BackupPC Monitor Agent installation for Rocky Linux..."

# Step 1: Check Rocky Linux version
log_info "Checking Rocky Linux version..."
if ! grep -q "Rocky Linux" /etc/os-release; then
    log_error "This script is designed for Rocky Linux"
    exit 1
fi

# Step 2: Install PHP and required extensions
log_info "Installing PHP and required extensions..."
dnf install -y php php-cli php-curl php-json php-mbstring php-xml

# Verify PHP installation
if ! command -v php &> /dev/null; then
    log_error "PHP installation failed"
    exit 1
fi

PHP_VERSION=$(php --version | head -n 1 | cut -d' ' -f2 | cut -d'.' -f1-2)
log_success "PHP $PHP_VERSION installed successfully"

# Step 3: Create dedicated user and group
log_info "Creating dedicated user and group..."
if ! id -u backuppc-agent &>/dev/null; then
    useradd --system --shell /sbin/nologin --home-dir /opt/backuppc-monitor --create-home backuppc-agent
    log_success "Created user 'backuppc-agent'"
else
    log_warn "User 'backuppc-agent' already exists"
fi

# Step 4: Create installation directories
log_info "Creating installation directories..."
mkdir -p "$INSTALL_DIR"
mkdir -p "$INSTALL_DIR/agent"
mkdir -p /var/log/backuppc-monitor
mkdir -p /var/run/backuppc-monitor

# Step 5: Install agent files
log_info "Installing agent files..."
# Copy agent files (assuming they're in the current directory)
cp agent/agent.php "$INSTALL_DIR/agent/"
cp agent/config.php "$INSTALL_DIR/agent/"
cp agent/config.example.php "$INSTALL_DIR/agent/"
cp agent/backuppc-monitor-agent.service /etc/systemd/system/

log_success "Agent files installed to $INSTALL_DIR"

# Step 6: Set proper permissions
log_info "Setting file permissions..."
chown -R backuppc-agent:backuppc-agent "$INSTALL_DIR"
chown backuppc-agent:backuppc-agent /var/log/backuppc-monitor
chown backuppc-agent:backuppc-monitor /var/run/backuppc-monitor

# Secure config file permissions
chmod 600 "$INSTALL_DIR/agent/config.php"
chmod 644 "$INSTALL_DIR/agent/agent.php"
chmod 644 "$INSTALL_DIR/agent/config.example.php"

log_success "File permissions configured"

# Step 7: Configure log rotation
log_info "Setting up log rotation..."
cat > "$LOGROTATE_FILE" << 'EOF'
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

log_success "Log rotation configured"

# Step 8: Enable and start service
log_info "Enabling and starting systemd service..."
systemctl daemon-reload
systemctl enable "$SERVICE_NAME"

log_warn "IMPORTANT: Before starting the service, you MUST configure $INSTALL_DIR/agent/config.php"
log_warn "Edit the config.php file with your actual dashboard URL, site ID, and agent token"
echo
log_info "To configure the agent:"
echo "  1. Edit $INSTALL_DIR/agent/config.php"
echo "  2. Set your dashboard URL, site ID, and agent token"
echo "  3. Optionally configure certificate pinning"
echo
read -p "Press Enter after configuring config.php to continue with service start..."

# Step 9: Start service
log_info "Starting BackupPC Monitor Agent service..."
systemctl start "$SERVICE_NAME"

# Step 10: Verify service status
sleep 2
if systemctl is-active --quiet "$SERVICE_NAME"; then
    log_success "BackupPC Monitor Agent service started successfully"
else
    log_error "Service failed to start. Check logs with: journalctl -u $SERVICE_NAME -f"
    log_info "You can also check the agent logs at: /var/log/backuppc-monitor/agent.log"
    exit 1
fi

# Step 11: Show service status
log_info "Service status:"
systemctl status "$SERVICE_NAME" --no-pager

echo
log_success "Installation completed successfully!"
echo
log_info "Useful commands:"
echo "  Check status: systemctl status $SERVICE_NAME"
echo "  View logs: journalctl -u $SERVICE_NAME -f"
echo "  Restart: systemctl restart $SERVICE_NAME"
echo "  Stop: systemctl stop $SERVICE_NAME"
echo
log_info "Configuration file: $INSTALL_DIR/agent/config.php"
log_info "Log files: /var/log/backuppc-monitor/agent.log"
echo
log_info "For certificate pinning (optional):"
echo "  1. Obtain your dashboard's public key"
echo "  2. Save it to /etc/ssl/dashboard-pubkey.pem"
echo "  3. Add 'dashboard_pubkey' => '/etc/ssl/dashboard-pubkey.pem' to config.php"
echo "  4. Restart the service"
