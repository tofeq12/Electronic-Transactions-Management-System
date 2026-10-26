// trx-types.js — إدارة أنواع المعاملات وحقولها الديناميكية (المرحلة 4).
// البيانات من window.ITQAN_DATA.allTrxTypes (تُحقن للأدمن فقط — types.manage).
// كل عملية تعديل تمر على الخادم (POST JSON) والتحقق النهائي يبقى هناك.
(function () {
  const T = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const TYPES = (window.ITQAN_DATA && ITQAN_DATA.allTrxTypes) || [];
  const FIELD_TYPES = [
    ['text', 'نص'], ['textarea', 'نص طويل'], ['number', 'رقم'], ['date', 'تاريخ'],
    ['select', 'قائمة اختيار'], ['checkbox', 'مربع اختيار'], ['file', 'ملف']
  ];
  const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const rec = id => TYPES.find(t => String(t.id) === String(id));

  /* ---------------- نافذة نموذج عامة ---------------- */
  function formModal({ title, fields, values = {}, ok = 'حفظ' }) {
    return new Promise(res => {
      const ov = document.createElement('div');
      ov.className = 'ui-overlay';
      const body = fields.map(f => {
        const v = values[f.name] ?? '';
        if (f.kind === 'select') {
          return '<div class="field"><label>' + T(f.label) + (f.required ? ' *' : '') + '</label><select name="' + f.name + '">' +
            f.options.map(o => '<option value="' + esc(o[0]) + '"' + (String(v) === String(o[0]) ? ' selected' : '') + '>' + T(o[1]) + '</option>').join('') +
            '</select></div>';
        }
        if (f.kind === 'checkbox') {
          return '<div class="field"><label class="chk-wrap"><input type="checkbox" name="' + f.name + '"' + (v ? ' checked' : '') + '> <span>' + T(f.label) + '</span></label></div>';
        }
        if (f.kind === 'textarea') {
          return '<div class="field field-full"><label>' + T(f.label) + (f.required ? ' *' : '') + '</label><textarea name="' + f.name + '" rows="2">' + esc(v) + '</textarea></div>';
        }
        return '<div class="field"><label>' + T(f.label) + (f.required ? ' *' : '') + '</label><input type="text" name="' + f.name + '" value="' + esc(v) + '"></div>';
      }).join('');
      ov.innerHTML = '<div class="ui-modal um-info" role="dialog" aria-modal="true"><h3>' + T(title) + '</h3>' +
        '<div class="um-body form-grid">' + body + '</div>' +
        '<div class="um-actions"><button type="button" class="btn btn-outline um-cancel">' + T('إلغاء') + '</button>' +
        '<button type="button" class="btn btn-primary um-ok">' + T(ok) + '</button></div></div>';
      document.body.appendChild(ov);
      const out = v => { ov.remove(); res(v); };
      ov.querySelector('.um-cancel').onclick = () => out(null);
      ov.addEventListener('click', e => { if (e.target === ov) out(null); });
      ov.querySelector('.um-ok').onclick = () => {
        const data = {};
        fields.forEach(f => {
          const el = ov.querySelector('[name="' + f.name + '"]');
          data[f.name] = f.kind === 'checkbox' ? el.checked : el.value.trim();
        });
        out(data);
      };
    });
  }

  const showErr = err => {
    const msgs = (err && err.errors) || {};
    const first = Object.values(msgs).flat()[0];
    UI.toast(first || (err && err.message) || T('تعذر تنفيذ العملية'), 'err');
  };

  const TYPE_FIELDS = [
    { name: 'slug', label: 'المعرّف (إنجليزي صغير)', required: true },
    { name: 'name_ar', label: 'الاسم بالعربية', required: true },
    { name: 'name_en', label: 'الاسم بالإنجليزية' },
    { name: 'sort_order', label: 'الترتيب' },
    { name: 'description', label: 'الوصف', kind: 'textarea' },
    { name: 'required_documents', label: 'المستندات المطلوبة (افصل بفاصلة)', kind: 'textarea' },
  ];

  /* ---------------- عمليات الأنواع ---------------- */
  async function addType() {
    const d = await formModal({ title: 'إضافة نوع معاملة', fields: TYPE_FIELDS, ok: 'إضافة' });
    if (!d) return;
    const body = {
      slug: d.slug, name_ar: d.name_ar, name_en: d.name_en || null,
      description: d.description || null, sort_order: parseInt(d.sort_order, 10) || 0,
      required_documents: d.required_documents ? d.required_documents.split(/[,،]/).map(s => s.trim()).filter(Boolean) : null
    };
    try { await API.post('/types', body); UI.toast(T('تمت إضافة النوع')); location.reload(); }
    catch (err) { showErr(err); }
  }

  async function editType(id) {
    const t = rec(id); if (!t) return;
    const fields = t.system ? TYPE_FIELDS.filter(f => ['description', 'required_documents', 'sort_order'].includes(f.name)) : TYPE_FIELDS;
    const d = await formModal({
      title: 'تعديل نوع المعاملة', fields, ok: 'حفظ',
      values: { slug: t.slug, name_ar: t.nameAr, name_en: t.nameEn || '', sort_order: t.sort, description: t.desc || '', required_documents: (t.requiredDocs || []).join('، ') }
    });
    if (!d) return;
    const body = { description: d.description || null, sort_order: parseInt(d.sort_order, 10) || 0, required_documents: d.required_documents ? d.required_documents.split(/[,،]/).map(s => s.trim()).filter(Boolean) : null };
    if (!t.system) Object.assign(body, { slug: d.slug, name_ar: d.name_ar, name_en: d.name_en || null });
    try { await API.post('/types/' + t.id + '/update', body); UI.toast(T('تم حفظ النوع')); location.reload(); }
    catch (err) { showErr(err); }
  }

  async function toggleType(id) {
    try { const r = await API.post('/types/' + id + '/toggle'); UI.toast(r.message || T('تم')); location.reload(); }
    catch (err) { showErr(err); }
  }

  async function deleteType(id) {
    const okc = await UI.confirm({ title: 'حذف نوع المعاملة', text: 'سيُحذف النوع وحقوله. المعاملات المرتبطة تبقى كما هي.', kind: 'danger' });
    if (!okc) return;
    try { await API.post('/types/' + id + '/delete'); UI.toast(T('تم حذف النوع')); location.reload(); }
    catch (err) { showErr(err); }
  }

  /* ---------------- عمليات الحقول ---------------- */
  const FIELD_FORM = [
    { name: 'label_ar', label: 'اسم الحقل بالعربية', required: true },
    { name: 'label_en', label: 'اسم الحقل بالإنجليزية' },
    { name: 'field_type', label: 'نوع الحقل', kind: 'select', options: FIELD_TYPES, required: true },
    { name: 'options', label: 'الخيارات (افصل بفاصلة — لقائمة الاختيار فقط)' },
    { name: 'sort_order', label: 'الترتيب' },
    { name: 'is_required', label: 'حقل مطلوب', kind: 'checkbox' },
    { name: 'is_active', label: 'مفعّل', kind: 'checkbox' },
  ];

  function fieldsModal(typeId) {
    const t = rec(typeId); if (!t) return;
    let rows = t.fields.map(f =>
      '<tr><td>' + esc(f.label) + '</td><td>' + T(FIELD_TYPES.find(x => x[0] === f.type)?.[1] || f.type) + '</td>' +
      '<td>' + (f.required ? '✓' : '—') + '</td><td>' + (f.active ? T('مفعّل') : T('موقوف')) + '</td>' +
      '<td><button type="button" class="icon-btn" data-fa="edit" data-fid="' + f.id + '" title="' + T('تعديل') + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>' +
      '<button type="button" class="icon-btn" data-fa="toggle" data-fid="' + f.id + '" title="' + T('تفعيل/إيقاف') + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M5 8l7-5 7 5v8l-7 5-7-5z"/></svg></button>' +
      '<button type="button" class="icon-btn delete" data-fa="delete" data-fid="' + f.id + '" title="' + T('حذف') + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button></td></tr>').join('') ||
      '<tr><td colspan="5">' + T('لا توجد حقول بعد') + '</td></tr>';

    const ov = document.createElement('div');
    ov.className = 'ui-overlay';
    ov.innerHTML = '<div class="ui-modal um-info" role="dialog" aria-modal="true"><h3>' + T('حقول') + ' — ' + esc(t.nameAr) + '</h3>' +
      '<div class="um-body"><table class="mini-table"><thead><tr><th>' + T('الاسم') + '</th><th>' + T('النوع') + '</th><th>' + T('مطلوب') + '</th><th>' + T('الحالة') + '</th><th></th></tr></thead><tbody>' + rows + '</tbody></table></div>' +
      '<div class="um-actions"><button type="button" class="btn btn-outline" data-fa="add">' + T('إضافة حقل') + '</button>' +
      '<button type="button" class="btn btn-primary um-cancel">' + T('إغلاق') + '</button></div></div>';
    document.body.appendChild(ov);
    ov.querySelector('.um-cancel').onclick = () => ov.remove();

    ov.addEventListener('click', async e => {
      const b = e.target.closest('[data-fa]'); if (!b) return;
      const act = b.dataset.fa, fid = b.dataset.fid;
      const field = fid ? t.fields.find(x => String(x.id) === String(fid)) : null;

      if (act === 'add' || act === 'edit') {
        const d = await formModal({
          title: (act === 'add' ? 'إضافة حقل' : 'تعديل حقل'), fields: FIELD_FORM, ok: 'حفظ',
          values: field ? { label_ar: field.label, label_en: field.labelEn || '', field_type: field.type, options: (field.options || []).join('، '), sort_order: field.sort, is_required: field.required, is_active: field.active } : { field_type: 'text', is_active: true }
        });
        if (!d) return;
        const body = {
          label_ar: d.label_ar, label_en: d.label_en || null, field_type: d.field_type,
          is_required: d.is_required ? 1 : 0, is_active: d.is_active ? 1 : 0,
          sort_order: parseInt(d.sort_order, 10) || 0,
          options: d.options ? d.options.split(/[,،]/).map(s => s.trim()).filter(Boolean) : null
        };
        try {
          await API.post(act === 'add' ? '/types/' + t.id + '/fields' : '/fields/' + fid + '/update', body);
          UI.toast(T('تم الحفظ')); location.reload();
        } catch (err) { showErr(err); }
      } else if (act === 'toggle') {
        try { await API.post('/fields/' + fid + '/toggle'); UI.toast(T('تم')); location.reload(); } catch (err) { showErr(err); }
      } else if (act === 'delete') {
        const okc = await UI.confirm({ title: 'حذف الحقل', text: 'القيم المحفوظة في المعاملات القديمة تبقى كما هي.', kind: 'danger' });
        if (!okc) return;
        try { await API.post('/fields/' + fid + '/delete'); UI.toast(T('تم حذف الحقل')); location.reload(); } catch (err) { showErr(err); }
      }
    });
  }

  /* ---------------- سير الموافقات (المرحلة 5) ---------------- */
  const WF_TYPES = ITQAN_DATA.workflows || [];
  const DEPTS = ITQAN_DATA.departments || [];
  const WF_ROLES = [['dept_manager', 'مدير'], ['employee', 'موظف'], ['reviewer', 'مراجع']];
  const wfFor = typeId => WF_TYPES.find(w => String(w.typeId) === String(typeId)) || null;

  function targetLabel(s) {
    if (s.targetType === 'department') return s.targetDept || '—';
    if (s.targetType === 'role') return s.targetRoleLabel || s.targetRole || '—';
    if (s.targetType === 'user') return s.targetUser || '—';
    return '—';
  }

  function stepsTable(wf) {
    if (!wf || !wf.steps || !wf.steps.length) return '<div class="fw-actions-note">' + T('لا توجد خطوات بعد') + '</div>';
    return '<table class="mini-table"><thead><tr><th>#</th><th>' + T('اسم الخطوة') + '</th><th>' + T('الجهة المستهدفة') + '</th><th></th></tr></thead><tbody>' +
      wf.steps.map(s =>
        '<tr><td>' + s.order + '</td><td>' + esc(s.nameAr) + '</td><td>' + esc(targetLabel(s)) + '</td>' +
        '<td><button type="button" class="icon-btn" data-wf="edit-step" data-sid="' + s.id + '" title="' + T('تعديل') + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></svg></button>' +
        '<button type="button" class="icon-btn delete" data-wf="del-step" data-sid="' + s.id + '" title="' + T('حذف') + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg></button></td></tr>').join('') +
      '</tbody></table>';
  }

  async function stepForm(t, step) {
    const isEdit = !!step;
    const ov = document.createElement('div');
    ov.className = 'ui-overlay';
    const tt = step ? step.targetType : 'department';
    ov.innerHTML = '<div class="ui-modal um-info" role="dialog" aria-modal="true"><h3>' + T(isEdit ? 'تعديل خطوة' : 'إضافة خطوة') + '</h3>' +
      '<div class="um-body form-grid">' +
      '<div class="field field-full"><label>' + T('اسم الخطوة (عربي)') + ' *</label><input type="text" name="name_ar" value="' + esc(step ? step.nameAr : '') + '"></div>' +
      '<div class="field field-full"><label>' + T('اسم الخطوة (إنجليزي)') + '</label><input type="text" name="name_en" value="' + esc((step && step.nameEn) || '') + '"></div>' +
      '<div class="field"><label>' + T('نوع الجهة') + '</label><select name="target_type">' +
      ['department', 'role', 'user'].map(v => '<option value="' + v + '"' + (tt === v ? ' selected' : '') + '>' + T({ department: 'قسم', role: 'دور', user: 'موظف' }[v]) + '</option>').join('') +
      '</select></div>' +
      '<div class="field" data-tgt="department"><label>' + T('القسم') + '</label><select name="target_dept_id">' +
      DEPTS.map(d => '<option value="' + d.id + '"' + (step && step.targetDeptId === d.id ? ' selected' : '') + '>' + esc(d.name) + '</option>').join('') + '</select></div>' +
      '<div class="field" data-tgt="role"><label>' + T('الدور') + '</label><select name="target_role">' +
      WF_ROLES.map(r => '<option value="' + r[0] + '"' + (step && step.targetRole === r[0] ? ' selected' : '') + '>' + T(r[1]) + '</option>').join('') + '</select></div>' +
      '<div class="field" data-tgt="user"><label>' + T('الموظف') + '</label><select name="target_user_id">' +
      ITQAN_DATA.users.map(u => '<option value="' + u.id + '"' + (step && step.targetUserId === u.id ? ' selected' : '') + '>' + esc(u.name) + '</option>').join('') + '</select></div>' +
      '</div><div class="um-actions"><button type="button" class="btn btn-outline um-cancel">' + T('إلغاء') + '</button>' +
      '<button type="button" class="btn btn-primary um-ok">' + T('حفظ') + '</button></div></div>';
    document.body.appendChild(ov);

    const sync = () => {
      const v = ov.querySelector('[name=target_type]').value;
      ov.querySelectorAll('[data-tgt]').forEach(f => { f.hidden = f.dataset.tgt !== v; });
    };
    ov.querySelector('[name=target_type]').onchange = sync; sync();

    return new Promise(res => {
      const out = v => { ov.remove(); res(v); };
      ov.querySelector('.um-cancel').onclick = () => out(null);
      ov.addEventListener('click', e => { if (e.target === ov) out(null); });
      ov.querySelector('.um-ok').onclick = () => {
        const g = n => { const el = ov.querySelector('[name=' + n + ']'); return el ? el.value.trim() : ''; };
        const tt2 = g('target_type');
        out({
          name_ar: g('name_ar'), name_en: g('name_en') || null, target_type: tt2,
          target_dept_id: tt2 === 'department' ? parseInt(g('target_dept_id'), 10) : null,
          target_role: tt2 === 'role' ? g('target_role') : null,
          target_user_id: tt2 === 'user' ? parseInt(g('target_user_id'), 10) : null
        });
      };
    });
  }

  async function workflowModal(typeId) {
    const t = rec(typeId); if (!t) return;
    let wf = wfFor(typeId);

    if (!wf) {
      const okc = await UI.confirm({ title: T('إنشاء سير موافقات'), text: T('سيُنشأ سير مبدئي لنوع المعاملة này. يمكنك إضافة الخطوات بعد ذلك.') });
      if (!okc) return;
      try { const r = await API.post('/workflows', { trx_type_id: t.id }); wf = { id: r.data.id, typeId: t.id, steps: [] }; }
      catch (err) { return showErr(err); }
    }

    while (true) {
      const html =
        '<div class="fw-actions-note">' + T('الإصدار') + ': ' + (wf.version || 1) + ' — ' +
        (wf.active ? T('مفعّل') : T('موقوف')) + ' — ' +
        T('الخطوة الحالية للمعاملات الجديدة تبدأ من رقم 1. أي تعديل يرفع الإصدار ولا يمسّ المعاملات قيد المسار.') + '</div>' +
        stepsTable(wf);
      const footer = '<button type="button" class="btn btn-outline" data-wf="add-step">' + T('إضافة خطوة') + '</button>' +
        '<button type="button" class="btn btn-outline" data-wf="toggle">' + (wf.active ? T('إيقاف') : T('تفعيل')) + '</button>' +
        '<button type="button" class="btn btn-outline" data-wf="del" style="color:var(--red-text)">' + T('حذف السير') + '</button>';

      const ov = document.createElement('div');
      ov.className = 'ui-overlay';
      ov.innerHTML = '<div class="ui-modal um-info" role="dialog" aria-modal="true"><h3>' + T('سير الموافقات') + ' — ' + esc(t.nameAr) + '</h3>' +
        '<div class="um-body">' + html + '</div><div class="um-actions">' + footer +
        '<button type="button" class="btn btn-primary um-cancel">' + T('إغلاق') + '</button></div></div>';
      document.body.appendChild(ov);
      const close = () => { ov.remove(); return; };
      ov.querySelector('.um-cancel').onclick = close;
      ov.addEventListener('click', e => { if (e.target === ov) close(); });

      const act = await new Promise(res2 => {
        ov.addEventListener('click', e => {
          const b = e.target.closest('[data-wf]'); if (!b) return;
          res2({ a: b.dataset.wf, sid: b.dataset.sid });
        });
      });
      close();
      if (!act) return;

      if (act.a === 'toggle') {
        try { await API.post('/workflows/' + wf.id + '/toggle'); wf.active = !wf.active; UI.toast(T('تم')); }
        catch (err) { showErr(err); }
        continue; // إعادة رسم النافذة بنفس الجلسة
      }

      try {
        if (act.a === 'add-step' || act.a === 'edit-step') {
          const step = act.sid ? wf.steps.find(s => String(s.id) === String(act.sid)) : null;
          const d = await stepForm(t, step);
          if (d && d.name_ar) {
            await API.post(act.a === 'add-step' ? '/workflows/' + wf.id + '/steps' : '/workflow-steps/' + act.sid + '/update', d);
            UI.toast(T('تم الحفظ')); location.reload();
          }
        } else if (act.a === 'del-step') {
          const okc = await UI.confirm({ title: T('حذف الخطوة'), text: T('ستُعاد ترقيم الخطوات. المعاملات قيد المسار لا تتأثر.'), kind: 'danger' });
          if (okc) { await API.post('/workflow-steps/' + act.sid + '/delete'); UI.toast(T('تم حذف الخطوة')); location.reload(); }
        } else if (act.a === 'del') {
          const okc = await UI.confirm({ title: T('حذف سير الموافقات'), text: T('يُحذف السير وخطواته. المعاملات المسجّلة (النسخة المحفوظة) تبقى كما هي.'), kind: 'danger' });
          if (okc) { await API.post('/workflows/' + wf.id + '/delete'); UI.toast(T('تم الحذف')); location.reload(); }
        }
      } catch (err) { showErr(err); }
      return;
    }
  }

  /* ---------------- ربط الصفحة ---------------- */
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('tt-add')?.addEventListener('click', addType);
    document.getElementById('types-table')?.addEventListener('click', e => {
      const b = e.target.closest('[data-tt]'); if (!b) return;
      const id = b.dataset.id;
      ({ edit: editType, toggle: toggleType, delete: deleteType, fields: fieldsModal, workflow: workflowModal })[b.dataset.tt](id);
    });
  });
})();
