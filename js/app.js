/* ============================================================
   IT INVENTORY — SHARED JAVASCRIPT
   ============================================================ */

// ── TOAST ────────────────────────────────────────────────────
const Toast = {
  container: null,

  init() {
    this.container = document.getElementById('toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      this.container.id = 'toast-container';
      document.body.appendChild(this.container);
    }
  },

  show(message, type = 'success', duration = 3200) {
    if (!this.container) this.init();
    const icons = {
      success: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>`,
      error:   `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>`,
      warning: `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>`,
      info:    `<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>`
    };
    const colors = { success:'var(--green)', error:'var(--red)', warning:'var(--yellow)', info:'var(--accent)' };
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.style.borderLeft = `3px solid ${colors[type]}`;
    toast.innerHTML = `
      <span style="color:${colors[type]};flex-shrink:0">${icons[type]}</span>
      <span style="color:var(--text-primary)">${message}</span>
    `;
    this.container.appendChild(toast);
    setTimeout(() => {
      toast.style.transition = 'all .3s ease';
      toast.style.opacity = '0';
      toast.style.transform = 'translateX(16px)';
      setTimeout(() => toast.remove(), 300);
    }, duration);
  }
};

// ── MODAL ────────────────────────────────────────────────────
const Modal = {
  open(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
      overlay.classList.add('open');
      document.body.style.overflow = 'hidden';
      Poller.pause('modal');
    }
  },
  close(id) {
    const overlay = document.getElementById(id);
    if (overlay) {
      overlay.classList.remove('open');
      document.body.style.overflow = '';
    }
    if (!document.querySelector('.modal-overlay.open')) {
      Poller.resume('modal');
    }
  }
};

document.addEventListener('click', (e) => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
    if (!document.querySelector('.modal-overlay.open')) {
      Poller.resume('modal');
    }
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open').forEach(m => {
      m.classList.remove('open');
      document.body.style.overflow = '';
    });
    Poller.resume('modal');
  }
});

// ── TABLE SEARCH / FILTER ────────────────────────────────────
function initTableSearch(inputId, tableId, colIndexes = null) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', () => {
    const q = input.value.toLowerCase().trim();
    table.querySelectorAll('tbody tr').forEach(row => {
      const cells = colIndexes
        ? colIndexes.map(i => row.cells[i]).filter(Boolean)
        : Array.from(row.cells);
      const text = cells.map(c => c.textContent).join(' ').toLowerCase();
      row.style.display = text.includes(q) ? '' : 'none';
    });
    updateEmptyState(table);
  });
}

function initSelectFilter(selectId, tableId, colIndex) {
  const select = document.getElementById(selectId);
  const table  = document.getElementById(tableId);
  if (!select || !table) return;
  select.addEventListener('change', () => {
    const val = select.value.toLowerCase();
    table.querySelectorAll('tbody tr').forEach(row => {
      const cell = row.cells[colIndex];
      if (!cell) return;
      row.style.display = !val || cell.textContent.toLowerCase().includes(val) ? '' : 'none';
    });
    updateEmptyState(table);
  });
}

function updateEmptyState(table) {
  const tbody   = table.querySelector('tbody');
  const visible = Array.from(tbody.querySelectorAll('tr')).filter(r => r.style.display !== 'none');
  let empty = tbody.querySelector('.empty-row');
  if (visible.length === 0) {
    if (!empty) {
      empty = document.createElement('tr');
      empty.className = 'empty-row';
      empty.innerHTML = `<td colspan="20" class="empty-state" style="padding:48px 0">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        <h3>No results found</h3><p>Try adjusting your search or filters</p>
      </td>`;
      tbody.appendChild(empty);
    }
    empty.style.display = '';
  } else if (empty) {
    empty.style.display = 'none';
  }
}

function validateForm(formId) {
  const form = document.getElementById(formId);
  if (!form) return true;
  let valid = true;
  form.querySelectorAll('[required]').forEach(field => {
    field.classList.remove('error');
    if (!field.value.trim()) {
      field.classList.add('error');
      field.style.borderColor = 'var(--red)';
      valid = false;
    } else {
      field.style.borderColor = '';
    }
  });
  return valid;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleDateString('en-PH', { year:'numeric', month:'short', day:'numeric' });
}

document.addEventListener('DOMContentLoaded', () => { Toast.init(); });

/* ================================================================
   AJAX FORM ENGINE
   Pauses the poller for the duration of every request so the
   poll fetch never collides with a save/edit/delete fetch.
   Network errors from the poller are always silent.
   Network errors from user actions show a toast ONLY if the
   request itself failed — not if it was a polling collision.
================================================================ */
const AjaxForm = {

  async submit(formEl, module, onSuccess) {
    const btn = formEl.querySelector('[type=submit]');
    const origText = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }

    // Pause polling for the duration of this request
    Poller.pause('request');
    let succeeded = false;

    try {
      const data = new FormData(formEl);
      data.set('module', module);
      const res  = await fetch('api.php', { method:'POST', body:data });
      const json = await res.json();

      if (json.success) {
        succeeded = true;
        Toast.show(json.success, 'success');
        Poller.resetHash();
        if (typeof onSuccess === 'function') {
          try {
            onSuccess(json.data || {});
          } catch (callbackErr) {
            console.error('Callback error:', callbackErr);
            // Save was successful — just trigger a poll to refresh the table
            setTimeout(() => Poller._tick(), 500);
          }
        }
      } else {
        Toast.show(json.error || 'An error occurred.', 'error');
      }
    } catch (e) {
      // Only show network error if it's a genuine failure
      // (not a polling collision — those are caught silently in _tick)
      if (!succeeded) {
        Toast.show('Could not save. Please check your connection and try again.', 'error');
      }
      console.error('AjaxForm.submit error:', e);
    } finally {
      if (btn) { btn.disabled = false; btn.textContent = origText; }
      Poller.resume('request');
    }
  },

  async delete(module, id, onSuccess) {
    Poller.pause('request');
    const data = new FormData();
    data.set('module', module);
    data.set('action', 'delete');
    data.set('record_id', id);
    try {
      const res  = await fetch('api.php', { method:'POST', body:data });
      const json = await res.json();
      if (json.success) {
        Toast.show(json.success, 'success');
        Poller.resetHash();
        if (typeof onSuccess === 'function') {
          try { onSuccess({ op:'delete', id }); } catch(e) { console.error(e); }
        }
      } else {
        Toast.show(json.error || 'Delete failed.', 'error');
      }
    } catch (e) {
      Toast.show('Could not delete. Please check your connection.', 'error');
      console.error('AjaxForm.delete error:', e);
    } finally {
      Poller.resume('request');
    }
  },

  async action(module, payload, onSuccess) {
    Poller.pause('request');
    const data = new FormData();
    data.set('module', module);
    Object.entries(payload).forEach(([k,v]) => data.set(k, v));
    try {
      const res  = await fetch('api.php', { method:'POST', body:data });
      const json = await res.json();
      if (json.success) {
        Toast.show(json.success, 'success');
        Poller.resetHash();
        if (typeof onSuccess === 'function') {
          try { onSuccess(json.data || {}); } catch(e) { console.error(e); setTimeout(() => Poller._tick(), 500); }
        }
      } else {
        Toast.show(json.error || 'Action failed.', 'error');
      }
    } catch (e) {
      Toast.show('Could not complete. Please check your connection.', 'error');
      console.error('AjaxForm.action error:', e);
    } finally {
      Poller.resume('request');
    }
  }
};

/* ================================================================
   SMART POLLER
   Polls poll.php every 5 seconds.
   Pauses when: modal open, user typing, tab hidden, request active.
   All polling errors are completely silent — never shown to user.
================================================================ */
const Poller = {
  module:         null,
  rebuildFn:      null,
  interval:       5000,
  lastHash:       null,
  timer:          null,
  pauses:         {},
  indicator:      null,
  _immediateTimer: null,
  _ticking:       false,    // prevents concurrent _tick() calls

  init(module, rebuildFn) {
    this.module    = module;
    this.rebuildFn = rebuildFn;
    this._buildIndicator();
    this._watchInputFocus();
    this._watchVisibility();
    this._start();
  },

  _start() {
    if (this.timer) return;
    this.timer = setInterval(() => this._tick(), this.interval);
  },

  async _tick() {
    // Skip if paused or already mid-request
    if (Object.values(this.pauses).some(Boolean)) return;
    if (this._ticking) return;
    this._ticking = true;

    try {
      const res  = await fetch(`poll.php?module=${this.module}&_=${Date.now()}`);
      if (!res.ok) return;                     // HTTP error — silent
      const json = await res.json();
      if (!json.ok) return;                    // App-level error — silent
      if (json.hash === this.lastHash) return; // No change — silent

      this.lastHash = json.hash;
      this._flash();

      if (typeof this.rebuildFn === 'function') {
        try {
          this.rebuildFn(json.rows);
        } catch (rebuildErr) {
          console.error('Poller rebuild error:', rebuildErr);
        }
      }
    } catch (_) {
      // Network blip — completely silent, never shown to user
    } finally {
      this._ticking = false;
    }
  },

  pause(reason) {
    this.pauses[reason] = true;
    if (this.indicator) this.indicator.style.opacity = '0.3';
  },

  resume(reason) {
    delete this.pauses[reason];
    const anyPause = Object.values(this.pauses).some(Boolean);
    if (this.indicator) this.indicator.style.opacity = anyPause ? '0.3' : '1';
  },

  resetHash() {
    this.lastHash = null;
    // Schedule an immediate poll ~800ms after our own write
    // so other users see the change quickly
    clearTimeout(this._immediateTimer);
    this._immediateTimer = setTimeout(() => this._tick(), 800);
  },

  _watchInputFocus() {
    document.addEventListener('focusin', (e) => {
      const tag = e.target.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        this.pause('input');
      }
    });
    document.addEventListener('focusout', (e) => {
      const tag = e.target.tagName;
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        setTimeout(() => {
          const active = document.activeElement;
          const activeTag = active ? active.tagName : '';
          if (activeTag !== 'INPUT' && activeTag !== 'TEXTAREA' && activeTag !== 'SELECT') {
            this.resume('input');
          }
        }, 200);
      }
    });
  },

  _watchVisibility() {
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        this.pause('hidden');
      } else {
        this.resume('hidden');
      }
    });
  },

  _buildIndicator() {
    const topbarActions = document.querySelector('.topbar-actions');
    if (!topbarActions) return;

    const wrap = document.createElement('div');
    wrap.style.cssText = 'display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted);font-family:var(--font-mono)';

    const dot = document.createElement('span');
    dot.style.cssText = 'width:6px;height:6px;border-radius:50%;background:var(--green);display:inline-block;transition:opacity .3s';
    dot.title = 'Live — syncs every 5 seconds';

    const label = document.createElement('span');
    label.textContent = 'Live';

    wrap.appendChild(dot);
    wrap.appendChild(label);
    topbarActions.insertBefore(wrap, topbarActions.firstChild);
    this.indicator = dot;
  },

  _flash() {
    if (!this.indicator) return;
    this.indicator.style.background   = 'var(--accent)';
    this.indicator.style.boxShadow    = '0 0 6px var(--accent)';
    setTimeout(() => {
      this.indicator.style.background = 'var(--green)';
      this.indicator.style.boxShadow  = '0 0 5px rgba(22,163,74,.5)';
    }, 400);
  }
};
