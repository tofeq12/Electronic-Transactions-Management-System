// auth-scene.js — shared illustration (reference image, sliced into layers) for Login / Register
(function () {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const B = new URL('../images/', document.currentScript.src).href;
  const scene = `<div class="ax-hero"><div class="ax-par"><div class="ax-hero-float">
    <img class="ax-layer ax-l1" src="${B}hero-laptop.png" alt="">
    <img class="ax-layer ax-l2" src="${B}hero-cards.png" alt="">
    <img class="ax-layer ax-l3" src="${B}hero-phone.png" alt="">
    <span class="ax-shield-glow"></span></div></div>
    <svg class="ax-spark" viewBox="0 0 580 715" aria-hidden="true"><g fill="#8fa8ff"><circle class="ax-tw" cx="70" cy="150" r="6" style="--fd:0s"/><circle class="ax-tw" cx="540" cy="330" r="5" style="--fd:1.4s"/><circle class="ax-tw" cx="330" cy="660" r="5" style="--fd:2.2s"/></g><g stroke="#9db2ff" stroke-width="3" stroke-linecap="round"><path class="ax-tw" d="M470 220v16M462 228h16" style="--fd:.8s"/><path class="ax-tw" d="M110 640v14M103 647h14" style="--fd:1.9s"/></g></svg></div>`;
  let bound = false;
  function init() {
    document.querySelectorAll('[data-ax-scene]').forEach(el => { if (!el.firstChild) el.innerHTML = scene; });
    document.querySelectorAll('[data-year]').forEach(n => n.textContent = new Date().getFullYear());
    const el = document.querySelector('.ax-scene');
    if (el && !bound && !reduce && matchMedia('(pointer:fine)').matches) {
      bound = true;   // subtle hover parallax (bound once; always targets the current scene)
      addEventListener('pointermove', e => {
        const s = document.querySelector('.ax-scene'); if (!s) return;
        s.style.setProperty('--mx', ((e.clientX / innerWidth) * 2 - 1).toFixed(3));
        s.style.setProperty('--my', ((e.clientY / innerHeight) * 2 - 1).toFixed(3));
      }, { passive: true });
    }
  }
  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();
