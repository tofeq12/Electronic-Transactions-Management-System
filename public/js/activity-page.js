// activity-page.js — شاشة سجل النشاطات (الأحدث أولاً) مع بحث وتصفية حسب المستخدم
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const $ = id => document.getElementById(id);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  function render() {
    const table = $('activity-table'); if (!table) return;
    const sel = $('af-user');
    const users = [...new Set(ITQAN.activity.map(a => a.by))];
    if (sel && sel.options.length - 1 !== users.length) {
      const cur = sel.value;
      sel.innerHTML = '<option value="">' + L('كل المستخدمين') + '</option>' + users.map(u => '<option>' + esc(u) + '</option>').join('');
      sel.value = users.includes(cur) ? cur : '';
    }
    const q = ($('af-q').value || '').trim().toLowerCase(), who = sel.value;
    const list = ITQAN.activity.filter(a => (!who || a.by === who) && (!q || (a.action + ' ' + a.target + ' ' + a.by + ' ' + (a.note || '')).toLowerCase().includes(q)))
      .slice().sort((a, b) => b.at.localeCompare(a.at));
    table.tBodies[0].innerHTML = list.length ? list.map(a =>
      '<tr><td class="al-at">' + esc(a.at) + '</td><td>' + esc(a.by) + '</td><td>' + esc(L(a.action)) + '</td><td class="al-target">' + esc(a.target) +
      (a.note ? ' <span class="al-note">' + esc(a.note) + '</span>' : '') + '</td></tr>').join('')
      : '<tr class="table-empty"><td colspan="4">' + L('لا توجد نشاطات مطابقة') + '</td></tr>';
  }
  function init() {
    if (!$('activity-table')) return;
    ['af-q', 'af-user'].forEach(id => { const el = $(id); if (el.dataset.bound) return; el.dataset.bound = 1; el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', render); });
    render();
  }
  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();