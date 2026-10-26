// urgent.js — المعاملات العاجلة والمتأخرة (قيد الانتظار فقط). المتأخرة = أقدم من ITQAN.OVERDUE_DAYS أيام.
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  let tab = 'all';
  const $ = id => document.getElementById(id);
  const EYE = () => UI.icons.eye;
  const DOTS = '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>';
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const daysOld = t => { const [y, m, d] = t.date.split('/').map(Number); return Math.floor((Date.now() - new Date(y, m - 1, d).getTime()) / 864e5); };
  const overdue = t => t.status === 'pending' && daysOld(t) > ITQAN.OVERDUE_DAYS;
  const urgent = t => t.status === 'pending' && t.priority === 'urgent';

  function render() {
    const table = $('urgent-table'); if (!table) return;
    if ($('ug-days')) $('ug-days').textContent = ITQAN.OVERDUE_DAYS;
    const all = ITQAN.transactions.filter(t => overdue(t) || urgent(t));
    const counts = { all: all.length, urgent: all.filter(urgent).length, overdue: all.filter(overdue).length };
    document.querySelectorAll('[data-ug]').forEach(b => {
      b.classList.toggle('active', b.dataset.ug === tab);
      b.querySelector('.ug-n').textContent = counts[b.dataset.ug];
    });
    const list = (tab === 'urgent' ? all.filter(urgent) : tab === 'overdue' ? all.filter(overdue) : all)
      .sort((a, b) => (urgent(b) - urgent(a)) || (daysOld(b) - daysOld(a)));
    table.tBodies[0].innerHTML = list.length ? list.map(t => {
      const pr = ITQAN.PRIORITY[t.priority] || ITQAN.PRIORITY.normal;
      return '<tr data-id="' + esc(t.id) + '"><td>' + esc(t.id) + '</td><td>' + esc(t.subject) + '</td>' +
        '<td><span class="badge ' + pr.cls + '">' + pr.label + '</span></td><td>' + esc(t.date) + '</td>' +
        '<td>' + (overdue(t) ? '<span class="badge badge-orange">' + L('متأخرة :d يوماً').replace(':d', daysOld(t)) + '</span>' : '—') + '</td>' +
        '<td><div class="row-actions"><button class="icon-btn view" data-action="trx-view" aria-label="' + L('عرض') + '">' + EYE() + '</button>' +
        '<button class="icon-btn" data-menu="trx" aria-label="' + L('خيارات إضافية') + '">' + DOTS + '</button></div></td></tr>';
    }).join('') : '<tr class="table-empty"><td colspan="6">' + L('لا توجد معاملات عاجلة أو متأخرة') + '</td></tr>';
  }

  if (!window.__urgentBound) {
    window.__urgentBound = true;
    document.addEventListener('click', e => { const b = e.target.closest('[data-ug]'); if (b) { tab = b.dataset.ug; render(); } });
  }
  document.addEventListener('DOMContentLoaded', () => { tab = 'all'; render(); });
  document.addEventListener('itqan:changed', render);
  if (document.readyState !== 'loading') render();
})();