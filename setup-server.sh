#!/bin/bash
set -e

KANBAN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
KANBAN_USER="$(stat -c '%U' "$KANBAN_DIR")"

echo "==> AgentBoard setup"
echo "    Directory : $KANBAN_DIR"
echo "    User      : $KANBAN_USER"
echo ""

# ── Detect distro ──────────────────────────────────────────────────────────────
if   command -v pacman  &>/dev/null; then DISTRO=arch
elif command -v apt-get &>/dev/null; then DISTRO=debian
elif command -v dnf     &>/dev/null; then DISTRO=fedora
else
    echo "ERROR: No supported package manager found (pacman / apt-get / dnf)." >&2
    echo "       Use Docker instead:" >&2
    echo "         cp .env.example .env" >&2
    echo "         echo \"KANBAN_API_KEY=\$(openssl rand -hex 32)\" >> .env" >&2
    echo "         docker compose up -d" >&2
    exit 1
fi

echo "==> Detected distro: $DISTRO"
echo ""

# ── Install packages ──────────────────────────────────────────────────────────
echo "==> Installing nginx and php-fpm..."
case "$DISTRO" in
    arch)
        pacman -S --noconfirm nginx php-fpm
        ;;
    debian)
        export DEBIAN_FRONTEND=noninteractive
        apt-get update -qq
        apt-get install -y nginx php-fpm
        ;;
    fedora)
        dnf install -y nginx php-fpm php-pdo
        ;;
esac

# ── Per-distro variables ──────────────────────────────────────────────────────
case "$DISTRO" in
    arch)
        FPM_SERVICE=php-fpm
        FPM_POOL_DIR=/etc/php/php-fpm.d
        FPM_SOCK=/run/php-fpm/kanban.sock
        FPM_SOCK_DIR=/run/php-fpm
        FPM_LOG_DIR=/var/log/php-fpm
        NGINX_USER=http
        ;;
    debian)
        PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
        FPM_SERVICE="php${PHP_VER}-fpm"
        FPM_POOL_DIR="/etc/php/${PHP_VER}/fpm/pool.d"
        FPM_SOCK="/run/php/php${PHP_VER}-fpm-kanban.sock"
        FPM_SOCK_DIR=/run/php
        FPM_LOG_DIR=/var/log/php-fpm
        NGINX_USER=www-data
        ;;
    fedora)
        FPM_SERVICE=php-fpm
        FPM_POOL_DIR=/etc/php-fpm.d
        FPM_SOCK=/run/php-fpm/kanban.sock
        FPM_SOCK_DIR=/run/php-fpm
        FPM_LOG_DIR=/var/log/php-fpm
        NGINX_USER=nginx
        ;;
esac

# ── PHP-FPM pool config ───────────────────────────────────────────────────────
echo "==> Writing php-fpm pool config..."

# On Arch the package ships php-fpm.conf; write it if missing
if [ "$DISTRO" = "arch" ]; then
    if [ ! -f /etc/php/php-fpm.conf ]; then
        cat > /etc/php/php-fpm.conf <<'CONF'
[global]
pid = /run/php-fpm/php-fpm.pid
error_log = /var/log/php-fpm/error.log
include = /etc/php/php-fpm.d/*.conf
CONF
    fi
fi

mkdir -p "$FPM_POOL_DIR"

# Disable the distro's default pool so it doesn't compete with ours
for f in "$FPM_POOL_DIR/www.conf" "$FPM_POOL_DIR/www.conf.rpmsave"; do
    [ -f "$f" ] && mv "$f" "${f}.disabled" && echo "    (disabled default pool: $f)"
done

cat > "$FPM_POOL_DIR/kanban.conf" <<CONF
[kanban]
user = $KANBAN_USER
group = $KANBAN_USER
listen = $FPM_SOCK
listen.owner = $NGINX_USER
listen.group = $NGINX_USER
listen.mode = 0660
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 5
php_admin_value[error_log] = $FPM_LOG_DIR/kanban-error.log
php_admin_flag[log_errors] = on
CONF

# ── nginx config ──────────────────────────────────────────────────────────────
echo "==> Writing nginx config..."
cat > /etc/nginx/nginx.conf <<CONF
user $NGINX_USER;
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
            fastcgi_pass unix:$FPM_SOCK;
            fastcgi_param SCRIPT_FILENAME $KANBAN_DIR/backend/api.php;
            include fastcgi_params;
        }

        location ~ \.(db|env)$ { deny all; }
    }
}
CONF

# ── API key ───────────────────────────────────────────────────────────────────
echo "==> Generating API key..."
if [ ! -f "$KANBAN_DIR/.env" ]; then
    KANBAN_API_KEY="${KANBAN_API_KEY:-$(openssl rand -hex 32)}"
    echo "KANBAN_API_KEY=$KANBAN_API_KEY" > "$KANBAN_DIR/.env"
    chown "$KANBAN_USER:$KANBAN_USER" "$KANBAN_DIR/.env"
    chmod 600 "$KANBAN_DIR/.env"
else
    echo "    .env already exists, skipping key generation"
fi

# ── Permissions ───────────────────────────────────────────────────────────────
echo "==> Setting permissions..."
mkdir -p "$FPM_SOCK_DIR" "$FPM_LOG_DIR"

# nginx workers need execute on every directory in the path
chmod o+x "$(dirname "$KANBAN_DIR")"
chmod 755 "$KANBAN_DIR"

if [ -f "$KANBAN_DIR/kanban.db" ]; then
    chmod 664 "$KANBAN_DIR/kanban.db"
    chown "$KANBAN_USER:$KANBAN_USER" "$KANBAN_DIR/kanban.db"
fi

# ── Start services ────────────────────────────────────────────────────────────
echo "==> Enabling and starting services..."
systemctl enable --now "$FPM_SERVICE" nginx

echo ""
echo "Done! AgentBoard is running at http://$(hostname -I | awk '{print $1}')"
echo ""
echo "Team API key (share with teammates):"
grep KANBAN_API_KEY "$KANBAN_DIR/.env" | cut -d= -f2
echo ""
echo "MCP env var for ~/.claude/settings.json:"
echo "  \"KANBAN_API_KEY\": \"$(grep KANBAN_API_KEY "$KANBAN_DIR/.env" | cut -d= -f2)\""
