#!/bin/bash
set -e

KANBAN_DIR=/home/gabriel/kanban
KANBAN_USER=gabriel

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
        root $KANBAN_DIR;
        index index.php;
        charset utf-8;

        location / {
            try_files \$uri \$uri/ /index.php?\$query_string;
        }

        location ~ \.php$ {
            fastcgi_pass unix:/run/php-fpm/kanban.sock;
            fastcgi_index index.php;
            fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
            include fastcgi_params;
        }

        # Block direct access to the SQLite DB
        location ~ \.db$ { deny all; }
    }
}
EOF

echo "==> Setting permissions..."
mkdir -p /run/php-fpm /var/log/php-fpm
chmod 755 /home/gabriel        # nginx needs to traverse into home
chmod 755 $KANBAN_DIR
chmod 664 $KANBAN_DIR/kanban.db
chown $KANBAN_USER:$KANBAN_USER $KANBAN_DIR/kanban.db

echo "==> Enabling and starting services..."
systemctl enable --now php-fpm nginx

echo ""
echo "Done! Kanban is running at http://$(hostname -I | awk '{print $1}')"
EOF

chmod +x /home/gabriel/kanban/setup-server.sh