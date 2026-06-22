#!/bin/bash
set -e

KANBAN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$KANBAN_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo "Error: $ENV_FILE not found. Run setup-server.sh first." >&2
    exit 1
fi

# Use provided key or generate a new one
if [ -n "$KANBAN_API_KEY" ]; then
    NEW_KEY="$KANBAN_API_KEY"
    echo "Using provided key."
else
    NEW_KEY=$(openssl rand -hex 32)
    echo "Generated new key."
fi

# Update .env in place (preserve any other vars)
if grep -q '^KANBAN_API_KEY=' "$ENV_FILE"; then
    sed -i "s|^KANBAN_API_KEY=.*|KANBAN_API_KEY=$NEW_KEY|" "$ENV_FILE"
else
    echo "KANBAN_API_KEY=$NEW_KEY" >> "$ENV_FILE"
fi

# Reload php-fpm so the next request picks up the new key immediately
if systemctl is-active --quiet php-fpm; then
    systemctl reload php-fpm
    echo "php-fpm reloaded."
fi

echo ""
echo "New API key:"
echo "  $NEW_KEY"
echo ""
echo "Share this with teammates — they will need to re-enter it in the lock screen."
