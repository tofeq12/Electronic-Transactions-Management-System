// shell.js — dashboard hamburger + sidebar (desktop: collapse, <=1024px: drawer with overlay)
(function () {
  const MENU = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>';
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const mobile = () => matchMedia('(max-width:1024px)').matches;
  function init() {
    const shell = document.querySelector('.dash-shell'); if (!shell) return;
    if (!shell.querySelector('.sb-overlay')) { const o = document.createElement('div'); o.className = 'sb-overlay'; shell.appendChild(o); }
    const main = shell.querySelector('.main-area');
    if (main && !main.querySelector('.menu-btn')) {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'menu-btn'; b.setAttribute('aria-label', L('القائمة')); b.innerHTML = MENU;
      const h1 = main.querySelector('.topbar h1');
      if (h1) h1.prepend(b); else { const bar = document.createElement('div'); bar.className = 'topbar-lite'; bar.appendChild(b); main.prepend(bar); }
    }
  }
  if (!window.__shellBound) {
    window.__shellBound = true;
    document.addEventListener('click', e => {
      const shell = document.querySelector('.dash-shell'); if (!shell) return;
      if (e.target.closest('.menu-btn')) shell.classList.toggle(mobile() ? 'sb-open' : 'sb-collapsed');
      else if (e.target.closest('.sb-overlay') || (mobile() && e.target.closest('.sidebar a'))) shell.classList.remove('sb-open');
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') document.querySelector('.dash-shell')?.classList.remove('sb-open'); });
  }
  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();
