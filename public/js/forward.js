// forward.js — شاشة إحالة المعاملة إلى قسم / موظف (?id=TRX-...)
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const $ = id => document.getElementById(id);
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const notice = (h, p, to, label) => '<div class="td-notfound"><h2>' + h + '</h2><p>' + p + '</p><button class="btn btn-primary" type="button" data-goto="' + to + '">' + label + '</button></div>';

  function init() {
    const f = $('forward-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    const box = $('fw-body');
    const t = ITQAN.trx(ITQAN.param('id'));
    if (!t) { box.innerHTML = notice(L('المعاملة غير موجودة'), L('قد تكون حُذفت أو أن الرابط غير صحيح.'), '/transactions', L('العودة إلى المعاملات')); return; }
    if (t.status !== 'pending') { box.innerHTML = notice(L('لا يمكن إحالة هذه المعاملة'), L('الإحالة متاحة للمعاملات قيد الانتظار فقط.'), '/transactions/details?id=' + t.id, L('العودة إلى تفاصيل المعاملة')); return; }

    $('fw-no').textContent = t.id;
    $('fw-subject').textContent = t.subject;
    $('fw-type').textContent = t.type;
    $('fw-dept').innerHTML = '<option value="">' + L('اختر القسم') + '</option>' + ITQAN.departments.map(d => '<option value="' + d.id + '">' + esc(d.name) + '</option>').join('');
    $('fw-user').innerHTML = '<option value="">' + L('— بدون تحديد موظف —') + '</option>' + ITQAN.users.filter(u => u.status === 'active' && u.id !== ITQAN.CURRENT_USER.id).map(u => '<option value="' + u.id + '">' + esc(u.name) + '</option>').join('');
    if (t.deptId) $('fw-dept').value = t.deptId;
    if (t.assignedTo) $('fw-user').value = t.assignedTo;

    $('fw-cancel').addEventListener('click', () => go('/transactions/details?id=' + t.id));
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const deptId = $('fw-dept').value, userId = $('fw-user').value, note = $('fw-note').value.trim();
      if (!deptId) { UI.toast(L('اختر القسم المحال إليه'), 'err'); $('fw-dept').focus(); return; }
      const d = ITQAN.dept(deptId), u = userId && ITQAN.user(userId);
      if (!d) { UI.toast(L('القسم المختار غير موجود'), 'err'); return; }
      try {
        const res = await API.post('/transactions/' + encodeURIComponent(t.id) + '/forward', {
          dept_id: parseInt(deptId, 10),
          user_id: userId ? parseInt(userId, 10) : null,
          note: note || null
        });
        t.deptId = d.id; t.assignedTo = u ? u.id : null;
        const entry = { t: L('تمت إحالة المعاملة إلى :name').replace(':name', d.name + (u ? ' — ' + u.name : '')), by: ITQAN.CURRENT_USER.name, at: UI.nowStamp() };
        if (note) entry.n = note;
        t.log.push(entry);
        ITQAN.logActivity && ITQAN.logActivity(L('إحالة معاملة'), t.id);
        UI.toast(res.message || L('تمت إحالة المعاملة'));
        go((res.data && res.data.redirect) || '/transactions/details?id=' + t.id);
      } catch (err) {
        UI.toast(err.message || L('تعذر إحالة المعاملة'), 'err');
      }
    });
  }
  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();