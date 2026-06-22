<?php
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $new = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA journal_mode = WAL');

    if ($new) _create_schema($pdo);
    return $pdo;
}

function _create_schema(PDO $db): void {
    $db->exec("
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
            archived   INTEGER NOT NULL DEFAULT 0,
            created_at TEXT DEFAULT (datetime('now')),
            FOREIGN KEY (column_id) REFERENCES columns(id) ON DELETE CASCADE
        );

        INSERT INTO columns (name, position) VALUES
            ('To Do',       0),
            ('In Progress', 1),
            ('Done',        2);
    ");
}
