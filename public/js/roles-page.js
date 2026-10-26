// roles-page.js — قائمة الأدوار (/roles) + نموذج إضافة/تعديل دور (?id=)
// الصلاحيات تُرسل كأسماء (permissions[]) لتطابق تحقق الخادم (exists:permissions,name).
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const $ = id => document.getElementById(id);
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  function renderList() {
    const table = $('roles-table'); if (!table) return;
    table.tBodies[0].innerHTML = ITQAN.roles.length ? ITQAN.roles.map(r =>
      '<tr data-id="' + r.id + '"><td>' + r.id + '</td><td>' + esc(r.name) + '</td><td>' + esc(r.label) + '</td><td>' + r.usersCount + '</td><td>' + r.permissions.length + '</td>' +
      '<td><div class="row-actions"><button class="icon-btn edit" data-action="role-edit" aria-label="' + L('تعديل') + '">' + UI.icons.edit + '</button>' +
      (r.system ? '' : '<button class="icon-btn delete" data-action="role-delete" aria-label="' + L('حذف') + '">' + UI.icons.trash + '</button>') +
      '</div></td></tr>').join('')
      : '<tr class="table-empty"><td colspan="6">' + L('لا توجد أدوار.') + '</td></tr>';
  }

  async function roleAction(act, id) {
    const r = ITQAN.role(id); if (!r) return;
    if (act === 'edit') return go('/roles/form?id=' + r.id);
    if (act === 'delete') {
      if (r.system) return UI.toast(L('لا يمكن حذف دور أساسي في النظام'), 'err');
      if (r.usersCount > 0) return UI.toast(L('لا يمكن حذف دور مرتبط بمستخدمين'), 'err');
      const yes = await UI.confirm({ title: L('حذف الدور'), text: L('سيتم حذف دور ":name" نهائياً ولا يمكن التراجع.').replace(':name', r.name), ok: L('حذف'), kind: 'danger' });
      if (!yes) return;
      try {
        const res = await API.post('/roles/' + r.id + '/delete');
        ITQAN.roles.splice(ITQAN.roles.indexOf(r), 1);
        ITQAN.logActivity && ITQAN.logActivity(L('حذف دور'), r.name);
        UI.toast(res.message || L('تم حذف الدور'));
        renderList();
      } catch (err) {
        UI.toast(err.message || L('تعذر حذف الدور'), 'err');
      }
    }
  }

  // بناء مربعات الاختيار متدرجة حسب المجموعة داخل #perm-groups.
  function renderPermGroups(selected) {
    const box = $('perm-groups'); if (!box) return;
    const groups = {};
    (ITQAN.permissions || []).forEach(p => { (groups[p.group] = groups[p.group] || []).push(p); });
    const names = Object.keys(groups);
    if (!names.length) { box.innerHTML = '<div class="field field-full"><p>' + L('لا توجد صلاحيات.') + '</p></div>'; return; }
    box.innerHTML = names.map(g =>
      '<div class="field field-full"><strong>' + esc(g) + '</strong></div>' +
      groups[g].map(p =>
        '<label class="perm-check"><input type="checkbox" name="permissions" value="' + esc(p.name) + '"' +
        (selected.has(p.name) ? ' checked' : '') + '> ' + esc(p.name) + '</label>').join('')
    ).join('');
  }

  function bindForm() {
    const f = $('role-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    const id = ITQAN.param('id'), r = id ? ITQAN.role(id) : null;
    if (id && !r) { f.closest('.form-panel-body').innerHTML = '<div class="td-notfound"><h2>' + L('الدور غير موجود') + '</h2><p>' + L('قد يكون حُذف أو أن الرابط غير صحيح.') + '</p><button class="btn btn-primary" type="button" data-goto="/roles">' + L('العودة إلى الأدوار') + '</button></div>'; return; }
    renderPermGroups(new Set(r ? r.permissions : []));
    if (r) {
      $('role-title').textContent = L('تعديل صلاحيات الدور');
      $('role-title-input').value = r.title || '';
      $('role-name').value = r.name;
      $('role-name').readOnly = true; // الاسم غير قابل للتغيير بعد الإنشاء.
    }
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const name = $('role-name').value.trim();
      const title = $('role-title-input').value.trim();
      if (!title) { UI.toast(L('اسم الدور (بالعربية) مطلوب'), 'err'); $('role-title-input').focus(); return; }
      if (!r && !/^[a-z][a-z0-9_]*$/.test(name)) { UI.toast(L('معرّف الدور: حروف إنجليزية صغيرة وأرقام و ( _ ) فقط ويبدأ بحرف'), 'err'); $('role-name').focus(); return; }
      const permissions = Array.from(f.querySelectorAll('input[name="permissions"]:checked')).map(c => c.value);
      const body = r ? { title: title, permissions: permissions } : { name: name, title: title, permissions: permissions };
      try {
        const res = await API.post(r ? '/roles/' + r.id + '/update' : '/roles', body);
        if (r) { r.title = title; r.label = title || r.name; r.permissions = permissions; ITQAN.logActivity && ITQAN.logActivity(L('تعديل صلاحيات دور'), r.name); UI.toast(L('تم حفظ صلاحيات الدور')); }
        else { ITQAN.roles.push({ id: (res.data && res.data.id) || Math.max(0, ...ITQAN.roles.map(x => x.id)) + 1, name: name, title: title, label: title || name, usersCount: 0, permissions: permissions, system: false }); ITQAN.logActivity && ITQAN.logActivity(L('إنشاء دور'), name); UI.toast(L('تم إنشاء الدور')); }
        go((res.data && res.data.redirect) || '/roles');
      } catch (err) {
        UI.toast((err.errors && Object.values(err.errors).flat()[0]) || err.message || L('تعذر حفظ الدور'), 'err');
      }
    });
  }

  if (!window.__roleBound) {
    window.__roleBound = true;
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-action^="role-"]'); if (!b) return;
      const tr = b.closest('tr'); roleAction(b.dataset.action.slice(5), tr && tr.dataset.id);
    });
  }
  const init = () => { renderList(); bindForm(); };
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('itqan:changed', renderList);
  if (document.readyState !== 'loading') init();
})();
