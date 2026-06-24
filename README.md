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
- **Self-hosted** — Docker Compose (any Linux/Mac) or bare-metal (Arch, Debian/Ubuntu, Fedora)

---

## Deploy with Docker Compose

The fastest way to get both the board and the MCP server running:

```bash
git clone https://github.com/bunnyiesart/mcp-kanban.git
cd mcp-kanban

cp .env.example .env
echo "KANBAN_API_KEY=$(openssl rand -hex 32)" >> .env

docker compose up -d
```

The board is now at `http://localhost`. The MCP server starts automatically once the board is healthy.

### Environment variables (`.env`)

| Variable | Default | Description |
|---|---|---|
| `KANBAN_API_KEY` | *(required)* | Shared key for the team and all agents |
| `PORT` | `80` | Host port the board listens on |
| `KANBAN_AGENT` | `claude` | Default agent name for the MCP server |

### Images

| Image | What it is |
|---|---|
| `ghcr.io/bunnyiesart/agentboard:latest` | Board — nginx + PHP + SQLite |
| `ghcr.io/bunnyiesart/mcp-kanban:latest` | MCP server — Python, stdio transport |

---

## Bare-metal setup

For direct installation without Docker. Supports **Arch**, **Debian/Ubuntu**, and **Fedora/RHEL**:

```bash
git clone https://github.com/bunnyiesart/mcp-kanban.git
cd mcp-kanban
sudo bash setup-server.sh
```

The script detects your distro, installs nginx and php-fpm, writes the configs, generates an API key, and starts the services. Pass `KANBAN_API_KEY=<key>` to use your own key instead of generating one:

```bash
sudo KANBAN_API_KEY=your-key bash setup-server.sh
```

---

## Managing the API key

### Show the current key

```bash
grep KANBAN_API_KEY .env | cut -d= -f2
```

### Rotate the key

```bash
# Bare-metal
sudo bash rotate-key.sh

# Docker Compose — edit .env, then restart
docker compose restart
```

Pass `KANBAN_API_KEY=<key>` to rotate to a specific value:

```bash
sudo KANBAN_API_KEY=your-key bash rotate-key.sh
```

All active browser sessions hit the lock screen on their next API call and just need to re-enter the new key.

---

## MCP server

The MCP server in `mcp/` wraps the REST API as tools for Claude Code, Claude Desktop, and any MCP client. It exposes two resources — a behavior guide and a full API reference — that agents read at session start.

If you used Docker Compose, the MCP server is already running. Connect to it with:

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

### Without Docker (Python)

```bash
pip install -r mcp/requirements.txt
KANBAN_URL=http://your-server KANBAN_API_KEY=your-key python mcp/server.py
```

Or in `settings.json`:

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
│   ├── index.html
│   ├── style.css
│   └── app.js
├── backend/                # PHP API (never served directly; routed via nginx)
│   ├── api.php             # REST JSON API + auth middleware
│   ├── db.php              # SQLite init + schema
│   └── config.php          # .env loader, DB_PATH + API_KEY constants
├── mcp/                    # MCP server
│   ├── server.py           # 10 tools + 2 resources (stdio transport)
│   ├── CLAUDE.md           # Agent behavior guide  →  kanban://session
│   ├── AGENT.md            # Full API reference    →  kanban://guide
│   ├── Dockerfile          # ghcr.io/bunnyiesart/mcp-kanban
│   └── requirements.txt
├── docker/                 # Config files for the app container
│   ├── nginx.conf
│   ├── supervisord.conf
│   └── php-fpm-pool.conf
├── Dockerfile              # App image — ghcr.io/bunnyiesart/agentboard
├── docker-compose.yml      # Orchestrates app + mcp together
├── .env.example            # Copy to .env and fill in KANBAN_API_KEY
├── setup-server.sh         # Bare-metal setup (Arch / Debian-Ubuntu / Fedora)
└── rotate-key.sh           # Rotates API key in .env and reloads php-fpm
```

---

## Security

- Single shared API key loaded from `.env` (never committed).
- Every request validated with `hash_equals()` — timing-safe comparison.
- Lock screen uses `sessionStorage` — key clears when the tab closes.
- Security headers on all responses: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy`.
- MCP server container runs as a non-root user.

---

## License

MIT
