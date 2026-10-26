// reports.js — Reports screen: animated counters, SVG bar chart, Excel / PDF export.
// الأرقام تُحسب من معاملات الخادم الحقيقية المحقونة في ITQAN.transactions (ضمن الفترة المحددة).
(function () {
  'use strict';

  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);

  const CATEGORIES = [L('تحويل مبالغ'), L('شراء أجهزة'), L('دفع فواتير'), L('تعاقد'), L('أخرى')];
  // الأرقام تُحسب الآن من معاملات الخادم الحقيقية (ITQAN.transactions) داخل الفترة المحددة.
  const DATA = {
    transactions: { label: L('تقرير المعاملات') },
    monthly: { label: L('تقرير شهري') },
    yearly: { label: L('تقرير سنوي') }
  };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const fmt = n => n.toLocaleString('en-US');
  const slashDate = v => v.replace(/-/g, '/');

  function scale(max) {
    const step = max <= 50 ? 10 : max <= 100 ? 20 : 50;
    return { step, top: Math.ceil(max / step) * step };
  }

  function chartSvg(w, bars, animate, delay) {
    const compact = w < 520;
    const h = compact ? 240 : 290;
    const m = { t: 12, r: 10, b: compact ? 44 : 36, l: 36 };
    const pw = w - m.l - m.r, ph = h - m.t - m.b;
    const { step, top } = scale(Math.max(...bars));
    const slot = pw / bars.length, bw = Math.min(slot * .46, 54), r = Math.min(4, bw / 2);
    const base = m.t + ph;

    let grid = '';
    for (let v = 0; v <= top; v += step) {
      const y = base - (v / top) * ph;
      grid += `<line class="rp-grid" x1="${m.l}" x2="${w - m.r}" y1="${y}" y2="${y}"/>` +
        `<text class="rp-axis-text" x="${m.l - 10}" y="${y + 4}" text-anchor="end">${v}</text>`;
    }
    const cols = bars.map((v, i) => {
      const x = m.l + slot * i + (slot - bw) / 2, bh = (v / top) * ph, y = base - bh, cx = x + bw / 2;
      const d = `M${x} ${base}V${y + r}Q${x} ${y} ${x + r} ${y}H${x + bw - r}Q${x + bw} ${y} ${x + bw} ${y + r}V${base}Z`;
      const words = CATEGORIES[i].split(' ');
      const label = slot < 92 && words.length > 1
        ? words.map((t, k) => `<tspan x="${cx}" dy="${k ? 13 : 0}">${t}</tspan>`).join('')
        : CATEGORIES[i];
      return `<path class="rp-bar" style="--i:${i}" d="${d}"><title>${CATEGORIES[i]}: ${v}</title></path>` +
        `<text class="rp-axis-text" x="${cx}" y="${base + 18}" text-anchor="middle">${label}</text>`;
    }).join('');

    return `<svg viewBox="0 0 ${w} ${h}" role="img" aria-label="${L('إحصائيات المعاملات')}" class="${animate ? 'is-animated' : ''}" style="--rp-delay:${delay}s">` +
      `${grid}<line class="rp-axis" x1="${m.l}" x2="${m.l}" y1="${m.t}" y2="${base}"/>` +
      `<line class="rp-axis" x1="${m.l}" x2="${w - m.r}" y1="${base}" y2="${base}"/>${cols}</svg>`;
  }

  function countUp(el, to, delay) {
    cancelAnimationFrame(el._raf); clearTimeout(el._t);
    const from = +el.dataset.v || 0;
    el.dataset.v = to;
    if (reduce) { el.textContent = fmt(to); return; }
    el.textContent = fmt(from);
    el._t = setTimeout(() => {
      const t0 = performance.now();
      const tick = now => {
        const p = Math.min(1, (now - t0) / 800);
        el.textContent = fmt(Math.round(from + (to - from) * (1 - Math.pow(1 - p, 3))));
        if (p < 1) el._raf = requestAnimationFrame(tick);
      };
      el._raf = requestAnimationFrame(tick);
    }, delay * 1000);
  }

  function init() {
    const root = document.querySelector('.rp-main');
    if (!root || root.dataset.rpBound) return;
    root.dataset.rpBound = '1';

    const $ = id => root.querySelector('#' + id);
    const typeEl = $('rp-type'), fromEl = $('rp-from'), toEl = $('rp-to');
    const errorEl = $('rp-error'), chartEl = $('rp-chart'), metaEl = $('rp-print-meta');
    let lastW = 0, current = Object.assign({}, DATA[typeEl.value], {
      stats: { rejected: 0, pending: 0, approved: 0, returned: 0, draft: 0, total: 0 },
      bars: [0, 0, 0, 0, 0]
    });

    // مفاتيح التصنيف تبقى القيم العربية القادمة من الخادم (بيانات مخزنة)؛
    // الترجمة تتم للعرض فقط عبر CATEGORIES.
    const TYPE_IDX = { 'تحويل مبلغ': 0, 'شراء أجهزة': 1, 'دفع فواتير': 2, 'تعاقد': 3 };
    const filtered = () => {
      const all = (window.ITQAN && ITQAN.transactions) || [];
      const from = (fromEl.value || '').replace(/-/g, '/');
      const to = (toEl.value || '').replace(/-/g, '/');
      return all.filter(t => (!from || t.date >= from) && (!to || t.date <= to));
    };
    // المرحلة 10: الأرقام تأتي من الخادم (نطاق الرؤية نفسه + الفترة المحددة)
    // فلا تعتمد على حِمولة الـ500 صف؛ والحساب المحلي القديم يبقى سقوطاً
    // آمناً عند تعذّر الاتصال.
    let serverData = null;
    const fetchServer = async () => {
      serverData = null;
      try {
        const q = new URLSearchParams();
        if (fromEl.value) q.set('from', fromEl.value);
        if (toEl.value) q.set('to', toEl.value);
        const res = await fetch('/reports/data' + (q.toString() ? '?' + q : ''), {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!res.ok) throw new Error('bad response');
        const payload = await res.json();
        if (payload && payload.success && payload.data) serverData = payload.data;
      } catch (e) { /* سقوط آمن إلى الحساب المحلي */ }
    };
    const refresh = () => {
      let stats, bars;
      if (serverData) {
        stats = serverData.stats; bars = serverData.bars;
      } else {
        const rows = filtered();
        stats = {
          rejected: rows.filter(t => t.status === 'rejected').length,
          pending: rows.filter(t => t.status === 'pending').length,
          approved: rows.filter(t => t.status === 'done').length,
          returned: rows.filter(t => t.status === 'returned').length,
          draft: rows.filter(t => t.status === 'draft').length,
          total: rows.length
        };
        bars = [0, 0, 0, 0, 0];
        rows.forEach(t => { const i = TYPE_IDX[t.type]; bars[i === undefined ? 4 : i]++; });
      }
      current = Object.assign({}, DATA[typeEl.value], { stats, bars });
    };

    const drawChart = (animate, delay) => {
      const w = chartEl.clientWidth; if (!w) return;
      lastW = w;
      if (!current.bars.some(v => v > 0)) {
        const anyData = serverData ? serverData.total > 0 : (((window.ITQAN && ITQAN.transactions) || []).length > 0);
        chartEl.innerHTML = '<div class="empty-state"><div class="es-title">' + L('لا توجد بيانات لعرضها') + '</div><div class="es-desc">' +
          (anyData ? L('لا توجد معاملات ضمن الفترة المحددة.') : L('ستظهر الإحصائيات هنا عند إضافة معاملات.')) + '</div></div>';
        return;
      }
      chartEl.innerHTML = chartSvg(w, current.bars, animate && !reduce, delay || 0);
    };
    const drawStats = delay => root.querySelectorAll('.rp-stat').forEach((card, i) =>
      countUp(card.querySelector('.rp-stat__value'), current.stats[card.dataset.stat], delay + i * .08));
    const showDates = () => [fromEl, toEl].forEach(el => { el.parentNode.querySelector('.rp-control__value').textContent = slashDate(el.value); });
    const updateMeta = () => {
      // بلا تاريخين = كل الفترات (الحالة الافتراضية بعد إزالة تواريخ 2024 المثبّتة).
      const f = slashDate(fromEl.value), t = slashDate(toEl.value);
      const period = (fromEl.value || toEl.value)
        ? L('من :from إلى :to').replace(':from', f).replace(':to', t)
        : L('كل الفترات');
      metaEl.textContent = current.label + ' — ' + period;
    };

    function valid() {
      const bad = !!(fromEl.value && toEl.value && fromEl.value > toEl.value);
      errorEl.hidden = !bad;
      fromEl.parentNode.classList.toggle('is-invalid', bad);
      toEl.parentNode.classList.toggle('is-invalid', bad);
      return !bad;
    }
    async function show() {
      if (!valid()) return;
      await fetchServer();
      refresh();
      drawStats(0); drawChart(true, 0); updateMeta();
    }

    function exportPdf() {
      if (!valid()) return;
      updateMeta();
      document.body.classList.add('rp-printing');
      addEventListener('afterprint', () => document.body.classList.remove('rp-printing'), { once: true });
      window.print();
    }

    typeEl.addEventListener('change', show);
    [fromEl, toEl].forEach(el => el.addEventListener('change', () => { showDates(); valid(); }));
    $('rp-view').addEventListener('click', show);
    $('rp-pdf').addEventListener('click', exportPdf);
    // Excel = تنزيل CSV من الخادم بنفس الفترة ونفس نطاق الرؤية (يفتحه Excel مباشرة).
    $('rp-excel')?.addEventListener('click', () => {
      if (!valid()) return;
      const q = new URLSearchParams();
      if (fromEl.value) q.set('from', fromEl.value);
      if (toEl.value) q.set('to', toEl.value);
      const a = document.createElement('a');
      a.href = '/reports/export' + (q.toString() ? '?' + q : '');
      a.addEventListener('click', e => e.stopPropagation()); // خارج مُوجّه الصفحات
      document.body.appendChild(a); a.click(); a.remove();
    });

    if (window.ResizeObserver) new ResizeObserver(() => { if (chartEl.clientWidth && chartEl.clientWidth !== lastW) drawChart(false); }).observe(chartEl);
    else addEventListener('resize', () => drawChart(false));

    showDates(); updateMeta();
    (async () => {
      await fetchServer();
      refresh();
      drawStats(.5);
      drawChart(true, .7);
    })();
  }

  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();