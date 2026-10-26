// ui.js — أدوات مشتركة: UI.toast / UI.confirm / UI.menu + معالجات النقر العامة (تُربط مرة واحدة)
(function () {
  if (window.UI) return;
  // Display-time translation (exact Arabic-key lookup, passthrough when the
  // locale is Arabic or the string is not in the map).
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : s;
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);
  const svg = d => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + d + '</svg>';
  const I = {
    eye: svg('<path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/>'),
    edit: svg('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'),
    check: svg('<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>'),
    x: svg('<circle cx="12" cy="12" r="9"/><path d="M9 9l6 6M15 9l-6 6"/>'),
    trash: svg('<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>'),
    power: svg('<path d="M12 3v9"/><path d="M6.4 6.4a8 8 0 1 0 11.2 0"/>'),
    warn: svg('<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>'),
    fwd: svg('<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4z"/>')
  };

  const UI = window.UI = { icons: I };

  /* ---------- Toast ---------- */
  UI.toast = (msg, type) => {
    let box = document.querySelector('.ui-toasts');
    if (!box) { box = document.createElement('div'); box.className = 'ui-toasts'; box.setAttribute('role', 'status'); document.body.appendChild(box); }
    const t = document.createElement('div');
    t.className = 'ui-toast ' + (type === 'err' ? 'err' : 'ok');
    t.textContent = L(msg);
    box.appendChild(t);
    setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 300); }, 2600);
  };

  /* ---------- Confirm modal (returns Promise<boolean>) ---------- */
  UI.confirm = ({ title, text, ok = 'تأكيد', cancel = 'إلغاء', kind = 'danger' }) => new Promise(res => {
    const icon = kind === 'success' ? I.check : kind === 'info' ? I.warn : I.warn;
    const btn = kind === 'success' ? 'btn-success' : kind === 'info' ? 'btn-primary' : 'btn-danger';
    const ov = document.createElement('div');
    ov.className = 'ui-overlay';
    ov.innerHTML = '<div class="ui-modal um-' + kind + '" role="dialog" aria-modal="true">' +
      '<div class="um-ico">' + icon + '</div><h3></h3><p></p>' +
      '<div class="um-actions"><button type="button" class="btn btn-outline" data-r="0"></button><button type="button" class="btn ' + btn + '" data-r="1"></button></div></div>';
    ov.querySelector('h3').textContent = L(title);
    ov.querySelector('p').textContent = L(text || '');
    ov.querySelector('[data-r="0"]').textContent = L(cancel);
    ov.querySelector('[data-r="1"]').textContent = L(ok);
    const close = v => { document.removeEventListener('keydown', onKey); ov.remove(); res(v); };
    const onKey = e => { if (e.key === 'Escape') close(false); };
    ov.addEventListener('click', e => { if (e.target === ov) close(false); const b = e.target.closest('[data-r]'); if (b) close(b.dataset.r === '1'); });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
    ov.querySelector('[data-r="1"]').focus();
  });

  /* ---------- Prompt modal with a required reason (returns Promise<string|null>) ---------- */
  UI.prompt = ({ title, text, placeholder = 'السبب...', ok = 'تأكيد', cancel = 'إلغاء' }) => new Promise(res => {
    const ov = document.createElement('div');
    ov.className = 'ui-overlay';
    ov.innerHTML = '<div class="ui-modal um-danger" role="dialog" aria-modal="true">' +
      '<div class="um-ico">' + I.x + '</div><h3></h3><p></p>' +
      '<textarea class="um-reason" rows="3" maxlength="1000" required></textarea>' +
      '<div class="um-hint" role="alert"></div>' +
      '<div class="um-actions"><button type="button" class="btn btn-outline" data-r="0"></button><button type="button" class="btn btn-danger" data-r="1"></button></div></div>';
    ov.querySelector('h3').textContent = L(title);
    ov.querySelector('p').textContent = L(text || '');
    const area = ov.querySelector('.um-reason');
    area.placeholder = L(placeholder);
    ov.querySelector('[data-r="0"]').textContent = L(cancel);
    ov.querySelector('[data-r="1"]').textContent = L(ok);
    const close = v => { document.removeEventListener('keydown', onKey); ov.remove(); res(v); };
    const onKey = e => { if (e.key === 'Escape') close(null); };
    ov.addEventListener('click', e => {
      if (e.target === ov) { close(null); return; }
      const b = e.target.closest('[data-r]');
      if (!b) return;
      if (b.dataset.r === '1') {
        const v = area.value.trim();
        if (v.length < 3) { ov.querySelector('.um-hint').textContent = L('السبب مطلوب (3 أحرف على الأقل)'); area.focus(); return; }
        close(v);
      } else {
        close(null);
      }
    });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
    area.focus();
  });

  /* ---------- Two-field prompt for return-for-completion (reason + missing items) ---------- */
  UI.returnPrompt = id => new Promise(res => {
    const ov = document.createElement('div');
    ov.className = 'ui-overlay';
    ov.innerHTML = '<div class="ui-modal um-danger" role="dialog" aria-modal="true">' +
      '<div class="um-ico">' + I.fwd + '</div><h3></h3><p></p>' +
      '<textarea class="um-reason" rows="2" maxlength="1000" required></textarea>' +
      '<textarea class="um-reason" rows="3" maxlength="2000" required></textarea>' +
      '<div class="um-hint" role="alert"></div>' +
      '<div class="um-actions"><button type="button" class="btn btn-outline" data-r="0"></button><button type="button" class="btn btn-danger" data-r="1"></button></div></div>';
    ov.querySelector('h3').textContent = L('إعادة المعاملة لاستكمال النواقص');
    ov.querySelector('p').textContent = L('سيتم إرجاع المعاملة :id إلى مقدم الطلب لاستكمال النواقص. أدخل السبب والمطلوب استكماله.').replace(':id', id);
    const areas = ov.querySelectorAll('.um-reason');
    areas[0].placeholder = L('سبب الإعادة...');
    areas[1].placeholder = L('المطلوب استكماله...');
    ov.querySelector('[data-r="0"]').textContent = L('إلغاء');
    ov.querySelector('[data-r="1"]').textContent = L('إعادة');
    const close = v => { document.removeEventListener('keydown', onKey); ov.remove(); res(v); };
    const onKey = e => { if (e.key === 'Escape') close(null); };
    ov.addEventListener('click', e => {
      if (e.target === ov) { close(null); return; }
      const b = e.target.closest('[data-r]');
      if (!b) return;
      if (b.dataset.r === '1') {
        const reason = areas[0].value.trim(), missing = areas[1].value.trim();
        if (reason.length < 3 || missing.length < 3) {
          ov.querySelector('.um-hint').textContent = L('السبب والمطلوب استكماله مطلوبان (3 أحرف على الأقل)');
          (reason.length < 3 ? areas[0] : areas[1]).focus();
          return;
        }
        close({ reason: reason, missing: missing });
      } else {
        close(null);
      }
    });
    document.addEventListener('keydown', onKey);
    document.body.appendChild(ov);
    areas[0].focus();
  });

  /* ---------- Dropdown menu anchored to a button ---------- */
  UI.closeMenu = () => document.querySelectorAll('.ui-menu').forEach(m => m.remove());
  UI.menu = (anchor, items) => {
    UI.closeMenu();
    items = items.filter((it, i, a) => !(it === '-' && (i === 0 || a[i - 1] === '-' || i === a.length - 1)));
    if (!items.length) return;
    const m = document.createElement('div'); m.className = 'ui-menu'; m.setAttribute('role', 'menu');
    items.forEach(it => {
      if (it === '-') { m.appendChild(document.createElement('hr')); return; }
      const b = document.createElement('button'); b.type = 'button'; b.className = it.cls || '';
      b.innerHTML = (it.icon || '') + '<span></span>'; b.querySelector('span').textContent = L(it.label);
      b.addEventListener('click', () => { UI.closeMenu(); it.run(); });
      m.appendChild(b);
    });
    document.body.appendChild(m);
    const r = anchor.getBoundingClientRect(), w = m.offsetWidth, h = m.offsetHeight;
    let left = r.left; if (left + w > innerWidth - 8) left = innerWidth - w - 8; if (left < 8) left = 8;
    let top = r.bottom + 6; if (top + h > innerHeight - 8) top = r.top - h - 6;
    m.style.left = left + 'px'; m.style.top = Math.max(8, top) + 'px';
  };

  /* ---------- Status helpers ---------- */
  UI.badge = status => {
    const s = ITQAN.STATUS[status] || ITQAN.STATUS.pending;
    return '<span class="badge ' + s.cls + '">' + s.label + '</span>';
  };
  UI.nowStamp = () => {
    const d = new Date(), p = n => String(n).padStart(2, '0');
    return d.getFullYear() + '/' + p(d.getMonth() + 1) + '/' + p(d.getDate()) + ' - ' + p(d.getHours()) + ':' + p(d.getMinutes());
  };

  /* ---------- Transaction actions (shared by table + details page) ---------- */
  UI.trxAction = async (act, id) => {
    const t = ITQAN.trx(id); if (!t) return false;
    if (act === 'view') { go('/transactions/details?id=' + id); return true; }
    if (act === 'edit') { go('/transactions/edit?id=' + id); return true; }
    if (act === 'forward') { go('/transactions/forward?id=' + id); return true; }
    if (act === 'approve' || act === 'reject') {
      const ok = act === 'approve';
      let reason = null;
      if (!ok) {
        reason = await UI.prompt({
          title: L('رفض المعاملة'),
          text: L('سيتم رفض المعاملة :id — أدخل سبب الرفض (يظهر في سجل المراجعة).').replace(':id', id),
          placeholder: L('سبب الرفض...'),
          ok: L('رفض')
        });
        if (!reason) return false;
      } else {
        const yes = await UI.confirm({
          title: L('الموافقة على المعاملة'),
          text: L('سيتم اعتماد المعاملة :id. هل أنت متأكد؟').replace(':id', id),
          ok: L('موافقة'), kind: 'success'
        });
        if (!yes) return false;
      }
      try {
        const res = await API.post('/transactions/' + encodeURIComponent(id) + '/' + (ok ? 'approve' : 'reject'),
          ok ? {} : { reason: reason });
        t.status = (res.data && res.data.status) || (ok ? 'done' : 'rejected');
        t.log.push({ t: ok ? L('تمت الموافقة') : L('تم رفض المعاملة'), by: ITQAN.CURRENT_USER.name, at: UI.nowStamp(), n: reason || undefined });
        ITQAN.logActivity && ITQAN.logActivity(ok ? 'موافقة على معاملة' : 'رفض معاملة', t.id);
        UI.toast(res.message || (ok ? L('تمت الموافقة على المعاملة') : L('تم رفض المعاملة')));
        document.dispatchEvent(new CustomEvent('itqan:changed'));
        return true;
      } catch (err) {
        UI.toast(err.message || L('تعذر تنفيذ الإجراء'), 'err');
        return false;
      }
    }
    if (act === 'return') {
      const r = await UI.returnPrompt(id);
      if (!r) return false;
      try {
        const res = await API.post('/transactions/' + encodeURIComponent(id) + '/return', { reason: r.reason, missing: r.missing });
        t.status = (res.data && res.data.status) || 'returned';
        t.log.push({ t: L('أعاد المعاملة إلى مقدم الطلب لاستكمال النواقص'), by: ITQAN.CURRENT_USER.name, at: UI.nowStamp(), n: r.reason + ' — ' + L('المطلوب استكماله') + ': ' + r.missing });
        ITQAN.logActivity && ITQAN.logActivity('إعادة معاملة لاستكمال', t.id);
        UI.toast(res.message || L('أُعيدت المعاملة إلى مقدم الطلب'));
        document.dispatchEvent(new CustomEvent('itqan:changed'));
        return true;
      } catch (err) {
        UI.toast(err.message || L('تعذر تنفيذ الإجراء'), 'err');
        return false;
      }
    }
    if (act === 'delete') {
      const yes = await UI.confirm({ title: L('حذف المعاملة'), text: L('سيتم حذف المعاملة :id نهائياً ولا يمكن التراجع.').replace(':id', id), ok: L('حذف'), kind: 'danger' });
      if (!yes) return false;
      try {
        const res = await API.post('/transactions/' + encodeURIComponent(id) + '/delete');
        ITQAN.transactions.splice(ITQAN.transactions.indexOf(t), 1);
        ITQAN.logActivity && ITQAN.logActivity('حذف معاملة', id);
        UI.toast(res.message || L('تم حذف المعاملة'));
        document.dispatchEvent(new CustomEvent('itqan:changed'));
        return true;
      } catch (err) {
        UI.toast(err.message || L('تعذر حذف المعاملة'), 'err');
        return false;
      }
    }
  };

  UI.userAction = async (act, id) => {
    const u = ITQAN.user(id); if (!u) return false;
    const self = u.id === ITQAN.CURRENT_USER.id;
    if (act === 'edit') { go('/users/edit?id=' + id); return true; }
    if (act === 'toggle') {
      if (self) { UI.toast(L('لا يمكنك تعطيل حسابك الحالي'), 'err'); return false; }
      const on = u.status === 'inactive';
      const yes = await UI.confirm({
        title: on ? L('تفعيل المستخدم') : L('تعطيل المستخدم'),
        text: (on ? L('سيتمكن :name من تسجيل الدخول.') : L('لن يتمكن :name من تسجيل الدخول.')).replace(':name', u.name),
        ok: on ? L('تفعيل') : L('تعطيل'), kind: on ? 'success' : 'danger'
      });
      if (!yes) return false;
      try {
        const res = await API.post('/users/' + u.id + '/toggle');
        u.status = (res.data && res.data.status) || (on ? 'active' : 'inactive');
        ITQAN.logActivity && ITQAN.logActivity(on ? 'تفعيل مستخدم' : 'تعطيل مستخدم', u.name);
        UI.toast(res.message || (on ? L('تم تفعيل المستخدم') : L('تم تعطيل المستخدم')));
        document.dispatchEvent(new CustomEvent('itqan:changed'));
        return true;
      } catch (err) {
        UI.toast(err.message || L('تعذر تنفيذ الإجراء'), 'err');
        return false;
      }
    }
    if (act === 'delete') {
      if (self) { UI.toast(L('لا يمكنك حذف حسابك الحالي'), 'err'); return false; }
      const yes = await UI.confirm({ title: L('حذف المستخدم'), text: L('سيتم حذف المستخدم :name نهائياً ولا يمكن التراجع.').replace(':name', u.name), ok: L('حذف'), kind: 'danger' });
      if (!yes) return false;
      try {
        const res = await API.post('/users/' + u.id + '/delete');
        ITQAN.users.splice(ITQAN.users.indexOf(u), 1);
        ITQAN.logActivity && ITQAN.logActivity('حذف مستخدم', u.name);
        UI.toast(res.message || L('تم حذف المستخدم'));
        document.dispatchEvent(new CustomEvent('itqan:changed'));
        return true;
      } catch (err) {
        UI.toast(err.message || L('تعذر حذف المستخدم'), 'err');
        return false;
      }
    }
  };

  /* ---------- Delegated handlers (bound once) ---------- */
  document.addEventListener('click', e => {
    const menuBtn = e.target.closest('[data-menu]');
    if (menuBtn) {
      e.stopPropagation();
      const tr = menuBtn.closest('tr'); const id = tr && tr.dataset.id;
      if (menuBtn.dataset.menu === 'trx') {
        const t = ITQAN.trx(id); if (!t) return;
        const items = [
          { label: L('عرض التفاصيل'), icon: I.eye, run: () => UI.trxAction('view', id) },
          { label: L('تعديل'), icon: I.edit, perm: 'trx.edit', run: () => UI.trxAction('edit', id) }
        ];
        if (t.status === 'pending') { items.push('-', { label: L('إحالة'), icon: I.fwd, perm: 'trx.forward', run: () => UI.trxAction('forward', id) }, { label: L('موافقة'), icon: I.check, cls: 'm-ok', perm: 'trx.approve', run: () => UI.trxAction('approve', id) }, { label: L('رفض'), icon: I.x, cls: 'm-danger', perm: 'trx.approve', run: () => UI.trxAction('reject', id) }); }
        items.push('-', { label: L('حذف'), icon: I.trash, cls: 'm-danger', perm: 'trx.delete', run: () => UI.trxAction('delete', id) });
        UI.menu(menuBtn, items);
      } else {
        const u = ITQAN.user(id); if (!u) return;
        UI.menu(menuBtn, [
          { label: L('تعديل'), icon: I.edit, run: () => UI.userAction('edit', id) },
          { label: u.status === 'active' ? L('تعطيل الحساب') : L('تفعيل الحساب'), icon: I.power, run: () => UI.userAction('toggle', id) }
        ]);
      }
      return;
    }
    UI.closeMenu();

    const act = e.target.closest('[data-action]');
    if (act) {
      const tr = act.closest('tr'); const id = act.dataset.id || (tr && tr.dataset.id);
      const a = act.dataset.action;
      if (a.startsWith('trx-')) UI.trxAction(a.slice(4), id);
      else if (a.startsWith('user-')) UI.userAction(a.slice(5), id);
      else if (a === 'download') UI.toast(L('تنزيل المرفقات يتوفر بعد ربط الخادم (Laravel)'));
      return;
    }
    if (e.target.closest('.sidebar-user')) { go('/profile'); return; }
    const row = e.target.closest('[data-href]');
    if (row && !e.target.closest('button, a')) go(row.dataset.href);
  });

  /* ---------- Logout confirmation (sidebar POST form) ----------
     Every page renders the same <form method="POST" action="/logout" class="sidebar-logout-form">.
     Intercept its submit, ask for confirmation, and only then let the real
     POST run (Auth::logout + session invalidate + CSRF regenerate happen server-side).
     Cancel keeps the session untouched; native form.submit() does not re-fire
     the submit event, so there is no re-entry. */
  document.addEventListener('submit', async e => {
    const form = e.target.closest('.sidebar-logout-form');
    if (!form) return;
    e.preventDefault();
    const yes = await UI.confirm({
      title: L('تسجيل الخروج'),
      text: L('هل أنت متأكد من تسجيل الخروج؟'),
      ok: L('نعم، تسجيل الخروج'),
      cancel: L('إلغاء'),
      kind: 'info'
    });
    if (yes) form.submit();
  });

  document.addEventListener('scroll', () => UI.closeMenu(), true);
  addEventListener('resize', () => UI.closeMenu());
  document.addEventListener('keydown', e => { if (e.key === 'Escape') UI.closeMenu(); });
})();
