<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kanban</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  html, body {
    height: 100%;
  }

  body {
    font-family: 'Courier New', Courier, monospace;
    background: #1a1a1a;
    color: #d4d4d4;
    display: flex;
    flex-direction: column;
    padding: 14px 16px 0;
    overflow: hidden;
  }

  h1 {
    font-size: .85rem;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #888;
    margin-bottom: 12px;
    flex-shrink: 0;
  }

  #board {
    flex: 1;
    display: flex;
    gap: 12px;
    align-items: stretch;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    padding-bottom: 14px;
  }

  .column {
    background: #252525;
    border: 1px solid #333;
    border-radius: 4px;
    width: 300px;
    min-width: 160px;
    max-width: 800px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
    position: relative;
  }

  .col-resize-handle {
    position: absolute;
    top: 0;
    right: -4px;
    width: 8px;
    height: 100%;
    cursor: col-resize;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .col-resize-handle::after {
    content: '';
    width: 2px;
    height: 40px;
    background: transparent;
    border-radius: 2px;
    transition: background .15s;
  }
  .col-resize-handle:hover::after,
  .col-resize-handle.dragging::after {
    background: #555;
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

  .col-count {
    font-size: .7rem;
    color: #555;
  }

  .btn-del-col {
    background: none;
    border: none;
    color: #555;
    cursor: pointer;
    font-size: .8rem;
    padding: 0 2px;
    line-height: 1;
  }
  .btn-del-col:hover { color: #c44; }

  .cards {
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 32px;
    overflow-y: auto;
    flex: 1;
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

  .card-title {
    word-break: break-word;
    line-height: 1.45;
    font-size: .85rem;
    color: #e8e8e8;
    background: #252525;
    border: 1px solid #333;
    border-radius: 3px;
    padding: 6px 8px;
  }

  .card-title a {
    color: inherit;
    text-decoration: none;
  }
  .card-title a:hover { color: #6e9ef5; text-decoration: underline; }

  .card-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-wrap: wrap;
  }

  .card-id {
    color: #444;
    font-size: .65rem;
    white-space: nowrap;
    margin-right: auto;
  }

  .agent-tag {
    font-size: .65rem;
    padding: 1px 5px;
    border-radius: 2px;
    white-space: nowrap;
    opacity: .85;
  }

  .card-actions {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
  }

  .btn {
    background: none;
    border: 1px solid #333;
    color: #777;
    cursor: pointer;
    font-family: inherit;
    font-size: .7rem;
    padding: 2px 5px;
    border-radius: 2px;
    white-space: nowrap;
  }
  .btn:hover { border-color: #666; color: #ccc; }
  .btn-danger:hover { border-color: #c44; color: #c44; }

  .card-git-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-family: inherit;
    font-size: .7rem;
    padding: 2px 8px;
    border-radius: 2px;
    border: 1px solid #2a3a5e;
    background: #1a2336;
    color: #6e9ef5;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    align-self: flex-start;
    transition: border-color .15s, color .15s;
  }
  .card-git-btn:hover { border-color: #6e9ef5; color: #a8c8ff; }
  .card-git-btn.unset {
    border-color: #252525;
    background: transparent;
    color: #3a3a3a;
  }
  .card-git-btn.unset:hover { border-color: #444; color: #666; }

  .card-notes-toggle {
    background: none;
    border: none;
    color: #444;
    font-family: inherit;
    font-size: .65rem;
    cursor: pointer;
    padding: 0;
    text-align: left;
  }
  .card-notes-toggle:hover { color: #888; }
  .card-notes-toggle.has-notes { color: #6a9a6a; }

  .card-notes {
    display: none;
    width: 100%;
    background: #141414;
    border: 1px solid #2a2a2a;
    color: #b0c8b0;
    font-family: inherit;
    font-size: .72rem;
    padding: 4px 6px;
    border-radius: 2px;
    outline: none;
    resize: vertical;
    min-height: 60px;
    line-height: 1.4;
  }
  .card-notes.open { display: block; }
  .card-notes:focus { border-color: #3a5a3a; }

  #add-col-btn {
    width: 48px;
    flex-shrink: 0;
    align-self: stretch;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px dashed #2e2e2e;
    border-radius: 4px;
    color: #3a3a3a;
    font-size: 1.4rem;
    cursor: pointer;
    transition: border-color .15s, color .15s;
    user-select: none;
  }
  #add-col-btn:hover { border-color: #555; color: #777; }

  .popup {
    position: fixed;
    z-index: 100;
    background: #1e1e1e;
    border: 1px solid #444;
    border-radius: 4px;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    box-shadow: 0 4px 16px rgba(0,0,0,.5);
    min-width: 180px;
  }
  .popup input {
    background: #111;
    border: 1px solid #333;
    color: #d4d4d4;
    font-family: inherit;
    font-size: .82rem;
    padding: 4px 8px;
    border-radius: 2px;
    outline: none;
    width: 100%;
  }
  .popup input:focus { border-color: #666; }
  .popup-row { display: flex; gap: 6px; }
  .popup-msg { font-size: .78rem; color: #aaa; }

  #archive-toggle {
    position: fixed;
    top: 50%;
    right: 0;
    transform: translateY(-50%);
    writing-mode: vertical-rl;
    background: #252525;
    border: 1px solid #333;
    border-right: none;
    border-radius: 4px 0 0 4px;
    color: #555;
    font-family: inherit;
    font-size: .7rem;
    letter-spacing: .1em;
    padding: 12px 6px;
    cursor: pointer;
    z-index: 200;
    transition: color .15s, border-color .15s;
    user-select: none;
  }
  #archive-toggle:hover { color: #aaa; border-color: #555; }
  #archive-toggle.open  { color: #d4d4d4; border-color: #666; background: #2e2e2e; }

  #archive-panel {
    position: fixed;
    top: 0;
    right: 0;
    width: 320px;
    height: 100%;
    background: #1e1e1e;
    border-left: 1px solid #333;
    display: flex;
    flex-direction: column;
    z-index: 190;
    transform: translateX(100%);
    transition: transform .25s ease;
  }
  #archive-panel.open { transform: translateX(0); }

  #archive-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px 10px;
    border-bottom: 1px solid #2e2e2e;
    flex-shrink: 0;
  }
  #archive-panel-header span {
    font-size: .75rem;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #888;
  }
  #archive-panel-close {
    background: none;
    border: none;
    color: #555;
    cursor: pointer;
    font-size: 1rem;
    line-height: 1;
    padding: 0;
  }
  #archive-panel-close:hover { color: #aaa; }

  #archive-list {
    flex: 1;
    overflow-y: auto;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .arc-card {
    background: #252525;
    border: 1px solid #2e2e2e;
    border-radius: 3px;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
  }
  .arc-card-title {
    font-size: .82rem;
    color: #bbb;
    word-break: break-word;
    line-height: 1.4;
  }
  .arc-card-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
  }
  .arc-col-tag {
    font-size: .65rem;
    color: #555;
  }
  .arc-actions { display: flex; gap: 4px; margin-left: auto; }

  .btn-archive {
    background: none;
    border: 1px solid #2e2e2e;
    color: #4a4a4a;
    cursor: pointer;
    font-family: inherit;
    font-size: .65rem;
    padding: 1px 5px;
    border-radius: 2px;
    white-space: nowrap;
  }
  .btn-archive:hover { border-color: #888; color: #aaa; }

  #status {
    position: fixed;
    bottom: 16px;
    right: 16px;
    background: #1e1e1e;
    border: 1px solid #333;
    color: #888;
    font-size: .7rem;
    padding: 4px 10px;
    border-radius: 3px;
    opacity: 0;
    transition: opacity .3s;
    pointer-events: none;
  }
  #status.show { opacity: 1; }
</style>
</head>
<body>
<h1>kanban</h1>
<div id="board"></div>
<div id="status"></div>
<div class="popup" id="popup" style="display:none"></div>

<button id="archive-toggle" onclick="toggleArchive()">archive</button>

<div id="archive-panel">
  <div id="archive-panel-header">
    <span>archived</span>
    <button id="archive-panel-close" onclick="toggleArchive()">×</button>
  </div>
  <div id="archive-list"></div>
</div>

<script>
const API = 'api.php';

async function api(action, body = null) {
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

async function render() {
  const data = await api('board');
  const board = document.getElementById('board');
  board.innerHTML = '';

  (data.columns || []).forEach(col => {
    const div = document.createElement('div');
    div.className = 'column';
    div.dataset.colId = col.id;

    const otherCols = (data.columns || []).filter(c => c.id !== col.id);

    const savedWidth = localStorage.getItem('col-width-' + col.id);
    if (savedWidth) div.style.width = savedWidth + 'px';

    div.innerHTML = `
      <div class="col-resize-handle" onmousedown="startResize(event, ${col.id})"></div>
      <div class="column-header">
        <span class="column-name" style="color:${colColor(col.id).text}">${esc(col.name)}</span>
        <span class="col-count">${col.cards.length}</span>
        <button class="btn-del-col" title="Delete column" onclick="openDelColPopup(event,${col.id},${JSON.stringify(col.name)})">×</button>
      </div>
      <div class="cards" id="cards-${col.id}">
        ${col.cards.map(c => cardHtml(c, otherCols)).join('')}
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
}

function agentColor(name) {
  let h = 0;
  for (let i = 0; i < name.length; i++) h = (h * 31 + name.charCodeAt(i)) & 0xffff;
  const hue = h % 360;
  return { bg: `hsl(${hue},35%,22%)`, border: `hsl(${hue},50%,38%)`, text: `hsl(${hue},70%,70%)` };
}

function colColor(id) {
  const hue = (id * 137) % 360;
  return {
    text:   `hsl(${hue},65%,68%)`,
    border: `hsl(${hue},50%,32%)`,
    bg:     `hsl(${hue},40%,14%)`,
  };
}

function cardHtml(c, otherCols) {
  const moveOpts = otherCols.map(col => {
    const clr = colColor(col.id);
    return `<button class="btn" style="border-color:${clr.border};color:${clr.text};background:${clr.bg}" onclick="moveCard(${c.id},${col.id})">→ ${esc(col.name)}</button>`;
  }).join('');
  const agent = c.agent || 'unknown';
  const clr = agentColor(agent);
  const tagStyle = `background:${clr.bg};border:1px solid ${clr.border};color:${clr.text}`;
  const titleHtml = c.url
    ? `<a href="${esc(c.url)}" target="_blank" rel="noopener">${esc(c.title)}</a>`
    : esc(c.title);
  const gitBtn = c.url
    ? `<a class="card-git-btn" href="${esc(c.url)}" target="_blank" rel="noopener">⬡ git repo</a>`
    : `<button class="card-git-btn unset" onclick="openSetUrlPopup(event,${c.id})">⬡ git repo</button>`;
  const hasNotes = !!(c.notes && c.notes.trim());
  const notesLabel = hasNotes ? '▾ notes' : '▾ add note';
  return `
    <div class="card" id="card-${c.id}">
      <div class="card-title">${titleHtml}</div>
      <div class="card-meta">
        <span class="card-id">#${c.id}</span>
        <span class="agent-tag" style="${tagStyle}">${esc(agent)}</span>
        <div class="card-actions">
          ${moveOpts}
          <button class="btn-archive" onclick="archiveCard(${c.id})">archive</button>
          <button class="btn btn-danger" onclick="delCard(${c.id})">del</button>
        </div>
      </div>
      ${gitBtn}
      <button class="card-notes-toggle ${hasNotes ? 'has-notes' : ''}" onclick="toggleNotes(${c.id}, this)">${notesLabel}</button>
      <textarea
        class="card-notes ${hasNotes ? 'open' : ''}"
        id="notes-${c.id}"
        placeholder="notes…"
        onblur="saveNotes(${c.id}, this.value)"
      >${esc(c.notes || '')}</textarea>
    </div>
  `;
}

function toggleNotes(id, btn) {
  const ta = document.getElementById('notes-' + id);
  const open = ta.classList.toggle('open');
  btn.textContent = open ? '▴ notes' : (ta.value.trim() ? '▾ notes' : '▾ add note');
  if (open) ta.focus();
}

async function saveNotes(id, value) {
  const res = await api('update_card', { id, notes: value });
  if (res.error) { flash('error: ' + res.error); }
}

function openSetUrlPopup(e, id) {
  e.stopPropagation();
  popup.innerHTML = `<input id="pop-url" type="url" placeholder="https://github.com/…">
    <div class="popup-row">
      <button class="btn" id="pop-url-ok">set</button>
      <button class="btn" onclick="closePopup()">cancel</button>
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
    list.innerHTML = '<div style="color:#444;font-size:.75rem;padding:8px 0">no archived cards</div>';
    return;
  }
  list.innerHTML = cards.map(c => {
    const titleHtml = c.url
      ? `<a href="${esc(c.url)}" target="_blank" rel="noopener" style="color:#6e9ef5;text-decoration:none">${esc(c.title)}</a>`
      : esc(c.title);
    return `
      <div class="arc-card">
        <div class="arc-card-title">${titleHtml}</div>
        <div class="arc-card-meta">
          <span class="arc-col-tag">was: ${esc(c.column_name)}</span>
          <div class="arc-actions">
            <button class="btn-archive" onclick="unarchiveCard(${c.id})">restore</button>
            <button class="btn-archive" style="border-color:#3a1a1a;color:#6a3a3a" onclick="delCard(${c.id}, true)">delete</button>
          </div>
        </div>
        ${c.notes ? `<div style="font-size:.7rem;color:#555;margin-top:2px">${esc(c.notes)}</div>` : ''}
      </div>`;
  }).join('');
}

async function delCard(id, fromArchive = false) {
  const res = await api('delete_card', { id });
  if (res.error) { flash('error: ' + res.error); return; }
  flash(`card #${id} deleted`);
  if (fromArchive) renderArchive(); else render();
}

// --- popup system ---
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
  popup.innerHTML = `<input id="pop-col-name" type="text" placeholder="column name…">
    <div class="popup-row">
      <button class="btn" id="pop-col-ok">+ add</button>
      <button class="btn" onclick="closePopup()">cancel</button>
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
  popup.innerHTML = `<span class="popup-msg">delete <b>${esc(name)}</b>?</span>
    <div class="popup-row">
      <button class="btn btn-danger" id="pop-del-ok">delete</button>
      <button class="btn" onclick="closePopup()">cancel</button>
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

function startResize(e, colId) {
  e.preventDefault();
  const col = document.querySelector(`[data-col-id="${colId}"]`);
  const handle = col.querySelector('.col-resize-handle');
  const startX = e.clientX;
  const startW = col.offsetWidth;

  handle.classList.add('dragging');
  document.body.style.cursor = 'col-resize';
  document.body.style.userSelect = 'none';

  function onMove(e) {
    const w = Math.min(800, Math.max(160, startW + e.clientX - startX));
    col.style.width = w + 'px';
  }
  function onUp() {
    handle.classList.remove('dragging');
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    localStorage.setItem('col-width-' + colId, col.offsetWidth);
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

render();
</script>
</body>
</html>
