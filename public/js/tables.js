// tables.js — مزامنة جدولي المعاملات والمستخدمين مع ITQAN (حذف/حالة/عناصر جديدة) + فلاتر المعاملات
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const norm = s => String(s || '').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).replace(/[-.]/g, '/').trim();
  const EYE = UI.icons.eye;
  const DOTS = '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>';
  const EDIT = UI.icons.edit, TRASH = UI.icons.trash;

  const trxRow = t => '<tr data-id="' + t.id + '" data-status="' + t.status + '"><td>' + t.id + '</td><td>' + t.subject + '</td><td>' + t.type + '</td><td>' + t.date + '</td><td>' + UI.badge(t.status) + '</td>' +
    '<td><div class="row-actions"><button class="icon-btn view" data-action="trx-view" aria-label="' + L('عرض') + '">' + EYE + '</button><button class="icon-btn" data-menu="trx" aria-label="' + L('خيارات إضافية') + '">' + DOTS + '</button></div></td></tr>';
  const userBadge = s => s === 'active' ? '<span class="badge badge-green">' + L('نشط') + '</span>' : s === 'inactive' ? '<span class="badge badge-red">' + L('غير نشط') + '</span>' : '<span class="td-none">—</span>';
  const userRow = u => '<tr data-id="' + u.id + '" data-status="' + (u.status || '') + '"><td>' + u.id + '</td><td>' + (u.name || '—') + '</td><td>' + (u.username || '—') + '</td><td>' + (u.email || '—') + '</td><td>' + userBadge(u.status) + '</td><td>' + (u.role ? L(u.role) : '—') + '</td>' +
    '<td><div class="row-actions"><button class="icon-btn edit" data-action="user-edit" aria-label="' + L('تعديل') + '">' + EDIT + '</button><button class="icon-btn delete" data-action="user-delete" aria-label="' + L('حذف') + '">' + TRASH + '</button><button class="icon-btn" data-menu="user" aria-label="' + L('خيارات إضافية') + '">' + DOTS + '</button></div></td></tr>';

  function sync(table, list, build, fillRow, emptyText) {
    const body = table.tBodies[0];
    const have = new Map([...body.rows].filter(r => r.dataset.id).map(r => [r.dataset.id, r]));
    have.forEach((row, id) => { if (!list.some(x => String(x.id) === id)) row.remove(); });
    list.forEach(x => {
      const row = have.get(String(x.id));
      if (row) { fillRow(row, x); }
      else { body.insertAdjacentHTML('beforeend', build(x)); }
    });
    const empty = body.querySelector('.table-empty');
    if (!list.length) {
      const cols = table.querySelectorAll('thead th').length || 1;
      if (!empty) body.insertAdjacentHTML('beforeend', '<tr class="table-empty"><td colspan="' + cols + '">' + emptyText + '</td></tr>');
    } else if (empty) empty.remove();
  }

  function applyFilters() {
    const table = document.getElementById('transactions-table'); if (!table) return;
    const val = k => (document.querySelector('[data-filter="' + k + '"]') || {}).value || '';
    const q = val('q').trim().toLowerCase(), type = val('type'), status = val('status'), from = norm(val('from')), to = norm(val('to'));
    let shown = 0;
    [...table.tBodies[0].rows].forEach(r => {
      if (r.classList.contains('table-empty')) return;
      const t = ITQAN.trx(r.dataset.id); if (!t) return;
      const ok = (!q || r.textContent.toLowerCase().includes(q)) && (!type || t.type === type) && (!status || t.status === status) && (!from || t.date >= from) && (!to || t.date <= to);
      r.style.display = ok ? '' : 'none'; if (ok) shown++;
    });
    let empty = table.tBodies[0].querySelector('.table-empty');
    if (!shown) { if (!empty) { table.tBodies[0].insertAdjacentHTML('beforeend', '<tr class="table-empty"><td colspan="6">' + L('لا توجد معاملات مطابقة') + '</td></tr>'); } }
    else if (empty) empty.remove();
  }

  function init() {
    const tt = document.getElementById('transactions-table');
    if (tt) {
      sync(tt, ITQAN.transactions, trxRow, (row, t) => { row.dataset.status = t.status; row.cells[4].innerHTML = UI.badge(t.status); }, L('لا توجد معاملات لعرضها'));
      document.querySelectorAll('[data-filter]').forEach(el => {
        if (el.dataset.bound) return; el.dataset.bound = 1;
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', applyFilters);
      });
      applyFilters();

      // Phase 7: "load more" — the page payload ships the newest 500 rows;
      // older ones are pulled page by page from GET /transactions/list and
      // appended (current filters are re-applied over the bigger set).
      const moreWrap = document.getElementById('trx-load-more-wrap');
      const moreBtn = document.getElementById('trx-load-more');
      if (moreWrap && moreBtn && !moreBtn.dataset.bound) {
        moreBtn.dataset.bound = 1;
        moreWrap.hidden = ITQAN.transactions.length < 500;
        let nextPage = 2, busy = false;
        moreBtn.addEventListener('click', async () => {
          if (busy) return; busy = true; moreBtn.disabled = true;
          try {
            const res = await fetch('/transactions/list?page=' + nextPage, {
              headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!res.ok) throw new Error('bad response');
            const payload = await res.json();
            const rows = (payload && payload.data && payload.data.rows) || [];
            const meta = (payload && payload.data && payload.data.meta) || {};
            rows.forEach(x => ITQAN.transactions.push(x));
            sync(tt, ITQAN.transactions, trxRow, (row, x) => { row.dataset.status = x.status; row.cells[4].innerHTML = UI.badge(x.status); }, L('لا توجد معاملات لعرضها'));
            applyFilters();
            nextPage = (meta.page || nextPage) + 1;
            if (!rows.length || nextPage > (meta.last_page || 1)) moreWrap.hidden = true;
          } catch (e) { /* transient — keep the button for a retry */ }
          finally { busy = false; moreBtn.disabled = false; }
        });
      }
    }
    const ut = document.getElementById('users-table');
    if (ut) sync(ut, ITQAN.users, userRow, (row, u) => { row.dataset.status = u.status || ''; row.cells[4].innerHTML = userBadge(u.status); }, L('لا يوجد مستخدمون لعرضهم'));
  }
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('itqan:changed', init);
  if (document.readyState !== 'loading') init();
})();