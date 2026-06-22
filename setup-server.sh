#!/bin/bash
set -e

# Detect install path from where this script lives
KANBAN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# Detect the owner of that directory as the runtime user
KANBAN_USER="$(stat -c '%U' "$KANBAN_DIR")"

echo "==> AgentBoard setup"
echo "    Directory : $KANBAN_DIR"
echo "    User      : $KANBAN_USER"
echo ""

echo "==> Installing nginx and php-fpm..."
pacman -S --noconfirm nginx php-fpm

echo "==> Writing php-fpm pool config..."
mkdir -p /etc/php/php-fpm.d
cat > /etc/php/php-fpm.conf <<'EOF'
[global]
pid = /run/php-fpm/php-fpm.pid
error_log = /var/log/php-fpm/error.log
include = /etc/php/php-fpm.d/*.conf
EOF

cat > /etc/php/php-fpm.d/kanban.conf <<EOF
[kanban]
user = $KANBAN_USER
group = $KANBAN_USER
listen = /run/php-fpm/kanban.sock
listen.owner = http
listen.group = http
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
php_admin_value[error_log] = /var/log/php-fpm/kanban-error.log
php_admin_flag[log_errors] = on
EOF

echo "==> Writing nginx config..."
cat > /etc/nginx/nginx.conf <<EOF
worker_processes auto;
events { worker_connections 1024; }

http {
    include mime.types;
    default_type application/octet-stream;
    sendfile on;
    keepalive_timeout 65;

    server {
        listen 80;
        server_name _;
        root $KANBAN_DIR/frontend;
        index index.html;
        charset utf-8;

        add_header X-Content-Type-Options  "nosniff"                          always;
        add_header X-Frame-Options         "DENY"                             always;
        add_header Referrer-Policy         "strict-origin-when-cross-origin"  always;
        add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; frame-ancestors 'none'" always;

        location / {
            try_files \$uri \$uri/ /index.html;
        }

        location = /api.php {
            fastcgi_pass unix:/run/php-fpm/kanban.sock;
            fastcgi_param SCRIPT_FILENAME $KANBAN_DIR/backend/api.php;
            include fastcgi_params;
        }

        location ~ \.(db|env)$ { deny all; }
    }
}
EOF

echo "==> Generating API key..."
if [ ! -f "$KANBAN_DIR/.env" ]; then
    # Use a pre-set key if provided, otherwise generate one
    if [ -n "$KANBAN_API_KEY" ]; then
        echo "    Using provided KANBAN_API_KEY"
    else
        KANBAN_API_KEY=$(openssl rand -hex 32)
    fi
    echo "KANBAN_API_KEY=$KANBAN_API_KEY" > "$KANBAN_DIR/.env"
    chown "$KANBAN_USER:$KANBAN_USER" "$KANBAN_DIR/.env"
    chmod 600 "$KANBAN_DIR/.env"
else
    echo "    .env already exists, skipping key generation"
fi

echo "==> Setting permissions..."
mkdir -p /run/php-fpm /var/log/php-fpm

# nginx/http needs execute permission on every directory in the path
chmod o+x "$(dirname "$KANBAN_DIR")"
chmod 755 "$KANBAN_DIR"

if [ -f "$KANBAN_DIR/kanban.db" ]; then
    chmod 664 "$KANBAN_DIR/kanban.db"
    chown "$KANBAN_USER:$KANBAN_USER" "$KANBAN_DIR/kanban.db"
fi

echo "==> Enabling and starting services..."
systemctl enable --now php-fpm nginx

echo ""
echo "Done! AgentBoard is running at http://$(hostname -I | awk '{print $1}')"
echo ""
echo "Team API key (share with teammates):"
grep KANBAN_API_KEY "$KANBAN_DIR/.env" | cut -d= -f2
echo ""
echo "MCP env var for ~/.claude/settings.json:"
echo "  \"KANBAN_API_KEY\": \"$(grep KANBAN_API_KEY "$KANBAN_DIR/.env" | cut -d= -f2)\""
