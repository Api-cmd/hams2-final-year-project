// ===========================================================
// js/main.js — Shared JavaScript for all HAMS pages
// ===========================================================
// Functions defined here are available on every page
// because every HTML file links this script in its <body>.
// Page-specific JS goes in <script> tags on that page itself.
// ===========================================================


// -----------------------------------------------------------
// escapeHtml()
// Converts special characters to safe HTML entities.
// ALWAYS use this when inserting user data into the page.
//
// Why: if a patient's name contains < or > or & and you
// insert it raw, the browser may treat it as HTML/code.
// This is called XSS (Cross-Site Scripting) — a security bug.
//
// Example:
//   escapeHtml('<script>alert("hacked")</script>')
//   returns: '&lt;script&gt;alert(&quot;hacked&quot;)&lt;/script&gt;'
// -----------------------------------------------------------
function escapeHtml(str) {
  if (str === null || str === undefined) return '';
  return String(str)
    .replace(/&/g,  '&amp;')
    .replace(/</g,  '&lt;')
    .replace(/>/g,  '&gt;')
    .replace(/"/g,  '&quot;')
    .replace(/'/g,  '&#39;');
}


// -----------------------------------------------------------
// formatDate(dateStr)
// Converts a database date string (YYYY-MM-DD) to a
// human-readable format (e.g. 15/06/2025).
//
// To change the date format, edit the options object.
// -----------------------------------------------------------
function formatDate(dateStr) {
  if (!dateStr) return '—';
  const date = new Date(dateStr + 'T00:00:00'); // force local time
  return date.toLocaleDateString('en-GB', {
    day:   '2-digit',
    month: '2-digit',
    year:  'numeric'
  });
}


// -----------------------------------------------------------
// formatTime(timeStr)
// Converts a 24-hour time string (HH:MM:SS) from the DB
// to a 12-hour AM/PM format (e.g. 08:30 AM).
// -----------------------------------------------------------
function formatTime(timeStr) {
  if (!timeStr) return '—';
  const [h, m] = timeStr.split(':');
  const date = new Date();
  date.setHours(parseInt(h), parseInt(m));
  return date.toLocaleTimeString('en-GB', {
    hour:   '2-digit',
    minute: '2-digit',
    hour12: true
  }).toUpperCase();
}


// -----------------------------------------------------------
// statusBadge(status)
// Returns an HTML string for a coloured status pill.
// The class name must match what we defined in style.css.
//
// To change a badge colour, go to style.css and find
// .badge-seen { background: ... } and edit it there.
// -----------------------------------------------------------
function statusBadge(status) {
  return `<span class="badge badge-${status}">${status}</span>`;
}


// -----------------------------------------------------------
// showAlert(message, type, containerId)
// Displays an alert box inside any element on the page.
//
// type: 'success' | 'error' | 'info' | 'warning'
// containerId: the id of the element to put the alert in
//
// Example:
//   showAlert('Booking successful!', 'success', 'alert-box');
// -----------------------------------------------------------
// -----------------------------------------------------------
// Notification system (modern toasts & confirm dialog)
// - Replaces native alert()/confirm()/prompt() UX
// - Notifications appear top-right, stack, have close buttons,
//   colored by type, and animate in/out.
// -----------------------------------------------------------
function _ensureNotificationsContainer() {
  if (window._notifContainer) return window._notifContainer;
  const c = document.createElement('div');
  c.className = 'notifications-container top-right';
  document.body.appendChild(c);
  window._notifContainer = c;
  return c;
}

function notify(message, type = 'info', { duration = 4000, closable = true } = {}) {
  const container = _ensureNotificationsContainer();
  const el = document.createElement('div');
  el.className = `notification ${type}`;
  el.setAttribute('role', 'status');

  const content = document.createElement('div');
  content.className = 'notification-content';
  content.innerHTML = `<div class="notification-message">${escapeHtml(message)}</div>`;
  el.appendChild(content);

  if (closable) {
    const btn = document.createElement('button');
    btn.className = 'notification-close';
    btn.setAttribute('aria-label', 'Close');
    btn.innerHTML = '&times;';
    btn.onclick = () => close();
    el.appendChild(btn);
  }

  function close() {
    el.classList.remove('enter');
    el.classList.add('exit');
    // wait for animation then remove
    setTimeout(() => { try { container.removeChild(el); } catch (e) {} }, 400);
  }

  container.appendChild(el);

  // Trigger CSS animation
  requestAnimationFrame(() => el.classList.add('enter'));

  if (duration > 0) {
    setTimeout(close, duration);
  }

  return { close };
}

// Backwards-compatible wrappers
function showToast(message, type = 'info', timeout = 3000) {
  notify(message, type, { duration: timeout, closable: true });
}

function showAlert(message, type = 'info', containerId = 'alert-box') {
  // Keep API compatibility: show modern notification instead of inline alert
  notify(message, type, { duration: 5000 });
}

// Confirm dialog implemented as a modal returning Promise<boolean>
function confirmDialog(message, { confirmText = 'Confirm', cancelText = 'Cancel' } = {}) {
  return new Promise(resolve => {
    // create overlay
    const overlay = document.createElement('div');
    overlay.className = 'confirm-overlay';

    const box = document.createElement('div');
    box.className = 'confirm-box';

    const msg = document.createElement('div');
    msg.className = 'confirm-message';
    msg.innerHTML = `<p>${escapeHtml(message)}</p>`;

    const actions = document.createElement('div');
    actions.className = 'confirm-actions';

    const btnCancel = document.createElement('button');
    btnCancel.className = 'btn btn-ghost';
    btnCancel.textContent = cancelText;
    btnCancel.onclick = () => { close(false); };

    const btnConfirm = document.createElement('button');
    btnConfirm.className = 'btn btn-primary';
    btnConfirm.textContent = confirmText;
    btnConfirm.onclick = () => { close(true); };

    actions.appendChild(btnCancel);
    actions.appendChild(btnConfirm);

    box.appendChild(msg);
    box.appendChild(actions);
    overlay.appendChild(box);
    document.body.appendChild(overlay);

    // focus management
    btnConfirm.focus();

    function close(result) {
      overlay.classList.add('closing');
      setTimeout(() => { try { document.body.removeChild(overlay); } catch (e) {} resolve(result); }, 220);
    }
  });
}

// Backwards-compatible wrapper for confirmAction (uses confirmDialog)
function confirmAction(message) {
  return confirmDialog(message);
}


// -----------------------------------------------------------
// setLoading(buttonId, isLoading)
// Disables a button and changes its text while a fetch()
// request is in progress, then restores it when done.
//
// This prevents the user from clicking "Book" twice.
//
// Example:
//   setLoading('submit-btn', true);   // before fetch
//   setLoading('submit-btn', false);  // after fetch
// -----------------------------------------------------------
function setLoading(buttonId, isLoading) {
  const btn = document.getElementById(buttonId);
  if (!btn) return;

  if (isLoading) {
    btn.dataset.originalText = btn.textContent; // save original text
    btn.textContent = 'Please wait...';
    btn.disabled    = true;
  } else {
    btn.textContent = btn.dataset.originalText || 'Submit';
    btn.disabled    = false;
  }
}
