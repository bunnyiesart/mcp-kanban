<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

$body = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
}

function json_ok(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function json_err(string $msg, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['error' => $msg]);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
if (API_KEY === '') json_err('Server misconfigured: KANBAN_API_KEY not set', 503);
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($provided === '' || !hash_equals(API_KEY, $provided)) json_err('Unauthorized', 401);
// ─────────────────────────────────────────────────────────────────────────────

try {
    $db = db();

    // GET board — returns all columns with their cards ordered by position
    if ($method === 'GET' && $action === 'board') {
        $cols = $db->query('SELECT id, name, position FROM `columns` ORDER BY position, id')->fetchAll();
        foreach ($cols as &$col) {
            $stmt = $db->prepare('SELECT id, title, agent, url, notes, position FROM cards WHERE column_id = ? AND archived = 0 ORDER BY position, id');
            $stmt->execute([$col['id']]);
            $col['cards'] = $stmt->fetchAll();
        }
        json_ok(['columns' => $cols]);
    }

    // POST create_card — { title, column_id, agent, url? }
    if ($method === 'POST' && $action === 'create_card') {
        $title     = trim($body['title'] ?? '');
        $column_id = (int)($body['column_id'] ?? 0);
        $agent     = trim($body['agent'] ?? 'unknown');
        $url       = trim($body['url'] ?? '') ?: null;
        $notes     = isset($body['notes']) ? trim($body['notes']) ?: null : null;
        if ($title === '') json_err('title is required');
        if ($column_id < 1) json_err('column_id is required');

        $pos = $db->prepare('SELECT COALESCE(MAX(position),0)+1 FROM cards WHERE column_id = ?');
        $pos->execute([$column_id]);
        $position = (int)$pos->fetchColumn();

        $stmt = $db->prepare('INSERT INTO cards (title, column_id, agent, url, notes, position) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$title, $column_id, $agent, $url, $notes, $position]);
        $id = (int)$db->lastInsertId();
        json_ok(['id' => $id, 'title' => $title, 'column_id' => $column_id, 'agent' => $agent, 'url' => $url, 'notes' => $notes], 201);
    }

    // POST move_card — { id, column_id }
    if ($method === 'POST' && $action === 'move_card') {
        $id        = (int)($body['id'] ?? 0);
        $column_id = (int)($body['column_id'] ?? 0);
        if ($id < 1)        json_err('id is required');
        if ($column_id < 1) json_err('column_id is required');

        $pos = $db->prepare('SELECT COALESCE(MAX(position),0)+1 FROM cards WHERE column_id = ?');
        $pos->execute([$column_id]);
        $position = (int)$pos->fetchColumn();

        $stmt = $db->prepare('UPDATE cards SET column_id = ?, position = ? WHERE id = ?');
        $stmt->execute([$column_id, $position, $id]);
        if ($stmt->rowCount() === 0) json_err('card not found', 404);
        json_ok(['ok' => true]);
    }

    // POST update_card — { id, title?, notes?, url?, agent? }
    if ($method === 'POST' && $action === 'update_card') {
        $id    = (int)($body['id'] ?? 0);
        if ($id < 1) json_err('id is required');

        $sets = [];
        $params = [];
        if (isset($body['title'])) {
            $title = trim($body['title']);
            if ($title === '') json_err('title cannot be empty');
            $sets[] = 'title = ?';
            $params[] = $title;
        }
        if (isset($body['agent'])) {
            $sets[] = 'agent = ?';
            $params[] = trim($body['agent']);
        }
        if (array_key_exists('url', $body)) {
            $sets[] = 'url = ?';
            $params[] = trim($body['url']) ?: null;
        }
        if (array_key_exists('notes', $body)) {
            $sets[] = 'notes = ?';
            $params[] = trim($body['notes']) ?: null;
        }
        if (empty($sets)) json_err('nothing to update');
        $params[] = $id;

        $stmt = $db->prepare('UPDATE cards SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) json_err('card not found', 404);
        json_ok(['ok' => true]);
    }

    // POST delete_card — { id }
    if ($method === 'POST' && $action === 'delete_card') {
        $id = (int)($body['id'] ?? 0);
        if ($id < 1) json_err('id is required');

        $stmt = $db->prepare('DELETE FROM cards WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_err('card not found', 404);
        json_ok(['ok' => true]);
    }

    // POST add_column — { name }
    if ($method === 'POST' && $action === 'add_column') {
        $name = trim($body['name'] ?? '');
        if ($name === '') json_err('name is required');

        $pos = $db->query('SELECT COALESCE(MAX(position),0)+1 FROM `columns`')->fetchColumn();
        $stmt = $db->prepare('INSERT INTO `columns` (name, position) VALUES (?, ?)');
        $stmt->execute([$name, (int)$pos]);
        $id = (int)$db->lastInsertId();
        json_ok(['id' => $id, 'name' => $name], 201);
    }

    // POST archive_card — { id }
    if ($method === 'POST' && $action === 'archive_card') {
        $id = (int)($body['id'] ?? 0);
        if ($id < 1) json_err('id is required');
        $stmt = $db->prepare('UPDATE cards SET archived = 1 WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_err('card not found', 404);
        json_ok(['ok' => true]);
    }

    // POST unarchive_card — { id }
    if ($method === 'POST' && $action === 'unarchive_card') {
        $id = (int)($body['id'] ?? 0);
        if ($id < 1) json_err('id is required');
        $stmt = $db->prepare('UPDATE cards SET archived = 0 WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_err('card not found', 404);
        json_ok(['ok' => true]);
    }

    // GET archived_cards — returns archived cards with their column name
    if ($method === 'GET' && $action === 'archived_cards') {
        $stmt = $db->query('SELECT c.id, c.title, c.agent, c.url, c.notes, c.created_at, col.name AS column_name FROM cards c JOIN "columns" col ON col.id = c.column_id WHERE c.archived = 1 ORDER BY c.id DESC');
        json_ok(['cards' => $stmt->fetchAll()]);
    }

    // POST delete_col — { id }
    if ($method === 'POST' && $action === 'delete_col') {
        $id = (int)($body['id'] ?? 0);
        if ($id < 1) json_err('id is required');

        $stmt = $db->prepare('DELETE FROM `columns` WHERE id = ?');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) json_err('column not found', 404);
        json_ok(['ok' => true]);
    }

    json_err('unknown action', 404);

} catch (PDOException $e) {
    json_err('db error: ' . $e->getMessage(), 500);
}
