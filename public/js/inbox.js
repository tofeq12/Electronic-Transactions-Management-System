// inbox.js — صندوق الوارد: الصفوف مبنية من بيانات الخادم المفلترة (window.ITQAN_INBOX).
// التصفية كلها تحدث على الخادم (نموذج GET)؛ هذا الملف يعرض النتيجة فقط ويشغّل
// أزرار العرض/القائمة بنفس آليات ui.js (data-action / data-menu).
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const EYE = UI.icons.eye;
  const DOTS = '<svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/></svg>';

  function render() {
    const data = window.ITQAN_INBOX || {};
    const rows = data.rows || [];

    // مؤشر "بانتظار إجرائك": العدد القادم من الخادم (بلا تصفية).
    const badge = document.getElementById('ib-waiting');
    if (badge) {
      const n = data.waiting || 0;
      badge.hidden = !n;
      badge.textContent = L('بانتظار إجرائك') + ': ' + n;
    }

    const table = document.getElementById('inbox-table');
    if (!table) return;

    table.tBodies[0].innerHTML = rows.length ? rows.map(t => {
      // أحدث حالة بعد إجراءات نفس الجلسة (موافقة/رفض/إحالة/إعادة) إن وُجدت.
      const live = (window.ITQAN && ITQAN.trx(t.id)) || t;
      return '<tr data-id="' + esc(t.id) + '" data-status="' + esc(live.status) + '"><td>' + esc(t.id) + '</td><td>' + esc(t.subject) + '</td><td>' + esc(t.type) + '</td><td>' + esc(t.date) + '</td><td>' + UI.badge(live.status) + '</td>' +
        '<td><div class="row-actions"><button class="icon-btn view" data-action="trx-view" aria-label="' + L('عرض') + '">' + EYE + '</button>' +
        '<button class="icon-btn" data-menu="trx" aria-label="' + L('خيارات إضافية') + '">' + DOTS + '</button></div></td></tr>';
    }).join('') : '<tr class="table-empty"><td colspan="6">' + L('لا توجد معاملات في صندوق الوارد') + '</td></tr>';
  }

  document.addEventListener('DOMContentLoaded', render);
  document.addEventListener('itqan:changed', render);
  if (document.readyState !== 'loading') render();
})();
