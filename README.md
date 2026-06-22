```
   ▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄▄
   █                                                   █
   █   ██████╗  ██████╗ ███████╗███╗   ██╗████████╗   █
   █   ██╔══██╗██╔════╝ ██╔════╝████╗  ██║╚══██╔══╝   █
   █   ███████║██║  ███╗█████╗  ██╔██╗ ██║   ██║       █
   █   ██╔══██║██║   ██║██╔══╝  ██║╚██╗██║   ██║       █
   █   ██║  ██║╚██████╔╝███████╗██║ ╚████║   ██║       █
   █   ╚═╝  ╚═╝ ╚═════╝ ╚══════╝╚═╝  ╚═══╝   ╚═╝       █
   █                                                   █
   █   ██████╗  ██████╗  █████╗ ██████╗ ██████╗        █
   █   ██╔══██╗██╔═══██╗██╔══██╗██╔══██╗██╔══██╗       █
   █   ██████╔╝██║   ██║███████║██████╔╝██║  ██║       █
   █   ██╔══██╗██║   ██║██╔══██║██╔══██╗██║  ██║       █
   █   ██████╔╝╚██████╔╝██║  ██║██║  ██║██████╔╝       █
   █   ╚═════╝  ╚═════╝ ╚═╝  ╚═╝╚═╝  ╚═╝╚═════╝        █
   █                                                   █
   █   MCP-native kanban board for AI agents           █
   █   and their humans.                               █
   █                                                   █
   ▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀▀
```

```
  ┌─── To Do ─────────┐  ┌─── In Progress ───┐  ┌─── Done ──────────┐
  │ ┌───────────────┐ │  │ ┌───────────────┐ │  │ ┌───────────────┐ │
  │ │ Hunt C2 bcn   │ │  │ │ Patch bypass  │ │  │ │ Setup MCP     │ │
  │ │ @gabriel      │ │  │ │ @claude       │ │  │ │ @team         │ │
  │ └───────────────┘ │  │ └───────────────┘ │  │ └───────────────┘ │
  │ ┌───────────────┐ │  └───────────────────┘  └───────────────────┘
  │ │ Review alerts │ │
  │ │ @gabriel      │ │  < drag & drop >   < AI agents write cards >
  │ └───────────────┘ │
  └───────────────────┘
```

---

AgentBoard is a self-hosted kanban board with a JSON REST API and a full MCP (Model Context Protocol) server. Human teammates manage cards in the browser. AI agents — Claude, GPT, any MCP client — read and write cards natively via tools. Built for small blue teams that work alongside AI.

---

## Features

- **Kanban UI** — columns + cards, drag-and-drop, live updates, lock screen
- **REST JSON API** — full CRUD for cards and columns, archive support
- **MCP server** — 10 tools + 2 context resources, Dockerised for easy deployment
- **API key auth** — single shared key for the team; `hash_equals` timing-safe check
- **SQLite backend** — zero external database dependencies
- **Self-hosted** — PHP 8 + Nginx on any Linux server

---

## Requirements

| Component | Version |
|---|---|
| PHP | 8.0+ with SQLite3 |
| Nginx (or Apache) | any recent version |
| Python | 3.11+ (MCP server only) |
| Docker | optional, for the MCP image |

---

## Board setup

### 1. Clone

```bash
git clone https://github.com/bunnyiesart/mcp-kanban.git
cd mcp-kanban
```

### 2. API key

Generate a key and write it to `.env`:

```bash
openssl rand -hex 32 > /tmp/k && echo "KANBAN_API_KEY=$(cat /tmp/k)" > .env && rm /tmp/k
cat .env   # KANBAN_API_KEY=<64-char hex>
```

The `.env` file is gitignored. Keep it out of version control.

### 3. Nginx

```nginx
server {
    listen 80;
    server_name kanban.yourdomain.com;
    root /var/www/agentboard/frontend;
    index index.html;

    add_header X-Content-Type-Options  "nosniff"                          always;
    add_header X-Frame-Options         "DENY"                             always;
    add_header Referrer-Policy         "strict-origin-when-cross-origin"  always;
    add_header Content-Security-Policy "default-src 'self'; script-src 'self'; style-src 'self'; frame-ancestors 'none'" always;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location = /api.php {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME /var/www/agentboard/backend/api.php;
        include fastcgi_params;
    }

    location ~ \.(db|env)$ { deny all; }
}
```

```bash
sudo chown -R www-data:www-data /var/www/agentboard
sudo systemctl reload nginx
```

### 4. First run

Navigate to `http://your-server`. The lock screen asks for the API key. The database and default columns (To Do / In Progress / Done) are created on first request.

### 5. Apache (alternative)

```apache
<VirtualHost *:80>
    ServerName kanban.yourdomain.com
    DocumentRoot /var/www/agentboard
    <Directory /var/www/agentboard>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

---

## MCP server

The MCP server in `mcp/` wraps the REST API as tools for Claude Code, Claude Desktop, and any other MCP client. It also exposes two resources — a behavior guide and a full API reference — that AI agents can read at session start.

### Docker (recommended)

```bash
docker pull ghcr.io/bunnyiesart/mcp-kanban:latest
```

Run as a stdio MCP server:

```bash
docker run -i --rm \
  -e KANBAN_URL=http://your-server \
  -e KANBAN_API_KEY=your-api-key \
  ghcr.io/bunnyiesart/mcp-kanban:latest
```

### Claude Code — `~/.claude/settings.json`

```json
{
  "mcpServers": {
    "agentboard": {
      "command": "docker",
      "args": [
        "run", "-i", "--rm",
        "-e", "KANBAN_URL=http://your-server",
        "-e", "KANBAN_API_KEY=your-api-key",
        "ghcr.io/bunnyiesart/mcp-kanban:latest"
      ]
    }
  }
}
```

Without Docker (Python):

```json
{
  "mcpServers": {
    "agentboard": {
      "command": "python",
      "args": ["/path/to/agentboard/mcp/server.py"],
      "env": {
        "KANBAN_URL": "http://your-server",
        "KANBAN_API_KEY": "your-api-key"
      }
    }
  }
}
```

### Claude Desktop — `claude_desktop_config.json`

macOS: `~/Library/Application Support/Claude/claude_desktop_config.json`  
Windows: `%APPDATA%\Claude\claude_desktop_config.json`

```json
{
  "mcpServers": {
    "agentboard": {
      "command": "docker",
      "args": [
        "run", "-i", "--rm",
        "-e", "KANBAN_URL=http://your-server",
        "-e", "KANBAN_API_KEY=your-api-key",
        "ghcr.io/bunnyiesart/mcp-kanban:latest"
      ]
    }
  }
}
```

### Install without Docker

```bash
pip install -r mcp/requirements.txt
KANBAN_URL=http://your-server KANBAN_API_KEY=your-key python mcp/server.py
```

### Environment variables

| Variable | Default | Description |
|---|---|---|
| `KANBAN_URL` | `http://localhost` | Base URL of the board |
| `KANBAN_API_KEY` | *(required)* | Shared API key |
| `KANBAN_AGENT` | `claude` | Default agent name if none given |

### MCP tools

| Tool | What it does |
|---|---|
| `board_read` | Return all columns and active cards |
| `card_create` | Create a card (`title`, `column_id`, `agent`, `notes?`, `url?`) |
| `card_move` | Move a card to a different column |
| `card_update` | Update title / notes / url / agent |
| `card_archive` | Archive a card (hidden, reversible) |
| `card_unarchive` | Restore an archived card |
| `card_delete` | Permanently delete a card |
| `cards_archived` | List all archived cards |
| `column_add` | Add a new column |
| `column_delete` | Delete a column and all its cards |

### MCP resources

| URI | Description |
|---|---|
| `kanban://session` | Agent behavior guide — ask for name, workflow, card etiquette |
| `kanban://guide` | Full API reference with field definitions and curl examples |

### Test with MCP inspector

```bash
mcp dev mcp/server.py
```

---

## REST API

All requests go to `api.php?action=<action>`.  
Every request must include the `X-Api-Key` header.

### Read the board

```
GET /api.php?action=board
X-Api-Key: your-api-key
```

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
| `agent` | string | Who owns it (human name or `claude`) |
| `url` | string? | Link to a PR, issue, alert, or log |
| `notes` | string? | Free-text trail of progress |
| `archived` | bool | Hidden from the main board |

### Columns

```
POST /api.php?action=add_column    { "name": "Review" }
POST /api.php?action=delete_col    { "id": 3 }
```

### Example: full card lifecycle

```bash
BASE="http://your-server/api.php"
KEY="your-api-key"

# Create
curl -s -X POST "$BASE?action=create_card" \
  -H "Content-Type: application/json" -H "X-Api-Key: $KEY" \
  -d '{"title":"Hunt C2 beacon","column_id":1,"agent":"gabriel"}'

# Move to In Progress
curl -s -X POST "$BASE?action=move_card" \
  -H "Content-Type: application/json" -H "X-Api-Key: $KEY" \
  -d '{"id":1,"column_id":2}'

# Add a note
curl -s -X POST "$BASE?action=update_card" \
  -H "Content-Type: application/json" -H "X-Api-Key: $KEY" \
  -d '{"id":1,"notes":"Found beacon on port 4444 — isolating host."}'

# Done
curl -s -X POST "$BASE?action=move_card" \
  -H "Content-Type: application/json" -H "X-Api-Key: $KEY" \
  -d '{"id":1,"column_id":3}'
```

---

## Team usage

AgentBoard is designed for a small team where humans and AI agents share the same board:

- **Humans** unlock the board with the shared API key and drag cards between columns in the browser.
- **AI agents** (Claude, etc.) read `kanban://session` at session start, ask for the operator's name, and tag every card they create with it so work is always attributable.
- All cards have an `agent` field — the name of whoever (human or AI) owns the work.
- Prefer `card_archive` over `card_delete` to preserve history.

---

## File structure

```
agentboard/
├── frontend/               # Static web UI (served as document root)
│   ├── index.html          # HTML shell — lock screen + board layout
│   ├── style.css           # All styles
│   └── app.js              # All behaviour — API calls, drag-and-drop, render
├── backend/                # PHP API (never served directly; routed via Nginx)
│   ├── api.php             # REST JSON API + auth middleware
│   ├── db.php              # SQLite init + auto-migration
│   └── config.php          # .env loader, API_KEY + DB_PATH constants
├── mcp/                    # MCP server (Docker or Python)
│   ├── server.py           # 10 tools + 2 resources (stdio transport)
│   ├── CLAUDE.md           # Agent behavior guide  →  kanban://session
│   ├── AGENT.md            # Full API reference    →  kanban://guide
│   ├── Dockerfile          # ghcr.io/bunnyiesart/mcp-kanban
│   ├── requirements.txt
│   └── .dockerignore
├── kanban.db               # SQLite database (gitignored, auto-created)
├── .env                    # KANBAN_API_KEY=... (gitignored)
├── .env.example            # Template
└── setup-server.sh         # Arch Linux one-shot setup script
```

---

## Security

- Single shared API key loaded from `.env` (never committed).
- Every request validated with `hash_equals()` — timing-safe comparison.
- Lock screen uses `sessionStorage` — key clears when the tab closes.
- Security headers on all responses: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy`.

---

## License

MIT
