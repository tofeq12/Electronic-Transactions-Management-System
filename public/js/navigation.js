// navigation.js — one delegated click handler (bound once) for [data-goto] + language cards
(function () {
  if (window.__navBound) return; window.__navBound = true;
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);

  document.addEventListener('click', e => {
    const g = e.target.closest('[data-goto]');
    if (g) { e.preventDefault(); go(g.getAttribute('data-goto')); return; }
    const card = e.target.closest('.lang-card');
    if (card) {
      document.querySelectorAll('.lang-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      Lang.set(card.dataset.lang);            // saves language + switches RTL/LTR immediately
    }
  });

  // language screen: preselect the saved language
  document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.lang-card'); if (!cards.length) return;
    cards.forEach(c => c.classList.toggle('selected', c.dataset.lang === Lang.get()));
  });
})();
