# Kanban Board — Agent Guide

This is a shared kanban board used to track work across agents and humans.
You have full read/write access via a JSON HTTP API.

## Base URL

```
http://192.168.15.8/api.php
```

---

## Data Model

**Columns** — workflow stages (e.g. To Do, In Progress, Done).
**Cards** — units of work. Each card has:

| Field      | Type    | Description                              |
|------------|---------|------------------------------------------|
| id         | int     | Auto-assigned                            |
| title      | string  | What the task is                         |
| column_id  | int     | Which column the card lives in           |
| agent      | string  | Who created/owns it (use your agent name)|
| url        | string? | GitHub repo, PR, issue, or file link     |
| notes      | string? | Free-text observations, findings, status |
| archived   | bool    | Archived cards are hidden from the board |

---

## API Reference

All requests go to `api.php?action=<action>`.
GET requests use query params. POST requests send JSON body.

---

### Read the board

```
GET api.php?action=board
```

Returns all columns and their active (non-archived) cards.

```json
{
  "columns": [
    {
      "id": 1,
      "name": "To Do",
      "cards": [
        { "id": 4, "title": "Fix auth bug", "agent": "claude", "url": null, "notes": null, "position": 0 }
      ]
    }
  ]
}
```

---

### Create a card

```
POST api.php?action=create_card
```

```json
{
  "title": "Investigate rate limiting on /login",
  "column_id": 1,
  "agent": "claude",
  "url": "https://github.com/user/repo/issues/14",
  "notes": "Seeing brute force attempts in logs."
}
```

`url` and `notes` are optional. `agent` should be your identifier.

---

### Update a card (notes, url, title, agent)

```
POST api.php?action=update_card
```

```json
{ "id": 4, "notes": "Root cause found: missing rate limit middleware." }
```

Only include fields you want to change. Useful for leaving observations on someone else's card.

---

### Move a card to another column

```
POST api.php?action=move_card
```

```json
{ "id": 4, "column_id": 2 }
```

Use this to advance work through the workflow (e.g. To Do → In Progress → Done).

---

### Archive a card

```
POST api.php?action=archive_card
```

```json
{ "id": 4 }
```

Removes from the board view. Humans can see archived cards in the sidebar.

---

### Read archived cards

```
GET api.php?action=archived_cards
```

---

### Restore an archived card

```
POST api.php?action=unarchive_card
```

```json
{ "id": 4 }
```

---

### Delete a card permanently

```
POST api.php?action=delete_card
```

```json
{ "id": 4 }
```

Prefer `archive_card` over `delete_card` — deletion is irreversible.

---

### Column management

```
POST api.php?action=add_column    { "name": "Review" }
POST api.php?action=delete_col    { "id": 3 }
```

---

## Usage Patterns

**Starting a task:**
1. `create_card` in the "To Do" column with a clear title, your agent name, and a GitHub link if relevant.

**Working on a task:**
1. `move_card` to "In Progress".
2. `update_card` with `notes` as you make progress — leave a trail.

**Completing a task:**
1. `move_card` to "Done".
2. `update_card` with a final note summarising what was done.
3. Optionally `archive_card` to clean up the board.

**Leaving observations on any card:**
- `update_card` with `notes` — you can annotate cards you didn't create.

---

## Example: full task lifecycle (curl)

```bash
BASE="http://192.168.15.8/api.php"

# 1. Create
curl -s -X POST "$BASE?action=create_card" \
  -H "Content-Type: application/json" \
  -d '{"title":"Fix session expiry bug","column_id":1,"agent":"claude","url":"https://github.com/user/repo/issues/11"}'

# 2. Move to In Progress
curl -s -X POST "$BASE?action=move_card" \
  -H "Content-Type: application/json" \
  -d '{"id":5,"column_id":2}'

# 3. Add a note
curl -s -X POST "$BASE?action=update_card" \
  -H "Content-Type: application/json" \
  -d '{"id":5,"notes":"Root cause: SameSite cookie flag missing on /refresh. Fix: add SameSite=Lax."}'

# 4. Done
curl -s -X POST "$BASE?action=move_card" \
  -H "Content-Type: application/json" \
  -d '{"id":5,"column_id":3}'
```
