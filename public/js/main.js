// =========================================================
// main.js — Shared helpers used across all pages
// =========================================================

/** Highlight the current page's sidebar link (used on dashboard pages). */
function initActiveNav() {
  const current = document.body.getAttribute('data-page');
  if (!current) return;
  document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.classList.remove('active');
    if (link.getAttribute('data-page') === current) {
      link.classList.add('active');
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initActiveNav();
});
