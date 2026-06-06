/* Email Bounce Monitor — small client-side enhancements */
(function () {
  'use strict';

  // ----- Theme persistence -----
  var html = document.documentElement;
  var STORAGE_KEY = 'ebm_theme';

  function applyStoredTheme() {
    try {
      var t = localStorage.getItem(STORAGE_KEY);
      if (t === 'dark' || t === 'light') {
        html.setAttribute('data-theme', t);
      }
    } catch (e) { /* ignore */ }
  }
  applyStoredTheme();

  document.addEventListener('click', function (e) {
    var btn = e.target.closest('#themeToggle');
    if (!btn) return;
    var current = html.getAttribute('data-theme') === 'light' ? 'light' : 'dark';
    var next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    try { localStorage.setItem(STORAGE_KEY, next); } catch (e) {}
  });

  // ----- Navbar active link highlight -----
  var path = location.pathname.replace(/\/+$/, '');
  document.querySelectorAll('.navbar__link[data-match]').forEach(function (a) {
    var match = a.getAttribute('data-match');
    var exact = a.getAttribute('data-match-exact') === '1';
    var p = path;
    if (p === match || (!exact && p.indexOf(match) === 0)) {
      a.classList.add('is-active');
    }
  });

  // ----- Confirm forms -----
  document.addEventListener('submit', function (e) {
    var form = e.target.closest('form[data-confirm]');
    if (!form) return;
    var msg = form.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
      e.preventDefault();
    }
  });

  // ----- Flash helper -----
  function flash(type, message, ttl) {
    var c = document.getElementById('flash-container');
    if (!c) { alert(message); return; }
    var div = document.createElement('div');
    div.className = 'flash flash--' + (type || 'info');
    div.innerHTML = message;
    c.appendChild(div);
    setTimeout(function () { div.style.opacity = '0'; div.style.transition = 'opacity .3s'; }, ttl || 5000);
    setTimeout(function () { div.remove(); }, (ttl || 5000) + 400);
  }
  window.EBM = window.EBM || {};
  window.EBM.flash = flash;

  // ----- Server-side flashes already injected by layout (data-flash JSON) -----
  // (No-op here; layout already injects via partial.)

  // ----- Modal helpers (data-modal-open / data-modal-close / <dialog>) -----
  document.addEventListener('click', function (e) {
    var opener = e.target.closest('[data-modal-open]');
    if (opener) {
      var sel = opener.getAttribute('data-modal-open');
      var d = document.querySelector(sel);
      if (d && typeof d.showModal === 'function') d.showModal();
    }
    var closer = e.target.closest('[data-modal-close]');
    if (closer) {
      var d2 = closer.closest('dialog');
      if (d2) d2.close();
    }
  });

  // ----- Bounce details (delegated AJAX) -----
  var dialog = document.getElementById('detailsDialog');
  var body = document.getElementById('detailsBody');
  var title = document.getElementById('detailsTitle');
  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : (document.body.getAttribute('data-csrf') || '');

  function openBounceDetails(d) {
    if (!dialog) return;
    body.innerHTML = '<p class="muted">Loading…</p>';
    if (title) title.textContent = 'Bounce details';
    if (typeof dialog.showModal === 'function') dialog.showModal();
    var baseUrl = (document.body.getAttribute('data-base-url') || '') + '/bounces/details';
    var url = baseUrl + '?id=' + encodeURIComponent(d.id) +
              '&mailbox=' + encodeURIComponent(d.mailbox) +
              (d.folder ? '&folder=' + encodeURIComponent(d.folder) : '');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Csrf-Token': csrfToken } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j) { body.innerHTML = '<p class="muted">Failed to load details.</p>'; return; }
        var rows = [
          ['Failed recipient', '<strong>' + esc(j.failed && j.failed.length ? j.failed.join(', ') : '—') + '</strong>'],
          ['Reason',           esc(j.reason || '—')],
          ['Original sender',  esc(j.from || '—')],
          ['Detected in',      esc(j.mailbox || '—')],
          ['Received',         esc(j.received_human || j.received || '—')],
          ['Subject',          '<code>' + esc(j.subject || '—') + '</code>'],
        ];
        var html = '<table class="table"><tbody>' + rows.map(function (r) { return '<tr><th style="width:160px">' + r[0] + '</th><td>' + r[1] + '</td></tr>'; }).join('') + '</tbody></table>';
        if (j.body) {
          html += '<h3 style="margin-top:14px">Body</h3>';
          if (j.bodyType === 'html') {
            html += '<div style="max-height:340px;overflow:auto;background:var(--surface-2);padding:12px;border-radius:8px">' + j.body + '</div>';
          } else {
            html += '<pre style="white-space:pre-wrap;background:var(--surface-2);padding:12px;border-radius:8px;max-height:340px;overflow:auto">' + esc(j.body) + '</pre>';
          }
        }
        body.innerHTML = html;
        var first = (j.failed && j.failed[0]) || '';
        if (title) title.textContent = 'Bounce: ' + (first || '');
      })
      .catch(function () { body.innerHTML = '<p class="muted">Network error.</p>'; });
  }

  document.addEventListener('click', function (e) {
    // View body button — only way to open the dialog
    var btn = e.target.closest('.js-view-body');
    if (btn) {
      e.preventDefault();
      openBounceDetails({
        id:      btn.getAttribute('data-message-id'),
        mailbox: btn.getAttribute('data-mailbox'),
        folder:  btn.getAttribute('data-folder'),
      });
    }
  });

  function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

  // ----- API key reveal -----
  var reveal = document.getElementById('apiKeyReveal');
  if (reveal) {
    reveal.addEventListener('click', function () {
      var v = reveal.getAttribute('data-current-key') || '';
      var slot = document.getElementById('apiKeyValue');
      if (slot) slot.textContent = v || '— not set —';
    });
  }

  // Search/filters only submit on Apply button click (no auto-submit on typing).
})();
