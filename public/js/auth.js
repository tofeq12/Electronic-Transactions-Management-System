// =========================================================
// auth.js — Login / Register: show-hide password, validation, submit
// =========================================================
document.addEventListener('DOMContentLoaded', () => {
  // Display-time translation: read the injected map (empty for the Arabic
  // locale, so Arabic keys pass through unchanged).
  const t = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
  const EYE_OFF = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17.9 17.9A10.9 10.9 0 0 1 12 19c-7 0-11-7-11-7a19 19 0 0 1 5-5.7M9.9 5.2A10 10 0 0 1 12 5c7 0 11 7 11 7a19 19 0 0 1-3.2 4.2M1 1l22 22"/></svg>';

  document.querySelectorAll('.ax-toggle').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = btn.parentElement.querySelector('input');
      const hide = input.type === 'password';
      input.type = hide ? 'text' : 'password';
      btn.innerHTML = hide ? EYE_OFF : EYE;
      btn.setAttribute('aria-label', hide ? t('إخفاء كلمة المرور') : t('إظهار كلمة المرور'));
    });
  });

  const rules = {
    user: v => v.trim().length >= 3 && (!v.includes('@') || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()))
      || t('أدخل اسم مستخدم أو بريداً إلكترونياً صحيحاً'),
    username: v => /^[a-z0-9_.]{3,}$/.test(v.trim().toLowerCase())
      || t('اسم المستخدم: أحرف صغيرة وأرقام و ( _ . ) — 3 أحرف على الأقل'),
    email: v => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) || t('أدخل بريداً إلكترونياً صحيحاً'),
    phone: v => /^\+?\d{9,15}$/.test(v.replace(/[\s()-]/g, '').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
      || t('أدخل رقم هاتف صحيحاً'),
    loginpass: v => v.length > 0 || t('أدخل كلمة المرور'),
    newpassword: v => (v.length >= 8 && /\d/.test(v) && /\D/.test(v)) || t('كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف'),
    confirm: (v, form) => (v.length > 0 && v === form.querySelector('[name="password"]').value)
      || t('كلمتا المرور غير متطابقتين')
  };

  function check(input, form) {
    const rule = rules[input.dataset.rule];
    if (!rule) return true;
    const res = rule(input.value, form);
    const field = input.closest('.ax-field');
    const ok = res === true;
    field.classList.toggle('has-error', !ok);
    input.setAttribute('aria-invalid', ok ? 'false' : 'true');
    field.querySelector('.ax-error').textContent = ok ? '' : res;
    return ok;
  }

  const shake = form => { const card = form.closest('.ax-card'); card.classList.remove('shake'); void card.offsetWidth; card.classList.add('shake'); };
  const fieldError = (form, idx, msg) => {
    const f = form.querySelectorAll('.ax-field')[idx];
    if (!f) return;
    f.classList.add('has-error');
    f.querySelector('.ax-error').textContent = msg;
  };

  [['login-form', '/dashboard'], ['register-form', '/dashboard']].forEach(([id, target]) => {
    const form = document.getElementById(id);
    if (!form) return;
    const inputs = [...form.querySelectorAll('[data-rule]')];
    inputs.forEach(i => {
      i.addEventListener('blur', () => i.value && check(i, form));
      i.addEventListener('input', () => { if (i.closest('.ax-field').classList.contains('has-error')) check(i, form); });
    });
    form.addEventListener('submit', e => {
      e.preventDefault();
      const results = inputs.map(i => check(i, form));
      if (!results.every(Boolean)) {
        shake(form);
        inputs[results.indexOf(false)].focus();
        return;
      }

      // التسجيل: تحقق مبدئي من المتصفح ثم إرسال النموذج الأصلي إلى الخادم
      // (التحقق النهائي والحفظ يتمان في Laravel).
      if (id === 'register-form') {
        const btn0 = form.querySelector('.ax-submit');
        btn0.disabled = true;
        btn0.textContent = t('جارٍ إنشاء الحساب...');
        form.submit();
        return;
      }

      const btn = form.querySelector('.ax-submit');
      if (!window.API) { form.submit(); return; } // fallback: native POST
      btn.disabled = true;
      btn.textContent = t('جارٍ المتابعة...');
      window.API.post(form.action, {
        email: form.querySelector('[name="email"]').value,
        password: form.querySelector('[name="password"]').value,
        remember: form.querySelector('[name="remember"]') ? (form.querySelector('[name="remember"]').checked ? 1 : 0) : 0
      }).then(res => {
        window.location.href = (res && res.data && res.data.redirect) || target;
      }).catch(err => {
        btn.disabled = false;
        btn.textContent = t('تسجيل الدخول');
        fieldError(form, 0, (err && err.message) || t('تعذر تسجيل الدخول'));
        shake(form);
      });
    });
  });
});
