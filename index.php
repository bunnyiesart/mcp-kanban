<?php
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; frame-ancestors 'none'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kanban</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  :root {
    --ground:     #F5F5F2;
    --surface:    #FFFFFF;
    --surface-2:  #EFEFE9;
    --border:     #E5E5E0;
    --text:       #1D1D1F;
    --text-2:     #6E6E73;
    --text-3:     #AEAEB2;
    --accent:     #5E5CE6;
    --green:      #34C759;
    --red:        #FF453A;
    --shadow-sm:  0 1px 2px rgba(0,0,0,.05);
    --shadow:     0 1px 3px rgba(0,0,0,.06), 0 4px 12px rgba(0,0,0,.04);
    --radius-col: 12px;
    --radius-card: 8px;
    --font: -apple-system, BlinkMacSystemFont, 'SF Pro Text', 'Helvetica Neue', Arial, sans-serif;
  }

  html, body {
    height: 100%;
    background: var(--ground);
    font-family: var(--font);
    color: var(--text);
    -webkit-font-smoothing: antialiased;
  }

  .app {
    display: flex;
    flex-direction: column;
    height: 100%;
  }

  /* ── Topbar ── */
  .topbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 20px 12px;
    flex-shrink: 0;
    border-bottom: 1px solid var(--border);
    background: rgba(245,245,242,.88);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    position: sticky;
    top: 0;
    z-index: 50;
  }

  .topbar-title {
    font-size: 13px;
    font-weight: 600;
    letter-spacing: -.01em;
    color: var(--text);
  }

  .topbar-sep { color: var(--border); font-size: 16px; user-select: none; }

  #board-meta {
    font-size: 12px;
    color: var(--text-2);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
  }

  .meta-stat {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .meta-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  /* ── Board ── */
  #board {
    flex: 1;
    display: flex;
    gap: 10px;
    padding: 16px 20px 20px;
    overflow-x: auto;
    overflow-y: auto;
    align-items: flex-start;
  }

  /* ── Column ── */
  .column {
    width: 288px;
    height: calc(100vh - 90px);
    min-width: 180px;
    max-width: 700px;
    min-height: 120px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    border-radius: var(--radius-col);
    overflow: hidden;
    border: 1px solid var(--border);
    background: var(--surface-2);
    position: relative;
  }

  /* ── Resize handles ── */
  .col-resize-x {
    position: absolute;
    top: 0; right: -5px;
    width: 10px; height: 100%;
    cursor: col-resize;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .col-resize-x::after {
    content: '';
    width: 3px; height: 32px;
    border-radius: 3px;
    background: transparent;
    transition: background .15s;
  }
  .col-resize-x:hover::after,
  .col-resize-x.dragging::after { background: var(--accent); opacity: .4; }

  .col-resize-y {
    position: absolute;
    bottom: -5px; left: 0;
    width: 100%; height: 10px;
    cursor: row-resize;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .col-resize-y::after {
    content: '';
    height: 3px; width: 32px;
    border-radius: 3px;
    background: transparent;
    transition: background .15s;
  }
  .col-resize-y:hover::after,
  .col-resize-y.dragging::after { background: var(--accent); opacity: .4; }

  .col-resize-corner {
    position: absolute;
    bottom: -2px; right: -2px;
    width: 16px; height: 16px;
    cursor: nwse-resize;
    z-index: 11;
    border-radius: 0 0 var(--radius-col) 0;
  }
  .col-resize-corner::after {
    content: '';
    position: absolute;
    bottom: 4px; right: 4px;
    width: 6px; height: 6px;
    border-right: 2px solid transparent;
    border-bottom: 2px solid transparent;
    border-radius: 0 0 2px 0;
    transition: border-color .15s;
  }
  .col-resize-corner:hover::after,
  .col-resize-corner.dragging::after { border-color: var(--accent); opacity: .5; }

  /* ── Column header ── */
  .column-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 11px 14px 9px;
    flex-shrink: 0;
  }

  .col-dot {
    width: 7px; height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .column-name {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .05em;
    text-transform: uppercase;
    flex: 1;
  }

  .col-count {
    font-size: 11px;
    font-weight: 500;
    color: var(--text-3);
    background: rgba(0,0,0,.05);
    border-radius: 10px;
    padding: 1px 7px;
    min-width: 20px;
    text-align: center;
    line-height: 16px;
  }

  .btn-del-col {
    background: none;
    border: none;
    color: var(--text-3);
    cursor: pointer;
    font-size: 14px;
    padding: 0 2px;
    line-height: 1;
    transition: color .15s;
  }
  .btn-del-col:hover { color: var(--red); }

  .col-header-divider {
    height: 1px;
    margin: 0 14px;
    background: rgba(0,0,0,.06);
    flex-shrink: 0;
  }

  /* ── Cards ── */
  .cards {
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 7px;
    overflow-y: auto;
    flex: 1;
    min-height: 32px;
  }

  .card {
    background: var(--surface);
    border-radius: var(--radius-card);
    padding: 11px 12px 10px;
    box-shadow: var(--shadow);
    border: 1px solid rgba(0,0,0,.04);
    display: flex;
    flex-direction: column;
    gap: 8px;
    transition: box-shadow .15s, transform .1s, opacity .15s;
    cursor: grab;
  }

  .card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,.1), 0 8px 24px rgba(0,0,0,.06);
    transform: translateY(-1px);
  }

  .card:active { cursor: grabbing; }
  .card.dragging { opacity: .35; transform: scale(.98); box-shadow: none; }

  .cards.drag-over {
    background: rgba(94,92,230,.05);
    outline: 2px dashed rgba(94,92,230,.25);
    outline-offset: -4px;
    border-radius: 6px;
  }

  .card-drop-placeholder {
    height: 4px;
    background: var(--accent);
    border-radius: 2px;
    opacity: .5;
    margin: 0 2px;
    flex-shrink: 0;
  }

  .card-title {
    font-size: 13px;
    font-weight: 450;
    line-height: 1.45;
    color: var(--text);
    word-break: break-word;
  }

  .card-title a { color: inherit; text-decoration: none; }
  .card-title a:hover { color: var(--accent); }

  /* ── Card footer (id + agent + actions) ── */
  .card-footer {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  .card-id {
    font-size: 11px;
    color: var(--text-3);
    font-variant-numeric: tabular-nums;
    flex-shrink: 0;
  }

  .agent-tag {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: var(--text-2);
    padding: 2px 7px 2px 5px;
    border-radius: 20px;
    background: rgba(0,0,0,.04);
    border: 1px solid rgba(0,0,0,.05);
    white-space: nowrap;
    flex-shrink: 0;
  }

  .agent-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  .card-actions {
    margin-left: auto;
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
    opacity: 0;
    transition: opacity .15s;
  }
  .card:hover .card-actions { opacity: 1; }

  .btn {
    font-family: var(--font);
    font-size: 11px;
    color: var(--text-2);
    background: none;
    border: 1px solid var(--border);
    border-radius: 5px;
    padding: 2px 7px;
    cursor: pointer;
    white-space: nowrap;
    transition: color .12s, border-color .12s, background .12s;
    line-height: 15px;
  }
  .btn:hover {
    background: var(--surface-2);
    border-color: var(--text-3);
    color: var(--text);
  }
  .btn-danger { color: var(--text-3); border-color: transparent; }
  .btn-danger:hover { color: var(--red); border-color: rgba(255,69,58,.3); background: rgba(255,69,58,.05); }

  /* ── URL chip ── */
  .card-git-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font);
    font-size: 11px;
    color: var(--accent);
    background: rgba(94,92,230,.06);
    border: 1px solid rgba(94,92,230,.15);
    border-radius: 5px;
    padding: 2px 8px;
    text-decoration: none;
    cursor: pointer;
    transition: background .12s;
    white-space: nowrap;
    align-self: flex-start;
  }
  .card-git-btn:hover { background: rgba(94,92,230,.12); }

  .card-git-btn.unset {
    color: var(--text-3);
    background: transparent;
    border-color: transparent;
  }
  .card-git-btn.unset:hover {
    color: var(--text-2);
    background: var(--surface-2);
    border-color: var(--border);
  }

  /* ── Notes toggle + textarea ── */
  .card-notes-toggle {
    display: flex;
    align-items: center;
    gap: 5px;
    font-family: var(--font);
    font-size: 11px;
    color: var(--text-3);
    cursor: pointer;
    padding: 0;
    background: none;
    border: none;
    user-select: none;
    transition: color .12s;
    align-self: flex-start;
  }
  .card-notes-toggle.has-notes { color: var(--accent); }
  .card-notes-toggle:hover { color: var(--text-2); }
  .card-notes-toggle.has-notes:hover { opacity: .75; }

  .notes-arrow {
    font-size: 10px;
    display: inline-block;
    transition: transform .15s;
  }
  .card-notes-toggle.open .notes-arrow { transform: rotate(90deg); }

  .card-notes {
    display: none;
    width: 100%;
    background: #F7F7F4;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text);
    font-family: var(--font);
    font-size: 12px;
    line-height: 1.5;
    padding: 8px 10px;
    outline: none;
    resize: none;
    min-height: 64px;
    transition: border-color .15s, box-shadow .15s;
  }
  .card-notes.open { display: block; }
  .card-notes:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(94,92,230,.1);
    background: #fff;
  }
  .card-notes::placeholder { color: var(--text-3); }

  /* ── Add column button ── */
  #add-col-btn {
    width: 44px;
    flex-shrink: 0;
    align-self: stretch;
    min-height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1.5px dashed var(--border);
    border-radius: var(--radius-col);
    color: var(--text-3);
    font-size: 20px;
    cursor: pointer;
    transition: border-color .15s, color .15s;
    user-select: none;
  }
  #add-col-btn:hover { border-color: var(--text-3); color: var(--text-2); }

  /* ── Status toast ── */
  #status {
    position: fixed;
    bottom: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(29,29,31,.88);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    color: #fff;
    font-size: 12px;
    padding: 6px 16px;
    border-radius: 20px;
    opacity: 0;
    transition: opacity .25s;
    pointer-events: none;
    box-shadow: 0 2px 12px rgba(0,0,0,.2);
    white-space: nowrap;
  }
  #status.show { opacity: 1; }

  /* ── Popup ── */
  .popup {
    position: fixed;
    z-index: 300;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 4px 24px rgba(0,0,0,.12);
    min-width: 200px;
  }
  .popup input {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text);
    font-family: var(--font);
    font-size: 13px;
    padding: 6px 10px;
    border-radius: 6px;
    outline: none;
    width: 100%;
    transition: border-color .15s, box-shadow .15s;
  }
  .popup input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(94,92,230,.1);
    background: var(--surface);
  }
  .popup-row { display: flex; gap: 6px; }
  .popup-msg { font-size: 13px; color: var(--text-2); }
  .popup-msg b { color: var(--text); font-weight: 600; }

  /* ── Archive tab ── */
  #archive-toggle {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    writing-mode: vertical-rl;
    background: var(--surface);
    border: 1px solid var(--border);
    border-right: none;
    border-radius: 8px 0 0 8px;
    color: var(--text-3);
    font-family: var(--font);
    font-size: 11px;
    letter-spacing: .06em;
    font-weight: 500;
    text-transform: uppercase;
    padding: 14px 7px;
    cursor: pointer;
    z-index: 200;
    transition: color .15s, border-color .15s, right .25s ease;
    box-shadow: var(--shadow-sm);
    user-select: none;
  }
  #archive-toggle:hover { color: var(--text-2); }
  #archive-toggle.open  { color: var(--text); border-color: var(--text-3); right: 300px; }

  /* ── Archive panel ── */
  #archive-panel {
    position: fixed;
    top: 0; right: 0;
    width: 300px;
    height: 100%;
    background: var(--surface);
    border-left: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    z-index: 190;
    transform: translateX(100%);
    transition: transform .25s ease;
    box-shadow: -4px 0 24px rgba(0,0,0,.06);
  }
  #archive-panel.open { transform: translateX(0); }

  #archive-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px 12px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
  }
  #archive-panel-header span {
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .07em;
    text-transform: uppercase;
    color: var(--text-2);
  }

  #archive-panel-close {
    background: none;
    border: none;
    font-size: 16px;
    color: var(--text-3);
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 4px;
    line-height: 1;
    transition: color .12s, background .12s;
  }
  #archive-panel-close:hover { color: var(--text); background: var(--surface-2); }

  #archive-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 7px;
  }

  .arc-card {
    background: var(--surface-2);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .arc-card-title {
    font-size: 12px;
    color: var(--text-2);
    line-height: 1.4;
    word-break: break-word;
  }
  .arc-card-title a { color: var(--accent); text-decoration: none; }
  .arc-card-title a:hover { text-decoration: underline; }

  .arc-card-meta {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .arc-col-tag {
    font-size: 11px;
    color: var(--text-3);
    flex: 1;
  }
  .arc-actions { display: flex; gap: 4px; }

  .btn-archive {
    font-family: var(--font);
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 5px;
    cursor: pointer;
    transition: background .12s;
    line-height: 15px;
  }
  .btn-restore {
    color: var(--accent);
    background: rgba(94,92,230,.06);
    border: 1px solid rgba(94,92,230,.2);
  }
  .btn-restore:hover { background: rgba(94,92,230,.12); }
  .btn-del-arc {
    color: var(--text-3);
    background: transparent;
    border: 1px solid transparent;
  }
  .btn-del-arc:hover { color: var(--red); background: rgba(255,69,58,.05); border-color: rgba(255,69,58,.2); }

  .arc-notes {
    font-size: 11px;
    color: var(--text-3);
    line-height: 1.4;
  }

  /* scrollbar */
  ::-webkit-scrollbar { width: 5px; height: 5px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: rgba(0,0,0,.12); border-radius: 10px; }
  ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,.2); }

  /* ── Lock screen ── */
  #lock {
    position: fixed;
    inset: 0;
    z-index: 1000;
    background: var(--ground);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .lock-box {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 32px 28px 28px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    width: 300px;
    box-shadow: var(--shadow);
  }
  .lock-title { font-size: 15px; font-weight: 600; color: var(--text); }
  .lock-sub   { font-size: 12px; color: var(--text-2); margin-top: -4px; line-height: 1.5; }
  .lock-input {
    background: var(--surface-2);
    border: 1px solid var(--border);
    color: var(--text);
    font-family: var(--font);
    font-size: 13px;
    padding: 7px 10px;
    border-radius: 8px;
    outline: none;
    width: 100%;
    transition: border-color .15s, box-shadow .15s;
  }
  .lock-input:focus {
    border-color: var(--accent);
    box-shadow: 0 0 0 3px rgba(94,92,230,.1);
    background: var(--surface);
  }
  .lock-err { font-size: 12px; color: var(--red); display: none; }
  .lock-btn {
    background: var(--accent);
    color: #fff;
    border: none;
    border-radius: 8px;
    padding: 8px 16px;
    font-family: var(--font);
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    align-self: flex-end;
    transition: opacity .15s;
  }
  .lock-btn:hover:not(:disabled) { opacity: .85; }
  .lock-btn:disabled { opacity: .5; cursor: default; }
</style>
</head>
<body>

<div id="lock">
  <div class="lock-box">
    <div class="lock-title">BlueTeam Kanban</div>
    <div class="lock-sub">Enter the team API key to access the board.</div>
    <input id="lock-key" class="lock-input" type="password" placeholder="API key…"
           autocomplete="off" onkeydown="if(event.key==='Enter')tryUnlock()">
    <div id="lock-err" class="lock-err">Wrong key — try again.</div>
    <button id="lock-btn" class="lock-btn" onclick="tryUnlock()">Unlock</button>
  </div>
</div>

<div class="app">
  <div class="topbar">
    <span class="topbar-title">Kanban</span>
    <div id="board-meta"></div>
  </div>
  <div id="board"></div>
</div>

<div id="status"></div>
<div class="popup" id="popup" style="display:none"></div>

<button id="archive-toggle" onclick="toggleArchive()">Archived</button>

<div id="archive-panel">
  <div id="archive-panel-header">
    <span>Archived</span>
    <button id="archive-panel-close" onclick="toggleArchive()">×</button>
  </div>
  <div id="archive-list"></div>
</div>

<script>
const API = 'api.php';
let _key = sessionStorage.getItem('kanban_key') || '';

const COL_TINTS = [
  { bg: '#F0F0F8', border: '#E2E2EE', dot: '#AEAEE8', label: '#6E6EAA' },
  { bg: '#F0EFFC', border: '#DDDCF7', dot: '#5E5CE6', label: '#5A58C0' },
  { bg: '#EFF7F1', border: '#D8EEE0', dot: '#34C759', label: '#3A9E56' },
  { bg: '#FFF5EC', border: '#FFDFC4', dot: '#FF9F0A', label: '#B07800' },
  { bg: '#FEF0F0', border: '#FAD5D5', dot: '#FF6B6B', label: '#C04040' },
];

function colTint(idx) { return COL_TINTS[idx % COL_TINTS.length]; }

function agentDotColor(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xffff;
  return `hsl(${h % 360},65%,55%)`;
}

async function api(action, body = null) {
  const opts = body
    ? { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Api-Key': _key }, body: JSON.stringify(body) }
    : { method: 'GET', headers: { 'X-Api-Key': _key } };
  const r = await fetch(`${API}?action=${action}`, opts);
  if (r.status === 401) { showLock(); return { error: 'Unauthorized' }; }
  return r.json();
}

function showLock() {
  _key = '';
  sessionStorage.removeItem('kanban_key');
  document.getElementById('lock').style.display = 'flex';
}

async function tryUnlock() {
  const input = document.getElementById('lock-key');
  const err   = document.getElementById('lock-err');
  const btn   = document.getElementById('lock-btn');
  const candidate = input.value.trim();
  if (!candidate) return;
  btn.disabled = true;
  btn.textContent = '…';
  const r = await fetch(`${API}?action=board`, { headers: { 'X-Api-Key': candidate } });
  btn.disabled = false;
  btn.textContent = 'Unlock';
  if (r.status === 401 || r.status === 403) {
    err.style.display = 'block';
    input.value = '';
    input.focus();
    return;
  }
  err.style.display = 'none';
  _key = candidate;
  sessionStorage.setItem('kanban_key', _key);
  document.getElementById('lock').style.display = 'none';
  render();
}

function flash(msg) {
  const el = document.getElementById('status');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(el._t);
  el._t = setTimeout(() => el.classList.remove('show'), 1800);
}

async function render() {
  const data = await api('board');
  const board = document.getElementById('board');
  board.innerHTML = '';

  const cols = data.columns || [];
  const totalCards = cols.reduce((n, c) => n + c.cards.length, 0);
  const meta = document.getElementById('board-meta');
  meta.innerHTML = totalCards
    ? `<div class="meta-stat"><div class="meta-dot" style="background:var(--accent)"></div><span>${totalCards} active</span></div>`
    : '';

  cols.forEach((col, idx) => {
    const tint = colTint(idx);
    const div = document.createElement('div');
    div.className = 'column';
    div.dataset.colId = col.id;
    div.style.background = tint.bg;
    div.style.borderColor = tint.border;

    const savedW = localStorage.getItem('col-width-'  + col.id);
    const savedH = localStorage.getItem('col-height-' + col.id);
    if (savedW) div.style.width  = savedW + 'px';
    if (savedH) div.style.height = savedH + 'px';

    div.innerHTML = `
      <div class="col-resize-x"      onmousedown="startResize(event,'x')"></div>
      <div class="col-resize-y"      onmousedown="startResize(event,'y')"></div>
      <div class="col-resize-corner" onmousedown="startResize(event,'xy')"></div>
      <div class="column-header">
        <div class="col-dot" style="background:${tint.dot}"></div>
        <span class="column-name" style="color:${tint.label}">${esc(col.name)}</span>
        <span class="col-count">${col.cards.length}</span>
        <button class="btn-del-col" title="Delete column" onclick="openDelColPopup(event,${col.id},${JSON.stringify(col.name)})">×</button>
      </div>
      <div class="col-header-divider"></div>
      <div class="cards" id="cards-${col.id}" data-col-id="${col.id}">
        ${col.cards.map(c => cardHtml(c)).join('')}
      </div>
    `;
    board.appendChild(div);
  });

  const addBtn = document.createElement('div');
  addBtn.id = 'add-col-btn';
  addBtn.title = 'Add column';
  addBtn.textContent = '+';
  addBtn.addEventListener('click', e => openAddColPopup(e));
  board.appendChild(addBtn);

  setupDragDrop();
}

const GIT_ICON = `<svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor" style="flex-shrink:0"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>`;

function cardHtml(c) {
  const agent = c.agent || 'unknown';
  const dotColor = agentDotColor(agent);
  const titleHtml = c.url
    ? `<a href="${esc(c.url)}" target="_blank" rel="noopener">${esc(c.title)}</a>`
    : esc(c.title);
  const urlBtn = c.url
    ? `<a class="card-git-btn" href="${esc(c.url)}" target="_blank" rel="noopener">${GIT_ICON} link</a>`
    : `<button class="card-git-btn unset" onclick="openSetUrlPopup(event,${c.id})">+ link</button>`;
  const hasNotes = !!(c.notes && c.notes.trim());
  return `
    <div class="card" id="card-${c.id}" draggable="true" data-card-id="${c.id}">
      <div class="card-title">${titleHtml}</div>
      <div class="card-footer">
        <span class="card-id">#${c.id}</span>
        <div class="agent-tag">
          <div class="agent-dot" style="background:${dotColor}"></div>
          <span>${esc(agent)}</span>
        </div>
        <div class="card-actions">
          <button class="btn" onclick="archiveCard(${c.id})">archive</button>
          <button class="btn btn-danger" onclick="delCard(${c.id})">del</button>
        </div>
      </div>
      ${urlBtn}
      <button class="card-notes-toggle ${hasNotes ? 'has-notes' : ''}" onclick="toggleNotes(${c.id}, this)">
        <span class="notes-arrow">›</span> ${hasNotes ? 'note' : 'add note'}
      </button>
      <textarea
        class="card-notes ${hasNotes ? 'open' : ''}"
        id="notes-${c.id}"
        placeholder="Add a note…"
        onblur="saveNotes(${c.id}, this.value)"
      >${esc(c.notes || '')}</textarea>
    </div>
  `;
}

function toggleNotes(id, btn) {
  const ta = document.getElementById('notes-' + id);
  const open = ta.classList.toggle('open');
  btn.classList.toggle('open', open);
  const hasContent = ta.value.trim().length > 0;
  if (open) ta.focus();
  btn.innerHTML = `<span class="notes-arrow">›</span> ${hasContent ? 'note' : 'add note'}`;
  if (hasContent) btn.classList.add('has-notes');
}

async function saveNotes(id, value) {
  const res = await api('update_card', { id, notes: value });
  if (res.error) flash('error: ' + res.error);
}

function openSetUrlPopup(e, id) {
  e.stopPropagation();
  popup.innerHTML = `<input id="pop-url" type="url" placeholder="https://github.com/…">
    <div class="popup-row">
      <button class="btn" id="pop-url-ok">Set</button>
      <button class="btn" onclick="closePopup()">Cancel</button>
    </div>`;
  placePopup(e);
  const input = document.getElementById('pop-url');
  input.focus();
  const submit = async () => {
    const url = input.value.trim();
    const res = await api('update_card', { id, url });
    closePopup();
    if (res.error) { flash('error: ' + res.error); return; }
    render();
  };
  input.addEventListener('keydown', e => { if (e.key === 'Enter') submit(); });
  document.getElementById('pop-url-ok').addEventListener('click', submit);
}

async function moveCard(id, colId) {
  const res = await api('move_card', { id, column_id: colId });
  if (res.error) { flash('error: ' + res.error); return; }
  flash(`card #${id} moved`);
  render();
}

async function archiveCard(id) {
  const res = await api('archive_card', { id });
  if (res.error) { flash('error: ' + res.error); return; }
  flash('card archived');
  render();
  if (document.getElementById('archive-panel').classList.contains('open')) renderArchive();
}

async function unarchiveCard(id) {
  const res = await api('unarchive_card', { id });
  if (res.error) { flash('error: ' + res.error); return; }
  flash('card restored');
  render();
  renderArchive();
}

function toggleArchive() {
  const panel = document.getElementById('archive-panel');
  const btn   = document.getElementById('archive-toggle');
  const open  = panel.classList.toggle('open');
  btn.classList.toggle('open', open);
  if (open) renderArchive();
}

async function renderArchive() {
  const data = await api('archived_cards');
  const list = document.getElementById('archive-list');
  const cards = data.cards || [];
  if (!cards.length) {
    list.innerHTML = '<div style="font-size:12px;color:var(--text-3);padding:16px 0;text-align:center">No archived cards</div>';
    return;
  }
  list.innerHTML = cards.map(c => {
    const titleHtml = c.url
      ? `<a href="${esc(c.url)}" target="_blank" rel="noopener">${esc(c.title)}</a>`
      : esc(c.title);
    return `
      <div class="arc-card">
        <div class="arc-card-title">${titleHtml}</div>
        <div class="arc-card-meta">
          <span class="arc-col-tag">was: ${esc(c.column_name)}</span>
          <div class="arc-actions">
            <button class="btn-archive btn-restore" onclick="unarchiveCard(${c.id})">Restore</button>
            <button class="btn-archive btn-del-arc" onclick="delCard(${c.id}, true)">Delete</button>
          </div>
        </div>
        ${c.notes ? `<div class="arc-notes">${esc(c.notes)}</div>` : ''}
      </div>`;
  }).join('');
}

async function delCard(id, fromArchive = false) {
  const res = await api('delete_card', { id });
  if (res.error) { flash('error: ' + res.error); return; }
  flash(`card #${id} deleted`);
  if (fromArchive) renderArchive(); else render();
}

// ── Popup ──
const popup = document.getElementById('popup');

function placePopup(e) {
  const margin = 8;
  popup.style.display = 'flex';
  const pw = popup.offsetWidth, ph = popup.offsetHeight;
  let x = e.clientX + margin, y = e.clientY + margin;
  if (x + pw > window.innerWidth  - margin) x = e.clientX - pw - margin;
  if (y + ph > window.innerHeight - margin) y = e.clientY - ph - margin;
  popup.style.left = x + 'px';
  popup.style.top  = y + 'px';
}

function closePopup() { popup.style.display = 'none'; popup.innerHTML = ''; }

document.addEventListener('mousedown', e => {
  if (!popup.contains(e.target)) closePopup();
});

function openAddColPopup(e) {
  e.stopPropagation();
  popup.innerHTML = `<input id="pop-col-name" type="text" placeholder="Column name…">
    <div class="popup-row">
      <button class="btn" id="pop-col-ok">Add</button>
      <button class="btn" onclick="closePopup()">Cancel</button>
    </div>`;
  placePopup(e);
  const input = document.getElementById('pop-col-name');
  input.focus();
  const submit = async () => {
    const name = input.value.trim();
    if (!name) return;
    const res = await api('add_column', { name });
    closePopup();
    if (res.error) { flash('error: ' + res.error); return; }
    flash(`"${name}" added`);
    render();
  };
  input.addEventListener('keydown', e => { if (e.key === 'Enter') submit(); });
  document.getElementById('pop-col-ok').addEventListener('click', submit);
}

function openDelColPopup(e, id, name) {
  e.stopPropagation();
  popup.innerHTML = `<span class="popup-msg">Delete <b>${esc(name)}</b>?</span>
    <div class="popup-row">
      <button class="btn btn-danger" id="pop-del-ok" style="opacity:1;border-color:rgba(255,69,58,.3);color:var(--red)">Delete</button>
      <button class="btn" onclick="closePopup()">Cancel</button>
    </div>`;
  placePopup(e);
  document.getElementById('pop-del-ok').addEventListener('click', async () => {
    const res = await api('delete_col', { id });
    closePopup();
    if (res.error) { flash('error: ' + res.error); return; }
    flash('column deleted');
    render();
  });
}

// ── Drag and drop ──
let _dragId = null;
let _placeholder = null;

function setupDragDrop() {
  document.querySelectorAll('.card').forEach(card => {
    card.addEventListener('dragstart', e => {
      _dragId = parseInt(card.dataset.cardId);
      card.classList.add('dragging');
      e.dataTransfer.effectAllowed = 'move';
      // prevent drag starting from interactive children
      if (e.target.tagName === 'TEXTAREA' || e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
        e.preventDefault();
      }
    });
    card.addEventListener('dragend', () => {
      card.classList.remove('dragging');
      if (_placeholder) { _placeholder.remove(); _placeholder = null; }
      document.querySelectorAll('.cards').forEach(z => z.classList.remove('drag-over'));
      _dragId = null;
    });
  });

  document.querySelectorAll('.cards').forEach(zone => {
    zone.addEventListener('dragover', e => {
      e.preventDefault();
      e.dataTransfer.dropEffect = 'move';
      zone.classList.add('drag-over');

      // position placeholder between cards
      const afterEl = getDragAfterElement(zone, e.clientY);
      if (!_placeholder) {
        _placeholder = document.createElement('div');
        _placeholder.className = 'card-drop-placeholder';
      }
      if (afterEl) zone.insertBefore(_placeholder, afterEl);
      else zone.appendChild(_placeholder);
    });
    zone.addEventListener('dragleave', e => {
      if (!zone.contains(e.relatedTarget)) {
        zone.classList.remove('drag-over');
        if (_placeholder && _placeholder.parentNode === zone) _placeholder.remove();
      }
    });
    zone.addEventListener('drop', e => {
      e.preventDefault();
      zone.classList.remove('drag-over');
      if (_placeholder) { _placeholder.remove(); _placeholder = null; }
      if (_dragId === null) return;
      const colId = parseInt(zone.dataset.colId);
      moveCard(_dragId, colId);
    });
  });
}

function getDragAfterElement(zone, y) {
  const cards = [...zone.querySelectorAll('.card:not(.dragging)')];
  return cards.reduce((closest, child) => {
    const box = child.getBoundingClientRect();
    const offset = y - box.top - box.height / 2;
    if (offset < 0 && offset > closest.offset) return { offset, element: child };
    return closest;
  }, { offset: Number.NEGATIVE_INFINITY }).element;
}

// ── Resize (x, y, xy) ──
function startResize(e, axis) {
  e.preventDefault();
  const handle = e.currentTarget;
  const col = handle.closest('.column');
  const colId = col.dataset.colId;
  const startX = e.clientX, startY = e.clientY;
  const startW = col.offsetWidth, startH = col.offsetHeight;

  handle.classList.add('dragging');
  const cursors = { x: 'col-resize', y: 'row-resize', xy: 'nwse-resize' };
  document.body.style.cursor = cursors[axis];
  document.body.style.userSelect = 'none';

  function onMove(e) {
    if (axis === 'x' || axis === 'xy') {
      col.style.width = Math.min(700, Math.max(180, startW + e.clientX - startX)) + 'px';
    }
    if (axis === 'y' || axis === 'xy') {
      col.style.height = Math.max(120, startH + e.clientY - startY) + 'px';
    }
  }
  function onUp() {
    handle.classList.remove('dragging');
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    if (axis === 'x' || axis === 'xy') localStorage.setItem('col-width-'  + colId, col.offsetWidth);
    if (axis === 'y' || axis === 'xy') localStorage.setItem('col-height-' + colId, col.offsetHeight);
    window.removeEventListener('mousemove', onMove);
    window.removeEventListener('mouseup', onUp);
  }
  window.addEventListener('mousemove', onMove);
  window.addEventListener('mouseup', onUp);
}

function esc(s) {
  return String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// Init — show board if key already in sessionStorage, else show lock
if (_key) {
  document.getElementById('lock').style.display = 'none';
  render();
}
</script>
</body>
</html>
