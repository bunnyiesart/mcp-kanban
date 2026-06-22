# mcp-kanban

A self-hosted kanban board with a JSON HTTP API, built for use by humans and AI agents. Ships with an MCP (Model Context Protocol) server so Claude and other LLM toolchains can read and write cards directly.

## What it is

- **Kanban board** — columns + cards, drag-and-drop UI, dark monospace theme
- **REST JSON API** — full CRUD for cards and columns, archive support
- **MCP server** — (coming in this repo) exposes the board as MCP tools so AI agents can manage tasks natively
- **SQLite backend** — zero external database dependencies
- **Self-hosted** — runs on any server with PHP 8+ and a web server

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.0+ |
| SQLite3 extension | bundled with PHP |
| Web server | Apache 2.4+ or Nginx |
| (optional) Node.js | 18+ for the MCP server |

---

## Self-hosted setup

### 1. Clone the repo

```bash
git clone https://github.com/bunnyiesart/mcp-kanban.git
cd mcp-kanban
```

### 2. Configure the base URL

Edit `config.php`:

```php
<?php
define('BASE_URL', 'http://your-server-ip-or-domain');
```

### 3. Apache

Create a vhost or drop in your `DocumentRoot`. The project uses no `.htaccess` — just point the root at the repo directory.

```apache
<VirtualHost *:80>
    ServerName kanban.local
    DocumentRoot /var/www/mcp-kanban
    <Directory /var/www/mcp-kanban>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

Enable and reload:

```bash
sudo a2ensite kanban.local
sudo systemctl reload apache2
```

### 4. Nginx

```nginx
server {
    listen 80;
    server_name kanban.local;
    root /var/www/mcp-kanban;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/kanban.local /etc/nginx/sites-enabled/
sudo systemctl reload nginx
```

### 5. Permissions

The SQLite database file is created automatically on first request. Make sure the web server user can write to the project directory:

```bash
sudo chown -R www-data:www-data /var/www/mcp-kanban
```

### 6. First run

Navigate to `http://your-server` in a browser. The database and default columns (To Do / In Progress / Done) are created automatically.

To seed demo data:

```bash
php seed.php
```

---

## API reference

All requests go to `api.php?action=<action>`.  
GET requests use query params. POST requests send a JSON body.

### Read the board

```
GET /api.php?action=board
```

Returns all columns and their active (non-archived) cards.

### Cards

| Action | Method | Required fields |
|---|---|---|
| `create_card` | POST | `title`, `column_id`, `agent` |
| `update_card` | POST | `id` + any of: `title`, `notes`, `url`, `agent` |
| `move_card` | POST | `id`, `column_id` |
| `archive_card` | POST | `id` |
| `unarchive_card` | POST | `id` |
| `delete_card` | POST | `id` |
| `archived_cards` | GET | — |

Card fields:

| Field | Type | Description |
|---|---|---|
| `id` | int | Auto-assigned |
| `title` | string | Task description |
| `column_id` | int | Which column the card lives in |
| `agent` | string | Who created/owns it |
| `url` | string? | Link to a PR, issue, or file |
| `notes` | string? | Free-text observations |
| `archived` | bool | Hidden from the main board |

### Columns

```
POST /api.php?action=add_column    { "name": "Review" }
POST /api.php?action=delete_col    { "id": 3 }
```

### Example: full card lifecycle

```bash
BASE="http://your-server/api.php"

# Create
curl -s -X POST "$BASE?action=create_card" \
  -H "Content-Type: application/json" \
  -d '{"title":"Fix login bug","column_id":1,"agent":"claude"}'

# Move to In Progress
curl -s -X POST "$BASE?action=move_card" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"column_id":2}'

# Add a note
curl -s -X POST "$BASE?action=update_card" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"notes":"Root cause found: missing session check."}'

# Move to Done
curl -s -X POST "$BASE?action=move_card" \
  -H "Content-Type: application/json" \
  -d '{"id":1,"column_id":3}'
```

---

## MCP server

A Python MCP server in `mcp/` that wraps the REST API as tools for Claude Code, Claude Desktop, and any other MCP-compatible client.

### Install

```bash
pip install -r mcp/requirements.txt
```

Or with [uv](https://github.com/astral-sh/uv) (no virtualenv needed):

```bash
uv pip install -r mcp/requirements.txt
```

### Tools

| Tool | What it does |
|---|---|
| `board_read` | Return all columns and active cards |
| `card_create` | Create a card (title, column_id, notes?, url?) |
| `card_move` | Move a card to a different column |
| `card_update` | Update title / notes / url / agent on a card |
| `card_archive` | Archive a card (hidden, reversible) |
| `card_unarchive` | Restore an archived card |
| `card_delete` | Permanently delete a card |
| `cards_archived` | List all archived cards |
| `column_add` | Add a new column |
| `column_delete` | Delete a column and all its cards |

### Claude Code

Add to `~/.claude/settings.json`:

```json
{
  "mcpServers": {
    "kanban": {
      "command": "python",
      "args": ["/path/to/mcp-kanban/mcp/server.py"],
      "env": {
        "KANBAN_URL": "http://your-server",
        "KANBAN_AGENT": "claude"
      }
    }
  }
}
```

Or with `uv run`:

```json
{
  "mcpServers": {
    "kanban": {
      "command": "uv",
      "args": ["run", "--with", "mcp[cli]", "--with", "httpx", "/path/to/mcp-kanban/mcp/server.py"],
      "env": {
        "KANBAN_URL": "http://your-server",
        "KANBAN_AGENT": "claude"
      }
    }
  }
}
```

### Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{
  "mcpServers": {
    "kanban": {
      "command": "python",
      "args": ["/path/to/mcp-kanban/mcp/server.py"],
      "env": {
        "KANBAN_URL": "http://your-server",
        "KANBAN_AGENT": "claude"
      }
    }
  }
}
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `KANBAN_URL` | `http://localhost` | Base URL of the PHP board server |
| `KANBAN_AGENT` | `claude` | Agent name written to cards when none is specified |

### Test with MCP inspector

```bash
mcp dev mcp/server.py
```

---

## File structure

```
mcp-kanban/
├── index.php       # Frontend UI (single-file SPA)
├── api.php         # REST JSON API
├── db.php          # SQLite connection + auto-migration
├── config.php      # Base URL config
├── schema.sql      # DB schema reference
├── seed.php        # Demo data seeder
├── AGENT.md        # API guide for AI agents
└── mcp/
    ├── server.py   # MCP server (stdio transport)
    └── requirements.txt
```

---

## License

MIT
