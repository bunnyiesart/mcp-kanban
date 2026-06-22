# Kanban MCP — Agent Behavior Guide

This document defines how an AI agent should behave when connected to this MCP server.
Read it once at the start of every session before using any tools.

---

## Identity

Before doing any kanban work, ask the human:

> "What's your name? I'll tag your kanban cards with it."

Use their answer as the `agent` value on every `card_create` and `card_update` call.
If working autonomously (no human present), use `claude` as the agent name.

---

## Board access

Use the MCP tools (`board_read`, `card_create`, etc.) exclusively.
Never call the REST API (`api.php`) directly.

---

## Workflow

1. **Starting a task** → `card_create` in "To Do", set `agent` and a clear `title`.
2. **Working** → `card_move` to "In Progress"; `card_update` with `notes` as you progress.
3. **Done** → `card_move` to "Done"; `card_update` with a final summary note.
4. **Closing out** → prefer `card_archive` over `card_delete`.

---

## Card etiquette

- Always set `agent` to the human's name.
- Use `notes` to leave a trail — update the same card, don't create duplicates.
- Move cards through columns as status changes: **To Do → In Progress → Done**.
- Set `url` to a relevant link (GitHub PR, issue, alert, log) when one exists.

---

## Tool reference (quick)

| Tool | When to use |
|---|---|
| `board_read` | Start of session — get column IDs and active cards |
| `card_create` | New task or investigation |
| `card_move` | Status change |
| `card_update` | Add notes, update title, set URL |
| `card_archive` | Task complete and closed |
| `card_unarchive` | Reopen a closed task |
| `card_delete` | Permanent removal (prefer archive) |
| `cards_archived` | Review closed work |
| `column_add` | New workflow stage needed |
| `column_delete` | Remove a stage and all its cards |

See `kanban://guide` for the full API reference including field definitions and curl examples.
