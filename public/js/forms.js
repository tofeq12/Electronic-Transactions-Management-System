// forms.js — نماذج: معاملة جديدة/تعديل، مستخدم جديد/تعديل، الملف الشخصي (واجهة فقط — بدون خادم)
(function () {
  const T = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);
  const norm = s => String(s || '').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)).trim();
  const $ = id => document.getElementById(id);
  const fail = (el, msg) => { UI.toast(msg, 'err'); el && el.focus(); return false; };
  const today = () => { const d = new Date(), p = n => String(n).padStart(2, '0'); return d.getFullYear() + '/' + p(d.getMonth() + 1) + '/' + p(d.getDate()); };
  const validPhone = v => /^\+?\d{9,15}$/.test(norm(v).replace(/[\s()-]/g, ''));
  const strongPass = p => p.length >= 8 && /\d/.test(p) && /\D/.test(p);
  const PASS_MSG = T('كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف');
  const nextTrxId = () => 'TRX-2024-' + String(Math.max(0, ...ITQAN.transactions.map(t => parseInt(t.id.slice(-4), 10))) + 1).padStart(4, '0');
  const showErr = err => {
    const msgs = (err && err.errors) || {};
    const first = Object.values(msgs).flat()[0];
    UI.toast(first || (err && err.message) || T('تعذر تنفيذ العملية'), 'err');
  };
  const fd = obj => { const f = new FormData(); Object.entries(obj).forEach(([k, v]) => { if (v !== undefined && v !== null) f.append(k, v); }); return f; };
  const appendFiles = f => { const inp = document.querySelector('.dropzone input[type=file]'); if (inp) [...inp.files].forEach(file => f.append('files[]', file)); };

  /* ---------------- المعاملات ---------------- */
  function readTrx() {
    const subject = $('trx-subject').value.trim();
    const amount = norm($('trx-amount').value).replace(/,/g, '');
    const date = norm($('trx-date').value).replace(/[-.]/g, '/');
    if (!subject) return fail($('trx-subject'), T('أدخل موضوع المعاملة'));
    if (!/^\d+(\.\d{1,2})?$/.test(amount) || parseFloat(amount) <= 0) return fail($('trx-amount'), T('أدخل مبلغاً صحيحاً أكبر من صفر'));
    if (!/^\d{4}\/\d{2}\/\d{2}$/.test(date)) return fail($('trx-date'), T('صيغة التاريخ: YYYY/MM/DD'));
    return {
      type: $('trx-type').value, subject, from: $('trx-from').value.trim(), to: $('trx-to').value,
      amount: parseFloat(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
      date, desc: $('trx-desc').value.trim(), priority: $('trx-priority') ? $('trx-priority').value : 'normal'
    };
  }
  function pickedFiles() {
    const inp = document.querySelector('.dropzone input[type=file]');
    return inp ? [...inp.files].map(f => f.name) : [];
  }
  /* ---------------- حقول النوع الديناميكية (المرحلة 4) ---------------- */
  // عرض/إخفاء مجموعة الحقول حسب النوع المختار، وجمع القيم إلى JSON.
  // التحقق النهائي يتم على الخادم (TypeConfigService) — هذا للراحة فقط.
  function syncDynFields() {
    const sel = $('trx-type'); if (!sel) return;
    const slug = sel.value; // الأنواع المهيّأة تُرسل slug؛ الأربعة النظامية تُرسل بسمها العربي (بدون حقول)
    document.querySelectorAll('.dyn-fields, .dyn-fields-title').forEach(el => { el.hidden = el.dataset.type !== slug; });
  }
  function readDynFields() {
    const out = {};
    document.querySelectorAll('.dyn-fields:not([hidden]) [data-fid]').forEach(el => {
      const fid = 'f' + el.dataset.fid;
      if (el.type === 'checkbox') { if (el.checked) out[fid] = '1'; }
      else if (el.value.trim() !== '') out[fid] = el.value.trim();
    });
    return Object.keys(out).length ? JSON.stringify(out) : '';
  }
  function dynHasMissing() {
    return [...document.querySelectorAll('.dyn-fields:not([hidden]) [data-req]')].some(el =>
      el.type === 'checkbox' ? false : el.value.trim() === '');
  }
  function clearDynValues() {
    document.querySelectorAll('.dyn-fields [data-fid]').forEach(el => { el.type === 'checkbox' ? (el.checked = false) : (el.value = ''); });
  }
  window.ITQAN_DYN = { sync: syncDynFields, read: readDynFields, missing: dynHasMissing, clear: clearDynValues };
  function bindNewTrx() {
    const f = $('transaction-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    if ($('trx-date')) $('trx-date').value = today();
    syncDynFields();
    $('trx-type')?.addEventListener('change', () => { syncDynFields(); });
    const save = async status => {
      const d = readTrx(); if (!d) return;
      if (status === 'pending' && dynHasMissing()) return UI.toast(T('أكمل الحقول المطلوبة'), 'err');
      const body = fd({
        type: d.type, subject: d.subject, from: d.from, to: d.to,
        amount: String(d.amount).replace(/,/g, ''), date: d.date, desc: d.desc,
        priority: d.priority, status: status
      });
      const dyn = readDynFields(); if (dyn) body.append('form_data', dyn);
      appendFiles(body);
      try {
        const res = await API.post('/transactions', body);
        UI.toast(status === 'draft' ? T('تم حفظ المعاملة كمسودة') : T('تم إرسال المعاملة بنجاح'));
        go((res.data && res.data.redirect) || '/transactions');
      } catch (err) { showErr(err); }
    };
    f.addEventListener('submit', e => { e.preventDefault(); save('pending'); });
    $('save-draft')?.addEventListener('click', () => save('draft'));
  }
  async function bindEditTrx() {
    const f = $('transaction-edit-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    let t = ITQAN.trx(ITQAN.param('id'));
    // Phase 7: records older than the 500-row page payload are fetched
    // on demand (same visibleTo() boundary on the server).
    if (!t && window.ITQAN && ITQAN.loadTrx) t = await ITQAN.loadTrx(ITQAN.param('id'));
    if (!t) { f.closest('.form-panel-body').innerHTML = '<div class="td-notfound"><h2>' + T('المعاملة غير موجودة') + '</h2><p>' + T('قد تكون حُذفت أو أن الرابط غير صحيح.') + '</p><button class="btn btn-primary" type="button" data-goto="/transactions">' + T('العودة إلى المعاملات') + '</button></div>'; return; }
    $('trx-no').value = t.id; $('trx-type').value = t.type; $('trx-subject').value = t.subject; $('trx-from').value = t.from;
    $('trx-to').value = t.to; $('trx-amount').value = t.amount; $('trx-date').value = t.date; $('trx-desc').value = t.desc || '';
    if ($('trx-priority')) $('trx-priority').value = t.priority || 'normal';
    syncDynFields();
    $('trx-type')?.addEventListener('change', () => { syncDynFields(); });
    // تعبئة الحقول الديناميكية بالقيم المحفوظة (t.formData من الخادم)
    if (t.formData) Object.entries(t.formData).forEach(([k, v]) => {
      const el = document.querySelector('.dyn-fields [data-fid="' + k.replace(/^f/, '') + '"]');
      if (!el) return;
      el.type === 'checkbox' ? (el.checked = v === '1' || v === 1) : (el.value = v);
    });
    const list = $('trx-files');
    const removed = [];
    const draw = () => { list.innerHTML = t.files.length ? t.files.map((n, i) => '<div class="td-file"><span class="f-name">' + n.replace(/[<>&]/g, '') + '</span><button type="button" class="icon-btn delete" data-rm="' + i + '" aria-label="' + T('حذف المرفق') + '">' + UI.icons.x + '</button></div>').join('') : ''; };
    draw();
    list.addEventListener('click', e => { const b = e.target.closest('[data-rm]'); if (b) { removed.push(t.files[+b.dataset.rm]); t.files.splice(+b.dataset.rm, 1); draw(); } });
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const d = readTrx(); if (!d) return;
      const body = fd({
        type: d.type, subject: d.subject, from: d.from, to: d.to,
        amount: String(d.amount).replace(/,/g, ''), date: d.date, desc: d.desc,
        priority: d.priority,
        status: (t.status === 'draft' || t.status === 'returned') ? 'pending' : t.status
      });
      const dyn = readDynFields(); if (dyn) body.append('form_data', dyn);
      removed.forEach(n => body.append('removed_files[]', n));
      appendFiles(body);
      try {
        const res = await API.post('/transactions/' + encodeURIComponent(t.id) + '/update', body);
        UI.toast(T('تم حفظ التعديلات'));
        go((res.data && res.data.redirect) || '/transactions/details?id=' + t.id);
      } catch (err) { showErr(err); }
    });
  }

  /* ---------------- المستخدمون ---------------- */
  function readUser(self) {
    const name = $('u-name').value.trim(), username = $('u-username').value.trim().toLowerCase(), email = $('u-email').value.trim().toLowerCase(), pass = $('u-pass').value, phone = $('u-phone') ? $('u-phone').value.trim() : '';
    if (name.length < 3) return fail($('u-name'), T('الاسم الكامل 3 أحرف على الأقل'));
    if (!/^[a-z0-9_.]{3,}$/.test(username)) return fail($('u-username'), T('اسم المستخدم: 3 أحرف إنجليزية/أرقام على الأقل'));
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail($('u-email'), T('أدخل بريداً إلكترونياً صحيحاً'));
    if (!validPhone(phone)) return fail($('u-phone'), T('أدخل رقم هاتف صحيحاً (9 إلى 15 رقماً)'));
    if (ITQAN.users.some(u => u !== self && u.username === username)) return fail($('u-username'), T('اسم المستخدم مستخدم مسبقاً'));
    if (ITQAN.users.some(u => u !== self && u.email === email)) return fail($('u-email'), T('البريد الإلكتروني مستخدم مسبقاً'));
    if ((!self || pass) && !strongPass(pass)) return fail($('u-pass'), PASS_MSG);
    const rec = { name, username, email, phone: norm(phone).replace(/[\s()-]/g, ''), role: $('u-role').value, status: $('u-status').value };
    // الإرسال: الإضافة تتطلب كلمة المرور (StoreUserRequest) والتعديل يقبل
    // تركها فارغة (UpdateUserRequest nullable) فتبقى القديمة كما هي.
    if (pass) rec.password = pass;
    if ($('u-role').disabled) delete rec.role; // أدوار لا يمثلها النموذج (university_admin / dept_manager)
    if ($('u-college')) rec.college_id = $('u-college').value;
    if ($('u-dept')) rec.department_id = $('u-dept').value;
    return rec;
  }
  /* تعبئة قائمتي الكلية والقسم من ITQAN (مرة واحدة) + فلترة الأقسام حسب الكلية */
  function fillOrg() {
    const cs = $('u-college'), ds = $('u-dept');
    if (cs && cs.dataset.filled !== '1') {
      cs.dataset.filled = '1';
      (ITQAN.colleges || []).forEach(c => {
        const o = document.createElement('option'); o.value = c.id; o.textContent = c.name; cs.appendChild(o);
      });
    }
    if (ds && ds.dataset.filled !== '1') {
      ds.dataset.filled = '1';
      (ITQAN.departments || []).forEach(d => {
        const o = document.createElement('option'); o.value = d.id; o.textContent = d.name;
        if (d.collegeId) o.dataset.college = d.collegeId;
        ds.appendChild(o);
      });
      const filter = () => {
        const pick = cs ? cs.value : '';
        [...ds.options].forEach(o => { if (o.value) o.hidden = !!(o.dataset.college && pick && o.dataset.college !== pick); });
        const cur = ds.selectedOptions[0];
        if (cur && cur.value && cur.hidden) ds.value = '';
      };
      if (cs) cs.addEventListener('change', filter);
    }
  }
  function bindAddUser() {
    const f = $('add-user-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    fillOrg();
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const d = readUser(null); if (!d) return;
      try {
        const res = await API.post('/users', fd(d));
        UI.toast(T('تمت إضافة المستخدم'));
        go((res.data && res.data.redirect) || '/dashboard/users');
      } catch (err) { showErr(err); }
    });
  }
  function bindEditUser() {
    const f = $('edit-user-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    fillOrg();
    const u = ITQAN.user(ITQAN.param('id'));
    if (!u) { f.closest('.form-panel-body').innerHTML = '<div class="td-notfound"><h2>' + T('المستخدم غير موجود') + '</h2><p>' + T('قد يكون حُذف أو أن الرابط غير صحيح.') + '</p><button class="btn btn-primary" type="button" data-goto="/dashboard/users">' + T('العودة إلى المستخدمين') + '</button></div>'; return; }
    $('u-name').value = u.name; $('u-username').value = u.username; $('u-email').value = u.email; if ($('u-phone')) $('u-phone').value = u.phone || ''; $('u-status').value = u.status;
    const slug = u.roleSlug || u.role;
    if (slug === 'university_admin' || slug === 'dept_manager') {
      $('u-role').disabled = true; // لا يمثلها النموذج — لا تُرسل لتفادي تغيير الدور صامتاً
    } else {
      $('u-role').value = u.role;
    }
    if (u.id === ITQAN.CURRENT_USER.id) { $('u-status').disabled = true; }
    if ($('u-college')) {
      if (u.collegeId) $('u-college').value = u.collegeId;
      if ($('u-dept')) {
        if (u.departmentId) $('u-dept').value = u.departmentId;
        $('u-college').dispatchEvent(new Event('change')); // فلترة الأقسام حسب الكلية المختارة
      }
    }
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const d = readUser(u); if (!d) return;
      if (u.id === ITQAN.CURRENT_USER.id) d.status = 'active';
      try {
        const res = await API.post('/users/' + u.id + '/update', fd(d));
        Object.assign(u, { name: d.name, username: d.username, email: d.email, phone: d.phone, status: d.status || u.status });
        if (d.role) u.role = d.role;
        if (u.id === ITQAN.CURRENT_USER.id) Object.assign(ITQAN.CURRENT_USER, { name: d.name, username: d.username, email: d.email, phone: d.phone });
        UI.toast(T('تم حفظ بيانات المستخدم'));
        go((res.data && res.data.redirect) || '/dashboard/users');
      } catch (err) { showErr(err); }
    });
  }

  /* ---------------- الملف الشخصي + اسم المستخدم في الشريط الجانبي ---------------- */
  function syncSidebarUser() {
    const me = ITQAN.CURRENT_USER;
    document.querySelectorAll('.sidebar-user .su-name').forEach(n => n.textContent = me.id ? me.name : T('غير مسجل'));
    document.querySelectorAll('.sidebar-user .su-role').forEach(n => n.textContent = me.role ? (me.role === 'مدير' ? T('مدير النظام') : T(me.role)) : '—');
  }
  function bindProfile() {
    const f = $('profile-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    const me = ITQAN.CURRENT_USER;
    $('pf-username').value = me.username || ''; $('pf-email').value = me.email || ''; $('pf-phone').value = me.phone || ''; $('pf-role').value = me.role ? T(me.role) : '';
    const paint = () => { $('pf-title').textContent = me.name; $('pf-sub').textContent = me.role === 'مدير' ? T('مدير النظام') : T(me.role); };
    paint();
    f.addEventListener('submit', e => {
      e.preventDefault();
      const email = $('pf-email').value.trim().toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail($('pf-email'), T('أدخل بريداً إلكترونياً صحيحاً'));
      if (ITQAN.users.some(u => u.id !== me.id && u.email === email)) return fail($('pf-email'), T('البريد الإلكتروني مستخدم مسبقاً'));
      const phone = $('pf-phone').value.trim();
      if (!validPhone(phone)) return fail($('pf-phone'), T('أدخل رقم هاتف صحيحاً (9 إلى 15 رقماً)'));
      API.post('/profile/update', fd({ email, phone: norm(phone).replace(/[\s()-]/g, '') })).then(res => {
        const u = (res.data && res.data.user) || null;
        if (u) Object.assign(ITQAN.CURRENT_USER, { email: u.email, phone: u.phone });
        const rec = ITQAN.user(ITQAN.CURRENT_USER.id);
        if (rec) { rec.email = email; rec.phone = ITQAN.CURRENT_USER.phone; }
        ITQAN.logActivity && ITQAN.logActivity(T('تعديل الملف الشخصي'), email);
        paint(); syncSidebarUser(); UI.toast(T('تم حفظ بياناتك'));
      }).catch(err => showErr(err));
    });
  }

  function init() { bindNewTrx(); bindEditTrx(); bindAddUser(); bindEditUser(); bindProfile(); syncSidebarUser(); }
  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();
