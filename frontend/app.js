const API = '/api.php';
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
    ? `<a href="${esc(c.url)}" target="_blank" rel="noopener" draggable="false">${esc(c.title)}</a>`
    : esc(c.title);
  const urlBtn = c.url
    ? `<a class="card-git-btn" href="${esc(c.url)}" target="_blank" rel="noopener" draggable="false">${GIT_ICON} link</a>`
    : `<button class="card-git-btn unset" onclick="openSetUrlPopup(event,${c.id})">+ link</button>`;
  const hasNotes = !!(c.notes && c.notes.trim());
  return `
    <div class="card" id="card-${c.id}" data-card-id="${c.id}">
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

// ── Drag and drop (pointer-events) ──
let _dragId    = null;
let _dragGhost = null;
let _dragOffX  = 0, _dragOffY = 0;
let _pending   = null;   // { cardId, startX, startY, offX, offY }
let _placeholder = null;
let _dndReady  = false;  // global listeners added once

function setupDragDrop() {
  document.querySelectorAll('.card').forEach(card => {
    card.addEventListener('pointerdown', e => {
      if (e.button !== 0) return;
      if (e.target.tagName === 'TEXTAREA') return;
      const box = card.getBoundingClientRect();
      _pending = {
        cardId: parseInt(card.dataset.cardId),
        startX: e.clientX, startY: e.clientY,
        offX: e.clientX - box.left, offY: e.clientY - box.top,
      };
    });
  });

  if (_dndReady) return;
  _dndReady = true;

  document.addEventListener('pointermove', e => {
    // Initiate drag once the pointer moves past a 6px threshold
    if (_pending && !_dragGhost) {
      if (Math.abs(e.clientX - _pending.startX) + Math.abs(e.clientY - _pending.startY) < 6) return;
      const card = document.getElementById('card-' + _pending.cardId);
      if (!card) { _pending = null; return; }
      const box  = card.getBoundingClientRect();
      _dragId   = _pending.cardId;
      _dragOffX = _pending.offX;
      _dragOffY = _pending.offY;
      _pending  = null;

      _dragGhost = card.cloneNode(true);
      Object.assign(_dragGhost.style, {
        position: 'fixed', width: box.width + 'px',
        left: (e.clientX - _dragOffX) + 'px',
        top:  (e.clientY - _dragOffY) + 'px',
        opacity: '.85', pointerEvents: 'none',
        zIndex: '999', transition: 'none',
        boxShadow: '0 8px 32px rgba(0,0,0,.18)',
        transform: 'rotate(1.5deg) scale(1.02)',
        cursor: 'grabbing',
      });
      document.body.appendChild(_dragGhost);
      card.classList.add('dragging');
    }

    if (!_dragGhost) return;
    _dragGhost.style.left = (e.clientX - _dragOffX) + 'px';
    _dragGhost.style.top  = (e.clientY - _dragOffY) + 'px';

    // Highlight drop zone + update placeholder
    document.querySelectorAll('.cards').forEach(z => z.classList.remove('drag-over'));
    const below = document.elementFromPoint(e.clientX, e.clientY);
    const zone  = below && below.closest('.cards');
    if (zone) {
      zone.classList.add('drag-over');
      if (!_placeholder) {
        _placeholder = document.createElement('div');
        _placeholder.className = 'card-drop-placeholder';
      }
      const afterEl = getDragAfterElement(zone, e.clientY);
      if (afterEl) zone.insertBefore(_placeholder, afterEl);
      else         zone.appendChild(_placeholder);
    } else if (_placeholder) {
      _placeholder.remove(); _placeholder = null;
    }
  });

  document.addEventListener('pointerup', e => {
    _pending = null;
    if (!_dragGhost) return;

    _dragGhost.remove(); _dragGhost = null;
    if (_placeholder) { _placeholder.remove(); _placeholder = null; }
    document.querySelectorAll('.cards').forEach(z => z.classList.remove('drag-over'));
    const card = document.getElementById('card-' + _dragId);
    if (card) card.classList.remove('dragging');

    const below = document.elementFromPoint(e.clientX, e.clientY);
    const zone  = below && below.closest('.cards');
    const id    = _dragId;
    _dragId     = null;

    if (zone) moveCard(id, parseInt(zone.dataset.colId));
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
