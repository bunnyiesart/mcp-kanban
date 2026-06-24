# syntax=docker/dockerfile:1
FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx supervisor curl sqlite-dev python3 py3-pip \
 && docker-php-ext-install pdo_sqlite

# PHP app
COPY frontend/ /app/frontend/
COPY backend/  /app/backend/

# MCP server
COPY mcp/requirements.txt /app/mcp/requirements.txt
RUN pip3 install --no-cache-dir --break-system-packages -r /app/mcp/requirements.txt
COPY mcp/server.py mcp/AGENT.md mcp/CLAUDE.md /app/mcp/

# nginx / php-fpm / supervisord configs
COPY docker/nginx.conf        /etc/nginx/nginx.conf
COPY docker/supervisord.conf  /etc/supervisord.conf
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf

COPY <<'EOF' /entrypoint.sh
#!/bin/sh
set -e
case "${1:-board}" in
    mcp)
        exec python3 /app/mcp/server.py
        ;;
    board)
        [ -n "$KANBAN_API_KEY" ] || {
            echo "" >&2
            echo "  ERROR: KANBAN_API_KEY is not set." >&2
            echo "  Generate: echo \"KANBAN_API_KEY=\$(openssl rand -hex 32)\" > .env" >&2
            echo "" >&2
            exit 1
        }
        exec /usr/bin/supervisord -c /etc/supervisord.conf
        ;;
    *)
        echo "Usage: docker run <image> [board|mcp]" >&2
        exit 1
        ;;
esac
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
CMD ["board"]
