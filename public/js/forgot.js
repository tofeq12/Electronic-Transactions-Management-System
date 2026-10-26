// forgot.js — استعادة كلمة المرور: رابط حقيقي عبر البريد
// المرحلة 9: نسيت كلمة المرور ← POST /forgot-password (Password broker حقيقي:
//    رمز مشفّر يُستعمل مرة واحدة، صلاحية 60 دقيقة، تحديد المحاولات) ← رابط يصل
//    إلى البريد ← صفحة /reset-password?token=&email= ← POST /reset-password.
// ⚠️ الصدق أولاً: إن لم يكن ناقل بريد حقيقي مفعّلاً (المُعد 'log' يسجّل الرسالة في
//    ملف السجل ولا يوصلها) يرفض الخادم الطلب بصراحة ويدل المستخدم على مسؤول
//    النظام — لا شاشة رمز وهمية بعد الآن (/verify-code بقيت كصفحة قديمة فقط).
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const $ = id => document.getElementById(id);
  const go = to => window.MotionNav ? MotionNav.go(to) : (location.href = to);

  function setError(input, msg) {
    const field = input.closest('.ax-field');
    field.classList.toggle('has-error', !!msg);
    field.querySelector('.ax-error').textContent = msg || '';
    input.setAttribute('aria-invalid', msg ? 'true' : 'false');
    return !msg;
  }
  function shake(form) { const c = form.closest('.ax-card'); c.classList.remove('shake'); void c.offsetWidth; c.classList.add('shake'); }
  function showNote(form, msg) {
    let note = $('fp-sent');
    if (!note) {
      note = document.createElement('p');
      note.id = 'fp-sent'; note.className = 'ax-desc'; note.setAttribute('role', 'status');
      form.parentNode.insertBefore(note, form);
    }
    note.textContent = msg; note.hidden = false;
  }

  /* ---- 1) نسيت كلمة المرور → طلب رابط الاستعادة ---- */
  function initForgot() {
    const f = $('forgot-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    const inp = $('fp-email'), btn = f.querySelector('.ax-submit');
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const v = inp.value.trim().toLowerCase();
      if (!setError(inp, /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : L('أدخل بريداً إلكترونياً صحيحاً'))) { shake(f); inp.focus(); return; }
      btn.disabled = true;
      try {
        await API.post('/forgot-password', { email: v });
        // نفس الرد دائماً سواء وُجد الحساب أو لم يوجد (منعاً لكشف الحسابات):
        // انتهت المهمة هنا — الرابط يصل إلى البريد إن كان مسجلاً.
        f.hidden = true;
        showNote(f, L('إذا كان البريد مسجلاً فسيصلك رابط إعادة تعيين كلمة المرور خلال دقائق'));
      } catch (err) {
        // 503: الاستعادة بالبريد غير مفعّلة — رفض صريح بدل ادّعاء إرسال.
        setError(inp, (err && err.message) || L('تعذر تنفيذ الطلب'));
        shake(f);
        btn.disabled = false;
      }
    });
    inp.addEventListener('input', () => inp.closest('.ax-field').classList.contains('has-error') && setError(inp, ''));
  }

  /* ---- 2) شاشة الرمز القديمة: خارج السلسلة الحقيقية ---- */
  function initVerify() {
    // بقيت للتوافق فقط؛ السلسلة الحقيقية تمر بالرابط المؤمّن في البريد.
    const f = $('verify-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    go('/forgot-password');
  }

  /* ---- 3) كلمة مرور جديدة عبر رابط البريد (?token=&email=) ---- */
  function initReset() {
    const f = $('reset-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    const qs = new URLSearchParams(location.search);
    const token = qs.get('token') || '', email = qs.get('email') || '';
    if (!token || !email) { go('/forgot-password'); return; }
    const p1 = $('rp-password'), p2 = $('rp-confirm');
    [p1, p2].forEach(i => i.addEventListener('input', () => i.closest('.ax-field').classList.contains('has-error') && setError(i, '')));
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const ok1 = setError(p1, (p1.value.length >= 8 && /\d/.test(p1.value) && /\D/.test(p1.value)) ? '' : L('كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف'));
      const ok2 = setError(p2, (p2.value && p2.value === p1.value) ? '' : L('كلمتا المرور غير متطابقتين'));
      if (!(ok1 && ok2)) { shake(f); (ok1 ? p2 : p1).focus(); return; }
      try {
        await API.post('/reset-password', { token: token, email: email, password: p1.value, password_confirmation: p2.value });
        f.hidden = true; $('rp-done').hidden = false;
      } catch (err) {
        // رمز غير صالح أو منتهي الصلاحية (تحقق الخادم دائماً).
        setError(p1, (err && err.message) || L('رابط إعادة التعيين غير صالح أو منتهي الصلاحية'));
        shake(f);
      }
    });
  }

  const init = () => { initForgot(); initVerify(); initReset(); };
  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();