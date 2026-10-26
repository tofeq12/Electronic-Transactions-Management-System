// permissions-page.js — شاشة الصلاحيات (/permissions): عرض متدرّج حسب
// المجموعة + إضافة صلاحية مخصصة + حذف صلاحية غير مستخدمة.
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const $ = id => document.getElementById(id);
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  function renderList() {
    const table = $('perm-table'); if (!table) return;
    // تجميع الصفوف حسب المجموعة ثم تسطيرها في الجدول بترتيب ثابت.
    const groups = {};
    ITQAN.permissions.forEach(p => { (groups[p.group] = groups[p.group] || []).push(p); });
    const ordered = Object.keys(groups).sort().reduce((acc, g) => acc.concat(groups[g]), []);
    table.tBodies[0].innerHTML = ordered.length ? ordered.map(p =>
      '<tr data-id="' + p.id + '"><td>' + p.id + '</td><td>' + esc(p.name) + '</td><td>' + esc(p.group) + '</td><td>' + p.rolesCount + '</td>' +
      '<td><div class="row-actions">' +
      (p.system || p.rolesCount > 0 ? '' : '<button class="icon-btn delete" data-action="perm-delete" aria-label="' + L('حذف') + '">' + UI.icons.trash + '</button>') +
      '</div></td></tr>').join('')
      : '<tr class="table-empty"><td colspan="5">' + L('لا توجد صلاحيات.') + '</td></tr>';
  }

  async function permAction(act, id) {
    const p = ITQAN.perm(id); if (!p) return;
    if (act !== 'delete') return;
    if (p.system) return UI.toast(L('لا يمكن حذف صلاحية أساسية في النظام'), 'err');
    if (p.rolesCount > 0) return UI.toast(L('لا يمكن حذف صلاحية مرتبطة بأدوار'), 'err');
    const yes = await UI.confirm({ title: L('حذف الصلاحية'), text: L('سيتم حذف صلاحية ":name" نهائياً ولا يمكن التراجع.').replace(':name', p.name), ok: L('حذف'), kind: 'danger' });
    if (!yes) return;
    try {
      const res = await API.post('/permissions/' + p.id + '/delete');
      ITQAN.permissions.splice(ITQAN.permissions.indexOf(p), 1);
      UI.toast(res.message || L('تم حذف الصلاحية'));
      renderList();
    } catch (err) {
      UI.toast(err.message || L('تعذر حذف الصلاحية'), 'err');
    }
  }

  function bindForm() {
    const f = $('perm-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const name = $('perm-name').value.trim();
      if (!/^[a-z][a-z0-9_.]*$/.test(name)) { UI.toast(L('اسم الصلاحية: حروف إنجليزية صغيرة وأرقام و ( _ . ) فقط ويبدأ بحرف'), 'err'); $('perm-name').focus(); return; }
      if (ITQAN.permissions.some(x => x.name === name)) { UI.toast(L('الصلاحية موجودة مسبقاً'), 'err'); $('perm-name').focus(); return; }
      try {
        const res = await API.post('/permissions', { name: name });
        ITQAN.permissions.push({ id: (res.data && res.data.id) || Math.max(0, ...ITQAN.permissions.map(x => x.id)) + 1, name: name, group: 'أخرى', rolesCount: 0, system: false });
        $('perm-name').value = '';
        UI.toast(res.message || L('تمت إضافة الصلاحية'));
        renderList();
      } catch (err) {
        UI.toast((err.errors && Object.values(err.errors).flat()[0]) || err.message || L('تعذر إضافة الصلاحية'), 'err');
      }
    });
  }

  if (!window.__permBound) {
    window.__permBound = true;
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-action^="perm-"]'); if (!b) return;
      const tr = b.closest('tr'); permAction(b.dataset.action.slice(5), tr && tr.dataset.id);
    });
  }
  const init = () => { renderList(); bindForm(); };
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('itqan:changed', renderList);
  if (document.readyState !== 'loading') init();
})();
