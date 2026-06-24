# syntax=docker/dockerfile:1
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor curl \
 && docker-php-ext-install pdo_sqlite

COPY frontend/ /app/frontend/
COPY backend/  /app/backend/

COPY docker/nginx.conf        /etc/nginx/nginx.conf
COPY docker/supervisord.conf  /etc/supervisord.conf
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

COPY <<'EOF' /entrypoint.sh
#!/bin/sh
set -e
if [ -z "$KANBAN_API_KEY" ]; then
    echo "" >&2
    echo "  ERROR: KANBAN_API_KEY is not set." >&2
    echo "  Generate one and add it to a .env file:" >&2
    echo "    echo \"KANBAN_API_KEY=\$(openssl rand -hex 32)\" > .env" >&2
    echo "  Then re-run: docker compose up" >&2
    echo "" >&2
    exit 1
fi
exec /usr/bin/supervisord -c /etc/supervisord.conf
EOF

RUN chmod +x /entrypoint.sh \
 && mkdir -p /data \
 && chown www-data:www-data /data

VOLUME /data
ENV KANBAN_DB_PATH=/data/kanban.db

EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --start-period=10s --retries=3 \
  CMD curl -fs http://localhost/ > /dev/null || exit 1

ENTRYPOINT ["/entrypoint.sh"]
