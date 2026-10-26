// motion.js — one reveal system + no-reload page transitions for every screen
(function () {
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const ROUTABLE = /\//;   // clean Laravel URLs: every same-origin path is routable
  const DASH_PATH = /^\/(dashboard|users|transactions|reports|notifications|settings|departments|activity-log|urgent|profile)(\/|$)/;
  const AUTH_PATH = /^\/(login|register|forgot-password|verify-code|reset-password)(\/|$)/;

  // ---- Reveal sequences: [selector, effect, gap-before(s), idleFloat?] (effects: fade|up|scale|slide)
  const STEP = .08;
  const SEQ = {
    splash1: [['.splash-header', 'fade', 0], ['.hero-icon-wrap', 'scale', .15], ['.hero-dot', 'fade', .05], ['.chip-lock', 'scale', .08, 1], ['.chip-doc', 'scale', .08, 1], ['.eyebrow-badge', 'up', .1], ['.splash-body h1', 'up'], ['.splash-body .subtitle', 'up'], ['.splash-body .btn', 'scale'], ['.splash-footer', 'fade']],
    splash2: [['.splash-header', 'fade', 0], ['.feature-card', 'slide', .15], ['.eyebrow-badge', 'up', .1], ['.splash-hero-text h1', 'up'], ['.splash-hero-text .subtitle', 'up'], ['.splash-hero-text .btn', 'scale'], ['.splash-footer', 'fade']],
    language: [['.simple-header', 'fade', 0], ['.round-icon', 'scale', .15], ['.step-label', 'fade'], ['.simple-body h1', 'up'], ['.simple-body .subtitle', 'up'], ['.lang-card', 'up'], ['.simple-body .btn', 'scale']],
    'auth-choice': [['.simple-header', 'fade', 0], ['.round-icon', 'scale', .15], ['.step-label', 'fade'], ['.simple-body h1', 'up'], ['.simple-body .subtitle', 'up'], ['.choice-row', 'up'], ['.change-lang-link', 'fade']],
    onboarding: [['.ob-bg', 'fade', 0], ['.ob-top', 'fade', .05], ['.ob-art', 'scale', .15], ['.ob-chip', 'scale', .1, 1], ['.ob-card', 'up', .1], ['.ob-title', 'up'], ['.ob-desc', 'up'], ['.ob-dots', 'fade'], ['.ob-actions', 'up']],
    settings: [['.st-main .topbar', 'up', 0], ['.st-card', 'up', .08], ['.st-field', 'up', .03], ['.st-card .btn, .st-btn-soft', 'scale', .05]],
    reports: [['.rp-main .topbar', 'up', 0], ['.rp-filter', 'up', .05], ['.rp-stat', 'up', .06], ['.rp-chart', 'scale', .12], ['.rp-btn', 'up', .06]],
    dash: [['.main-area > *', 'up', 0], ['.stat-card', 'up', .04], ['.panel .field', 'up', .04], ['.empty-state', 'scale', .1], ['.main-area tbody tr', 'up', .02]],
    auth: [['.ax-waves', 'fade', 0], ['.ax-lang', 'fade', .05], ['.ax-l1', 'fade', .12], ['.ax-l2', 'up', .1], ['.ax-l3', 'up', .1], ['.ax-shield-glow', 'fade', .1], ['.ax-spark', 'fade', .05],
    ['.ax-card', 'up', .12], ['.ax-logo', 'scale', .1], ['.ax-system', 'up', .06], ['.ax-title', 'up', .06], ['.ax-field', 'up', .07], ['.ax-row', 'fade'], ['.ax-submit', 'scale'], ['.ax-foot', 'fade'], ['.ax-copy', 'fade']]
  };
  const pageKey = () => {
    const p = document.body.dataset.page || '';
    if (document.querySelector('.dash-shell')) return p === 'reports' || p === 'settings' ? p : 'dash';
    if (p === 'onboarding') return p;
    return p === 'login' || p === 'register' || p === 'forgot' || p === 'verify' || p === 'reset' ? 'auth' : p === 'splash1' || p === 'splash2' || p === 'language' || p === 'auth-choice' ? p : null;
  };

  function play(root, opts) {
    opts = opts || {};
    const seq = SEQ[pageKey()];
    if (!seq || reduce) return;
    let d = .2, items = [];
    seq.forEach(([sel, fx, gap, fl]) => {
      if (opts.skipScene && /^\.ax-(l\d|shield-glow|spark|waves)/.test(sel)) return;
      root.querySelectorAll(sel).forEach((el, n) => {
        d += (n === 0 && gap !== undefined) ? gap : STEP;
        items.push([el, fx, d, fl]);
      });
    });
    const k = Math.min(1, 1.15 / Math.max(d, .01));       // whole sequence stays ≈1.2s
    items.forEach(([el, fx, dd, fl]) => {
      el.style.setProperty('--mo-a', 'mo-' + fx);
      el.style.setProperty('--mo-d', (dd * k).toFixed(3) + 's');
      el.classList.add('mo-i');
      el.addEventListener('animationend', function done(e) {
        if (e.target !== el) return;
        el.removeEventListener('animationend', done);
        el.classList.remove('mo-i');
        if (fl) el.classList.add('mo-float-s');
      });
    });
    // idle floating for illustrative elements, started after the reveal
    setTimeout(() => {
      root.querySelectorAll('.hero-icon').forEach(e => e.classList.add('mo-float-s'));
      root.querySelectorAll('.ax-hero-float').forEach(e => e.classList.add('mo-float'));
    }, (d * k + .7) * 1000);
  }

  // ---- Router: swap screens without reloading the page
  const cache = new Map();
  const abs = u => new URL(u, location.href);
  const routable = u => u.origin === location.origin && /^https?:$/.test(u.protocol) && ROUTABLE.test(u.pathname);
  const fetchPage = u => {
    if (!cache.has(u.href)) cache.set(u.href, fetch(u.href).then(r => { if (!r.ok) throw 0; return r.text(); }));
    return cache.get(u.href);
  };
  const wait = ms => new Promise(r => setTimeout(r, ms));
  const rootOf = () => [...document.body.children].find(n => n.tagName !== 'SCRIPT');
  const loadEl = (el, tag) => new Promise(res => { el.onload = el.onerror = res; setTimeout(res, 2000); document[tag === 'link' ? 'head' : 'body'].appendChild(el); });
  let busy = false;
  // load stylesheets / scripts the target dashboard page needs that are not on the page yet (additive)
  async function addAssets(doc, url) {
    const haveCss = [...document.head.querySelectorAll('link[rel=stylesheet]')].map(l => l.href);
    const css = [...doc.querySelectorAll('link[rel=stylesheet]')].map(l => new URL(l.getAttribute('href'), url).href).filter(h => !haveCss.includes(h));
    await Promise.all(css.map(h => { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = h; return loadEl(l, 'link'); }));
    const haveJs = [...document.scripts].map(s => s.src);
    for (const s of doc.querySelectorAll('body script[src]')) {
      const h = new URL(s.getAttribute('src'), url).href;
      if (!haveJs.includes(h)) { const el = document.createElement('script'); el.src = h; el.async = false; await loadEl(el, 'script'); }
    }
  }

  async function go(to, push) {
    const url = abs(to);
    const hardNav = () => { location.href = url.href; };
    if (busy) return;
    if (!routable(url) || location.protocol === 'file:') {
      busy = true;
      rootOf()?.classList.add('mo-out');
      return void setTimeout(hardNav, reduce ? 0 : 230);
    }
    busy = true;
    try {
      const oldRoot = rootOf();
      const oldMain = oldRoot.querySelector && oldRoot.querySelector('.main-area');
      const dashNav = !!oldMain && DASH_PATH.test(url.pathname);
      const sameFamily = oldRoot.classList.contains('ax-page') && AUTH_PATH.test(url.pathname);
      const [html] = await Promise.all([fetchPage(url), (() => {
        (dashNav ? oldMain : sameFamily ? oldRoot.querySelector('.ax-card') : oldRoot).classList.add('mo-out');
        return wait(reduce ? 0 : 240);
      })()]);
      const doc = new DOMParser().parseFromString(html, 'text/html');

      if (dashNav && doc.querySelector('.main-area')) {
        await addAssets(doc, url);   // page-specific css/js (e.g. reports.css / reports.js)
        if (push !== false) history.pushState({}, '', url.href);
        document.title = doc.title;
        document.body.dataset.page = doc.body.dataset.page || '';
        const nm = document.importNode(doc.querySelector('.main-area'), true);
        oldMain.replaceWith(nm);
        document.querySelectorAll('.sidebar-nav a').forEach(a => a.classList.toggle('active', a.dataset.page === document.body.dataset.page));
        document.querySelector('.dash-shell').classList.remove('sb-open');
        window.scrollTo(0, 0);
        document.dispatchEvent(new Event('DOMContentLoaded'));   // re-init menu button, tables, forms
        play(document.querySelector('.dash-shell'));
        return;
      }
      // styles needed by the next screen
      const have = [...document.head.querySelectorAll('link[rel=stylesheet]')];
      const need = [...doc.querySelectorAll('link[rel=stylesheet]')].map(l => new URL(l.getAttribute('href'), url).href);
      await Promise.all(need.filter(h => !have.some(l => l.href === h)).map(h => {
        const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = h; return loadEl(l, 'link');
      }));

      if (push !== false) history.pushState({}, '', url.href);
      document.title = doc.title;
      document.body.dataset.page = doc.body.dataset.page || '';

      // keep the same illustration between Login <-> Register (background stays put)
      const keepScene = sameFamily ? oldRoot.querySelector('.ax-scene') : null;
      const fresh = [...doc.body.children].filter(n => n.tagName !== 'SCRIPT').map(n => document.importNode(n, true));
      oldRoot.remove();
      fresh.forEach(n => document.body.insertBefore(n, document.body.querySelector('script')));
      const newRoot = fresh[0];
      if (keepScene) { newRoot.classList.add('mo-keep'); newRoot.querySelector('.ax-scene').replaceWith(keepScene); }
      have.filter(l => !need.includes(l.href)).forEach(l => l.remove());

      // scripts the next screen needs (loaded once)
      const haveJs = [...document.scripts].map(s => s.src);
      for (const s of doc.querySelectorAll('body script[src]')) {
        const h = new URL(s.getAttribute('src'), url).href;
        if (!haveJs.includes(h)) { const el = document.createElement('script'); el.src = h; el.async = false; await loadEl(el, 'script'); }
      }
      window.scrollTo(0, 0);
      window.__moSkipScene = !!keepScene;
      document.dispatchEvent(new Event('DOMContentLoaded'));   // re-run each screen's init
    } catch (e) { return location.href = url.href; }
    finally { busy = false; }
  }
  window.MotionNav = { go: u => go(u) };
  window.MotionPlay = play;

  document.addEventListener('click', e => {
    const a = e.target.closest && e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.button || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
    const h = a.getAttribute('href');
    if (!h || h === '#' || h.startsWith('mailto:') || h.startsWith('tel:')) return;
    e.preventDefault(); go(h);
  });
  // warm the cache so the swap feels instant
  const warm = e => {
    const a = e.target.closest && e.target.closest('a[href],[data-goto]'); if (!a) return;
    const u = abs(a.getAttribute('href') || a.getAttribute('data-goto')); if (routable(u) && location.protocol !== 'file:') fetchPage(u).catch(() => cache.delete(u.href));
  };
  document.addEventListener('pointerover', warm, { passive: true });
  document.addEventListener('touchstart', warm, { passive: true });
  addEventListener('popstate', () => go(location.href, false));

  document.addEventListener('DOMContentLoaded', () => {
    const root = rootOf();
    if (root && !root.dataset.moDone) { root.dataset.moDone = 1; play(root, { skipScene: window.__moSkipScene }); }
    window.__moSkipScene = false;
  });
})();
