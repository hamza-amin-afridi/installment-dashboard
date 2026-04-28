// MotoLease Pro — Global JavaScript
'use strict';

// ===== Theme Toggle =====
const themeToggle = document.getElementById('themeToggle');
const html = document.documentElement;

function applyTheme(theme) {
  html.setAttribute('data-theme', theme);
  localStorage.setItem('ml_theme', theme);
  if (themeToggle) themeToggle.textContent = theme === 'dark' ? '🌙' : '☀️';
}

(function initTheme() {
  const saved = localStorage.getItem('ml_theme') || 'dark';
  applyTheme(saved);
})();

if (themeToggle) {
  themeToggle.addEventListener('click', () => {
    const current = html.getAttribute('data-theme');
    applyTheme(current === 'dark' ? 'light' : 'dark');
  });
}

// ===== Sidebar Toggle =====
const sidebar      = document.getElementById('sidebar');
const sidebarToggle= document.getElementById('sidebarToggle');
const mainWrapper  = document.getElementById('mainWrapper');

// Mobile overlay
const overlay = document.createElement('div');
overlay.className = 'mobile-overlay';
document.body.appendChild(overlay);

function isMobile() { return window.innerWidth <= 900; }

function closeSidebar() {
  if (isMobile()) {
    sidebar && sidebar.classList.remove('mobile-open');
    overlay.classList.remove('visible');
  } else {
    sidebar && sidebar.classList.add('collapsed');
    mainWrapper && mainWrapper.classList.add('expanded');
  }
  localStorage.setItem('ml_sidebar', 'closed');
}

function openSidebar() {
  if (isMobile()) {
    sidebar && sidebar.classList.add('mobile-open');
    overlay.classList.add('visible');
  } else {
    sidebar && sidebar.classList.remove('collapsed');
    mainWrapper && mainWrapper.classList.remove('expanded');
  }
  localStorage.setItem('ml_sidebar', 'open');
}

(function initSidebar() {
  if (!isMobile()) {
    const saved = localStorage.getItem('ml_sidebar');
    if (saved === 'closed') closeSidebar();
  }
})();

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => {
    const isOpen = isMobile()
      ? sidebar.classList.contains('mobile-open')
      : !sidebar.classList.contains('collapsed');
    isOpen ? closeSidebar() : openSidebar();
  });
}

overlay.addEventListener('click', closeSidebar);

window.addEventListener('resize', () => {
  if (!isMobile()) {
    sidebar && sidebar.classList.remove('mobile-open');
    overlay.classList.remove('visible');
  }
});

// ===== Flash Message Auto-dismiss =====
const flashMsg = document.getElementById('flashMsg');
if (flashMsg) {
  setTimeout(() => {
    flashMsg.style.transition = 'opacity 0.5s ease';
    flashMsg.style.opacity = '0';
    setTimeout(() => flashMsg.remove(), 500);
  }, 4000);
}

// ===== Confirm Delete =====
document.addEventListener('click', function(e) {
  const btn = e.target.closest('[data-confirm]');
  if (btn) {
    const msg = btn.dataset.confirm || 'Are you sure?';
    if (!confirm(msg)) e.preventDefault();
  }
});

// ===== Live Search (filter table rows) =====
function initTableSearch(inputId, tableId) {
  const input = document.getElementById(inputId);
  const table = document.getElementById(tableId);
  if (!input || !table) return;
  input.addEventListener('input', function() {
    const q = this.value.toLowerCase().trim();
    const rows = table.querySelectorAll('tbody tr');
    rows.forEach(row => {
      const text = row.textContent.toLowerCase();
      row.style.display = text.includes(q) ? '' : 'none';
    });
  });
}

// ===== Format number inputs with commas =====
function numberWithCommas(n) {
  return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

// ===== Lease calculation preview =====
function calcLeasePreview(price, markup, months, priceEl, installEl) {
  if (!price || !markup || !months) return;
  const total   = price * (1 + markup / 100);
  const monthly = total / months;
  if (priceEl)   priceEl.textContent   = '₨ ' + numberWithCommas(total.toFixed(2));
  if (installEl) installEl.textContent = '₨ ' + numberWithCommas(monthly.toFixed(2));
}

// ===== Modal helpers =====
function openModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.add('open');
}
function closeModal(id) {
  const m = document.getElementById(id);
  if (m) m.classList.remove('open');
}

document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
  }
  const trigger = e.target.closest('[data-modal]');
  if (trigger) openModal(trigger.dataset.modal);
  const closer = e.target.closest('[data-close-modal]');
  if (closer) closeModal(closer.dataset.closeModal);
});

// ===== Score bar animation =====
document.querySelectorAll('.score-fill').forEach(bar => {
  const w = bar.style.width;
  bar.style.width = '0';
  requestAnimationFrame(() => {
    setTimeout(() => { bar.style.width = w; }, 100);
  });
});

// ===== Progress bar animation =====
document.querySelectorAll('.progress-bar-fill').forEach(bar => {
  const w = bar.style.width;
  bar.style.width = '0';
  requestAnimationFrame(() => {
    setTimeout(() => { bar.style.width = w; }, 100);
  });
});
