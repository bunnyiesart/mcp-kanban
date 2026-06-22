<?php
define('DATA_FILE', __DIR__ . '/demo_data.json');

// 4 fixed color slots — one per person
const SLOTS = [
    ['bg' => '#1a2a3a', 'border' => '#3a7bd5', 'text' => '#7ab8f5'],
    ['bg' => '#1a2e1a', 'border' => '#3a9e4a', 'text' => '#7dd67a'],
    ['bg' => '#2e1f0a', 'border' => '#c47a1a', 'text' => '#f0b060'],
    ['bg' => '#251535', 'border' => '#8b4ec8', 'text' => '#c49ef0'],
];

function load(): array {
    if (!file_exists(DATA_FILE)) return default_data();
    return json_decode(file_get_contents(DATA_FILE), true);
}

function save(array $data): void {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT));
}

function default_data(): array {
    return [
        'next_col_id'  => 4,
        'next_card_id' => 1,
        'people'       => [],
        'columns'      => [
            ['id' => 1, 'name' => 'Backlog',     'position' => 0],
            ['id' => 2, 'name' => 'In Progress', 'position' => 1],
            ['id' => 3, 'name' => 'Done',        'position' => 2],
        ],
        'cards' => [],
    ];
}

function find_person(array $data, string $token): ?array {
    foreach ($data['people'] as $p) {
        if ($p['token'] === $token) return $p;
    }
    return null;
}

// Seed file if missing
if (!file_exists(DATA_FILE)) {
    $d = default_data();

    // Pre-register 4 demo people with fixed tokens for seeding
    $names  = ['alice', 'bob', 'carol', 'dave'];
    $tokens = ['tok_alice111', 'tok_bob2222', 'tok_carol33', 'tok_dave444'];
    foreach ($names as $i => $name) {
        $d['people'][] = ['name' => $name, 'token' => $tokens[$i], 'slot' => $i];
    }
    $d['next_card_id'] = 13;

    // Seed cards attributed to demo people by token
    $cards = [
        [1,  'Scrape target site and store raw HTML',          1, 'tok_alice111'],
        [2,  'Design prompt template for extraction pipeline', 1, 'tok_bob2222'],
        [3,  'Set up vector DB schema',                        1, 'tok_carol33'],
        [4,  'Write unit tests for chunking logic',            1, 'tok_dave444'],
        [5,  'Implement retry logic for flaky API calls',      2, 'tok_bob2222'],
        [6,  'Parse and normalize date fields',                2, 'tok_carol33'],
        [7,  'Integrate embedding model into ingestion',       2, 'tok_alice111'],
        [8,  'Draft evaluation rubric for output quality',     2, 'tok_dave444'],
        [9,  'Deploy staging environment',                     3, 'tok_alice111'],
        [10, 'Define data schema v1',                          3, 'tok_bob2222'],
        [11, 'Set up CI pipeline',                             3, 'tok_carol33'],
        [12, 'Write project README',                           3, 'tok_dave444'],
    ];
    foreach ($cards as $i => [$id, $title, $col_id, $token]) {
        $d['cards'][] = ['id' => $id, 'title' => $title, 'column_id' => $col_id, 'token' => $token, 'position' => $i % 4];
    }

    save($d);
}

// ── API ──────────────────────────────────────────────────────────────────────
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

    $action = $_GET['action'];
    $method = $_SERVER['REQUEST_METHOD'];
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];

    function ok(array $d, int $s = 200): void { http_response_code($s); echo json_encode($d); exit; }
    function err(string $m, int $s = 400): void { http_response_code($s); echo json_encode(['error' => $m]); exit; }

    $data = load();

    // GET board — public
    if ($method === 'GET' && $action === 'board') {
        $cols = $data['columns'];
        usort($cols, fn($a,$b) => $a['position'] <=> $b['position']);
        foreach ($cols as &$col) {
            $col['cards'] = array_values(array_filter($data['cards'], fn($c) => $c['column_id'] === $col['id']));
            usort($col['cards'], fn($a,$b) => $a['position'] <=> $b['position']);
            // Attach person info to each card
            foreach ($col['cards'] as &$card) {
                $p = find_person($data, $card['token'] ?? '');
                $card['person_name'] = $p ? $p['name'] : 'unknown';
                $card['person_slot'] = $p ? $p['slot'] : -1;
                unset($card['token']);
            }
        }
        // Return people list (without tokens)
        $people = array_map(fn($p) => ['name' => $p['name'], 'slot' => $p['slot']], $data['people']);
        ok(['columns' => $cols, 'people' => $people]);
    }

    // POST register_person — { name } — max 4
    if ($method === 'POST' && $action === 'register_person') {
        $name = trim($body['name'] ?? '');
        if (!$name) err('name required');
        if (count($data['people']) >= 4) err('person limit reached (max 4)', 403);
        foreach ($data['people'] as $p) {
            if (strtolower($p['name']) === strtolower($name)) err('name already registered', 409);
        }
        $slot  = count($data['people']);
        $token = bin2hex(random_bytes(8));
        $data['people'][] = ['name' => $name, 'token' => $token, 'slot' => $slot];
        save($data);
        ok(['name' => $name, 'token' => $token, 'slot' => $slot], 201);
    }

    // ── Token-gated write endpoints ──────────────────────────────────────────

    // POST create_card — { title, column_id, token }
    if ($method === 'POST' && $action === 'create_card') {
        $token = trim($body['token'] ?? '');
        $person = find_person($data, $token);
        if (!$person) err('invalid token', 401);

        $title = trim($body['title'] ?? '');
        $col   = (int)($body['column_id'] ?? 0);
        if (!$title) err('title required');
        if (!$col)   err('column_id required');

        $pos = count(array_filter($data['cards'], fn($c) => $c['column_id'] === $col));
        $id  = $data['next_card_id']++;
        $data['cards'][] = ['id' => $id, 'title' => $title, 'column_id' => $col, 'token' => $token, 'position' => $pos];
        save($data);
        ok(['id' => $id, 'title' => $title, 'column_id' => $col, 'person' => $person['name']], 201);
    }

    // POST move_card — { id, column_id, token }
    if ($method === 'POST' && $action === 'move_card') {
        $token = trim($body['token'] ?? '');
        if (!find_person($data, $token)) err('invalid token', 401);

        $id  = (int)($body['id'] ?? 0);
        $col = (int)($body['column_id'] ?? 0);
        if (!$id || !$col) err('id and column_id required');

        $pos = count(array_filter($data['cards'], fn($c) => $c['column_id'] === $col));
        foreach ($data['cards'] as &$c) {
            if ($c['id'] === $id) { $c['column_id'] = $col; $c['position'] = $pos; save($data); ok(['ok' => true]); }
        }
        err('card not found', 404);
    }

    // POST update_card — { id, title, token }
    if ($method === 'POST' && $action === 'update_card') {
        $token = trim($body['token'] ?? '');
        if (!find_person($data, $token)) err('invalid token', 401);

        $id = (int)($body['id'] ?? 0);
        if (!$id) err('id required');
        foreach ($data['cards'] as &$c) {
            if ($c['id'] === $id) {
                if (isset($body['title'])) { $t = trim($body['title']); if (!$t) err('title empty'); $c['title'] = $t; }
                save($data); ok(['ok' => true]);
            }
        }
        err('card not found', 404);
    }

    // POST delete_card — { id, token }
    if ($method === 'POST' && $action === 'delete_card') {
        $token = trim($body['token'] ?? '');
        if (!find_person($data, $token)) err('invalid token', 401);

        $id = (int)($body['id'] ?? 0);
        if (!$id) err('id required');
        $before = count($data['cards']);
        $data['cards'] = array_values(array_filter($data['cards'], fn($c) => $c['id'] !== $id));
        if (count($data['cards']) === $before) err('card not found', 404);
        save($data); ok(['ok' => true]);
    }

    // POST add_column — { name, token }
    if ($method === 'POST' && $action === 'add_column') {
        $token = trim($body['token'] ?? '');
        if (!find_person($data, $token)) err('invalid token', 401);

        $name = trim($body['name'] ?? '');
        if (!$name) err('name required');
        $pos  = count($data['columns']);
        $id   = $data['next_col_id']++;
        $data['columns'][] = ['id' => $id, 'name' => $name, 'position' => $pos];
        save($data); ok(['id' => $id, 'name' => $name], 201);
    }

    // POST delete_col — { id, token }
    if ($method === 'POST' && $action === 'delete_col') {
        $token = trim($body['token'] ?? '');
        if (!find_person($data, $token)) err('invalid token', 401);

        $id = (int)($body['id'] ?? 0);
        if (!$id) err('id required');
        $before = count($data['columns']);
        $data['columns'] = array_values(array_filter($data['columns'], fn($c) => $c['id'] !== $id));
        if (count($data['columns']) === $before) err('column not found', 404);
        $data['cards'] = array_values(array_filter($data['cards'], fn($c) => $c['column_id'] !== $id));
        save($data); ok(['ok' => true]);
    }

    err('unknown action', 404);
}

// Pass slot colors to JS as JSON
$slots_json = json_encode(SLOTS);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kanban</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Courier New', Courier, monospace;
    background: #1a1a1a;
    color: #d4d4d4;
    min-height: 100vh;
    padding: 24px;
  }

  #header {
    display: flex;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
  }

  h1 {
    font-size: 1rem;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #888;
  }

  #people-legend {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  .person-pill {
    font-size: .7rem;
    padding: 2px 10px;
    border-radius: 20px;
    white-space: nowrap;
    border-width: 1px;
    border-style: solid;
  }

  .person-pill.empty {
    background: #1e1e1e;
    border-color: #333;
    color: #444;
  }

  #board {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    flex-wrap: nowrap;
    overflow-x: auto;
    padding-bottom: 12px;
  }

  .column {
    background: #252525;
    border: 1px solid #333;
    border-radius: 4px;
    width: 240px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
  }

  .column-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px 8px;
    border-bottom: 1px solid #333;
  }

  .column-name {
    font-size: .75rem;
    letter-spacing: .1em;
    text-transform: uppercase;
    color: #aaa;
  }

  .col-count { font-size: .7rem; color: #555; }

  .btn-del-col {
    background: none; border: none; color: #555;
    cursor: pointer; font-size: .8rem; padding: 0 2px; line-height: 1;
  }
  .btn-del-col:hover { color: #c44; }

  .cards {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 32px;
  }

  .card {
    background: #1e1e1e;
    border: 1px solid #2e2e2e;
    border-radius: 3px;
    padding: 8px 10px;
    font-size: .82rem;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 6px;
  }

  .card-title { flex: 1; word-break: break-word; line-height: 1.4; }
  .card-id { color: #444; font-size: .65rem; white-space: nowrap; flex-shrink: 0; margin-top: 2px; }

  .card-bottom {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
  }

  .person-tag {
    font-size: .65rem;
    padding: 1px 6px;
    border-radius: 2px;
    white-space: nowrap;
    flex-shrink: 0;
    border-width: 1px;
    border-style: solid;
  }

  .btn {
    background: none; border: 1px solid #333; color: #777;
    cursor: pointer; font-family: inherit; font-size: .7rem;
    padding: 2px 5px; border-radius: 2px; white-space: nowrap;
  }
  .btn:hover { border-color: #666; color: #ccc; }
  .btn-danger:hover { border-color: #c44; color: #c44; }

  #add-column-form {
    display: flex; flex-direction: column; gap: 6px;
    width: 200px; flex-shrink: 0; align-self: flex-start; margin-top: 2px;
  }

  #add-column-form input {
    background: #111; border: 1px solid #333; color: #d4d4d4;
    font-family: inherit; font-size: .78rem; padding: 5px 8px;
    border-radius: 2px; outline: none; width: 100%;
  }
  #add-column-form input:focus { border-color: #555; }
  #add-column-form .btn { align-self: flex-start; }

  #status {
    position: fixed; bottom: 16px; right: 16px;
    background: #1e1e1e; border: 1px solid #333; color: #888;
    font-size: .7rem; padding: 4px 10px; border-radius: 3px;
    opacity: 0; transition: opacity .3s; pointer-events: none;
  }
  #status.show { opacity: 1; }
</style>
</head>
<body>
<div id="header">
  <h1>kanban</h1>
  <div id="people-legend"></div>
</div>
<div id="board"></div>
<div id="status"></div>

<script>
const API   = 'demo.php';
const SLOTS = <?= $slots_json ?>;

async function apiFetch(action, body = null) {
  const opts = body
    ? { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }
    : { method: 'GET' };
  const r = await fetch(`${API}?action=${action}`, opts);
  return r.json();
}

function flash(msg) {
  const el = document.getElementById('status');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 1800);
}

function slotStyle(slot) {
  if (slot < 0 || slot >= SLOTS.length) return { bg:'#1e1e1e', border:'#333', text:'#555' };
  return SLOTS[slot];
}

async function render() {
  const data = await apiFetch('board');
  const people = data.people || [];

  // Legend
  const legend = document.getElementById('people-legend');
  legend.innerHTML = '';
  for (let i = 0; i < 4; i++) {
    const p = people.find(x => x.slot === i);
    const pill = document.createElement('span');
    pill.className = 'person-pill';
    if (p) {
      const s = slotStyle(i);
      pill.style.cssText = `background:${s.bg};border-color:${s.border};color:${s.text}`;
      pill.textContent = p.name;
    } else {
      pill.classList.add('empty');
      pill.textContent = `slot ${i + 1}`;
    }
    legend.appendChild(pill);
  }

  // Board
  const board = document.getElementById('board');
  board.innerHTML = '';
  (data.columns || []).forEach(col => {
    const div = document.createElement('div');
    div.className = 'column';
    const otherCols = (data.columns || []).filter(c => c.id !== col.id);
    div.innerHTML = `
      <div class="column-header">
        <span class="column-name">${esc(col.name)}</span>
        <span class="col-count">${col.cards.length}</span>
        <button class="btn-del-col" onclick="delCol(${col.id})">×</button>
      </div>
      <div class="cards">
        ${col.cards.map(c => cardHtml(c, otherCols)).join('')}
      </div>
    `;
    board.appendChild(div);
  });

  const form = document.createElement('div');
  form.id = 'add-column-form';
  form.innerHTML = `
    <input id="new-col-name" type="text" placeholder="new column…" onkeydown="if(event.key==='Enter')addCol()">
    <button class="btn" onclick="addCol()">+ column</button>
  `;
  board.appendChild(form);
}

function cardHtml(c, otherCols) {
  const s = slotStyle(c.person_slot);
  const tagStyle = `background:${s.bg};border-color:${s.border};color:${s.text}`;
  const moveOpts = otherCols.map(col =>
    `<button class="btn" onclick="moveCard(${c.id},${col.id})">→ ${esc(col.name)}</button>`
  ).join('');
  return `
    <div class="card" id="card-${c.id}">
      <div class="card-top">
        <span class="card-id">#${c.id}</span>
        <span class="card-title">${esc(c.title)}</span>
      </div>
      <div class="card-bottom">
        <span class="person-tag" style="${tagStyle}">${esc(c.person_name)}</span>
        ${moveOpts}
        <button class="btn btn-danger" onclick="delCard(${c.id})">del</button>
      </div>
    </div>
  `;
}

// UI-side move/delete (no token — admin view only)
async function moveCard(id, colId) {
  // Use first registered person's token from URL hash for convenience, or skip token check on UI
  // Board view moves are handled by the API without token (read-only UI adjustment)
  flash('use API to move cards');
}

async function delCard(id) {
  flash('use API to delete cards');
}

async function addCol() {
  const input = document.getElementById('new-col-name');
  const name = input.value.trim();
  if (!name) return;
  flash('use API to add columns');
}

async function delCol(id) {
  flash('use API to manage columns');
}

function esc(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

render();
setInterval(render, 2500);
</script>
</body>
</html>
