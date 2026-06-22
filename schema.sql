-- SQLite schema (auto-applied by db.php on first run)
CREATE TABLE columns (
    id       INTEGER PRIMARY KEY AUTOINCREMENT,
    name     TEXT NOT NULL,
    position INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE cards (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    title      TEXT NOT NULL,
    column_id  INTEGER NOT NULL,
    agent      TEXT NOT NULL DEFAULT 'unknown',
    url        TEXT DEFAULT NULL,
    notes      TEXT DEFAULT NULL,
    position   INTEGER NOT NULL DEFAULT 0,
    created_at TEXT DEFAULT (datetime('now')),
    FOREIGN KEY (column_id) REFERENCES columns(id) ON DELETE CASCADE
);

INSERT INTO columns (name, position) VALUES
    ('To Do',       0),
    ('In Progress', 1),
    ('Done',        2);
