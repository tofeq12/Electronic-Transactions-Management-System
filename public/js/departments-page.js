// departments-page.js — قائمة الأقسام + نموذج إضافة/تعديل قسم (?id=)
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const $ = id => document.getElementById(id);
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const same = (a, b) => a.trim().replace(/\s+/g, ' ').toLowerCase() === b.trim().replace(/\s+/g, ' ').toLowerCase();
  // Phase 11 — organization structure: show which college a department
  // belongs to (payload carries collegeId + the colleges list).
  const collegeName = id => {
    const c = (ITQAN.colleges || []).find(x => String(x.id) === String(id));
    return c ? c.name : '—';
  };

  function renderList() {
    const table = $('dept-table'); if (!table) return;
    table.tBodies[0].innerHTML = ITQAN.departments.length ? ITQAN.departments.map(d =>
      '<tr data-id="' + d.id + '"><td>' + d.id + '</td><td>' + esc(d.name) + '</td><td>' + (d.desc ? esc(d.desc) : '—') + '</td><td>' + esc(collegeName(d.collegeId)) + '</td><td>' + ITQAN.deptTrxCount(d.id) + '</td>' +
      '<td><div class="row-actions"><button class="icon-btn edit" data-action="dept-edit" aria-label="' + L('تعديل') + '">' + UI.icons.edit + '</button>' +
      '<button class="icon-btn delete" data-action="dept-delete" aria-label="' + L('حذف') + '">' + UI.icons.trash + '</button></div></td></tr>').join('')
      : '<tr class="table-empty"><td colspan="6">' + L('لا توجد أقسام. أضف القسم الأول.') + '</td></tr>';
  }

  async function deptAction(act, id) {
    const d = ITQAN.dept(id); if (!d) return;
    if (act === 'edit') return go('/departments/form?id=' + d.id);
    if (act === 'delete') {
      if (ITQAN.deptTrxCount(d.id)) return UI.toast(L('لا يمكن حذف قسم أُحيلت إليه معاملات'), 'err');
      const yes = await UI.confirm({ title: L('حذف القسم'), text: L('سيتم حذف قسم ":name" نهائياً ولا يمكن التراجع.').replace(':name', d.name), ok: L('حذف'), kind: 'danger' });
      if (!yes) return;
      try {
        const res = await API.post('/departments/' + d.id + '/delete');
        ITQAN.departments.splice(ITQAN.departments.indexOf(d), 1);
        ITQAN.logActivity && ITQAN.logActivity(L('حذف قسم'), d.name);
        UI.toast(res.message || L('تم حذف القسم'));
        renderList();
      } catch (err) {
        UI.toast(err.message || L('تعذر حذف القسم'), 'err');
      }
    }
  }

  function bindForm() {
    const f = $('dept-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    const id = ITQAN.param('id'), d = id ? ITQAN.dept(id) : null;
    if (id && !d) { f.closest('.form-panel-body').innerHTML = '<div class="td-notfound"><h2>' + L('القسم غير موجود') + '</h2><p>' + L('قد يكون حُذف أو أن الرابط غير صحيح.') + '</p><button class="btn btn-primary" type="button" data-goto="/departments">' + L('العودة إلى الأقسام') + '</button></div>'; return; }
    if (d) {
      $('dept-title').textContent = L('تعديل القسم'); $('dept-name').value = d.name; $('dept-desc').value = d.desc || '';
      if ($('dept-college')) $('dept-college').value = d.collegeId || '';
    }
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const name = $('dept-name').value.trim(), desc = $('dept-desc').value.trim();
      const collegeId = $('dept-college') && $('dept-college').value ? Number($('dept-college').value) : null;
      if (name.length < 2) { UI.toast(L('اسم القسم حرفان على الأقل'), 'err'); $('dept-name').focus(); return; }
      if (ITQAN.departments.some(x => x !== d && same(x.name, name))) { UI.toast(L('اسم القسم مستخدم مسبقاً'), 'err'); $('dept-name').focus(); return; }
      try {
        const res = await API.post(d ? '/departments/' + d.id + '/update' : '/departments', { name: name, desc: desc, college_id: collegeId });
        if (d) { d.name = name; d.desc = desc; d.collegeId = collegeId; ITQAN.logActivity && ITQAN.logActivity(L('تعديل قسم'), name); UI.toast(L('تم حفظ القسم')); }
        else { ITQAN.departments.push({ id: (res.data && res.data.id) || Math.max(0, ...ITQAN.departments.map(x => x.id)) + 1, name, desc, collegeId }); ITQAN.logActivity && ITQAN.logActivity(L('إضافة قسم'), name); UI.toast(L('تمت إضافة القسم')); }
        go((res.data && res.data.redirect) || '/departments');
      } catch (err) {
        UI.toast((err.errors && Object.values(err.errors).flat()[0]) || err.message || L('تعذر حفظ القسم'), 'err');
      }
    });
  }

  if (!window.__deptBound) {
    window.__deptBound = true;
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-action^="dept-"]'); if (!b) return;
      const tr = b.closest('tr'); deptAction(b.dataset.action.slice(5), tr && tr.dataset.id);
    });
  }
  const init = () => { renderList(); bindForm(); };
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('itqan:changed', renderList);
  if (document.readyState !== 'loading') init();
})();