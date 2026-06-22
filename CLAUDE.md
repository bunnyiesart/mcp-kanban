# Kanban — Session Instructions

## Who are you?

At the start of every session in this project, before doing any kanban work, ask the user:

> "What's your name? I'll tag your kanban cards with it."

Use their answer as the `agent` value on every `card_create` and `card_update` call for the rest of the session. Do not use the env var default. If the user is working autonomously (no human at the keyboard), use `claude` as the agent name.

## Board access

The board lives at the URL configured in `KANBAN_URL`. Use the MCP tools (`board_read`, `card_create`, etc.) to interact with it — don't call the REST API directly.

## Card etiquette

- Always set `agent` to the user's name (established above).
- Use `notes` to leave a trail as work progresses — update the card rather than creating new ones.
- Prefer `card_archive` over `card_delete` when closing out work.
- Move cards through columns as status changes: To Do → In Progress → Done.
