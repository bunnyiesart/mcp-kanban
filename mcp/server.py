import os
from typing import Optional
import httpx
from mcp.server.fastmcp import FastMCP

_base_url = os.environ.get("KANBAN_URL", "http://localhost").rstrip("/")
_api = _base_url + "/api.php"
_agent = os.environ.get("KANBAN_AGENT", "claude")
_api_key = os.environ.get("KANBAN_API_KEY", "")
_headers = {"X-Api-Key": _api_key} if _api_key else {}

mcp = FastMCP("kanban")


def _get(action: str) -> dict | list:
    r = httpx.get(_api, params={"action": action}, headers=_headers, timeout=10)
    data = r.json()
    if r.is_error or "error" in data:
        raise RuntimeError(data.get("error", f"HTTP {r.status_code}"))
    return data


def _post(action: str, payload: dict) -> dict:
    r = httpx.post(_api, params={"action": action}, json=payload, headers=_headers, timeout=10)
    data = r.json()
    if r.is_error or "error" in data:
        raise RuntimeError(data.get("error", f"HTTP {r.status_code}"))
    return data


@mcp.tool()
def board_read() -> dict:
    """Return all columns and their active (non-archived) cards."""
    return _get("board")


@mcp.tool()
def card_create(
    title: str,
    column_id: int,
    agent: str,
    notes: Optional[str] = None,
    url: Optional[str] = None,
) -> dict:
    """Create a new card. agent must be the name of the person doing the work (ask if unknown)."""
    payload = {
        "title": title,
        "column_id": column_id,
        "agent": agent or _agent,
    }
    if notes is not None:
        payload["notes"] = notes
    if url is not None:
        payload["url"] = url
    return _post("create_card", payload)


@mcp.tool()
def card_move(id: int, column_id: int) -> dict:
    """Move a card to a different column."""
    return _post("move_card", {"id": id, "column_id": column_id})


@mcp.tool()
def card_update(
    id: int,
    title: Optional[str] = None,
    notes: Optional[str] = None,
    url: Optional[str] = None,
    agent: Optional[str] = None,
) -> dict:
    """Update one or more fields on a card. Only provided fields are changed."""
    payload: dict = {"id": id}
    if title is not None:
        payload["title"] = title
    if notes is not None:
        payload["notes"] = notes
    if url is not None:
        payload["url"] = url
    if agent is not None:
        payload["agent"] = agent
    return _post("update_card", payload)


@mcp.tool()
def card_archive(id: int) -> dict:
    """Archive a card (hides it from the board; reversible with card_unarchive)."""
    return _post("archive_card", {"id": id})


@mcp.tool()
def card_unarchive(id: int) -> dict:
    """Restore an archived card back to the board."""
    return _post("unarchive_card", {"id": id})


@mcp.tool()
def card_delete(id: int) -> dict:
    """Permanently delete a card. Prefer card_archive — this cannot be undone."""
    return _post("delete_card", {"id": id})


@mcp.tool()
def cards_archived() -> dict:
    """Return all archived cards with their original column name."""
    return _get("archived_cards")


@mcp.tool()
def column_add(name: str) -> dict:
    """Add a new column to the board."""
    return _post("add_column", {"name": name})


@mcp.tool()
def column_delete(id: int) -> dict:
    """Delete a column and all its cards permanently."""
    return _post("delete_col", {"id": id})


if __name__ == "__main__":
    mcp.run()
