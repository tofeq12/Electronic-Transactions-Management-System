// settings.js — Settings screen: saved preferences, password form validation, demo backup (front-end only)
(function () {
  'use strict';
  const KEY = 'itqan_settings';
  const t = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const load = () => { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } };
  const save = data => { try { localStorage.setItem(KEY, JSON.stringify(data)); } catch (e) { } };

  function note(el, text, kind) {
    el.textContent = text;
    el.classList.toggle('is-ok', kind === 'ok');
    el.classList.toggle('is-error', kind === 'error');
  }

  function init() {
    const root = document.querySelector('.st-main');
    if (!root || root.dataset.stBound) return;
    root.dataset.stBound = '1';
    const $ = id => root.querySelector('#' + id);

    // ---- System preferences: language is saved server-side (per account) ----
    const prefs = { lang: $('st-lang'), timezone: $('st-timezone'), dateFormat: $('st-date-format') };
    const sysNote = $('st-system-note');
    const me = (window.ITQAN_DATA && ITQAN_DATA.currentUser) || {};
    prefs.lang.value = me.locale || (window.ITQAN_LANG) || 'ar';
    const saved = load();
    if (saved.timezone) prefs.timezone.value = saved.timezone;
    if (saved.dateFormat) prefs.dateFormat.value = saved.dateFormat;
    prefs.lang.addEventListener('change', () => {
      if (window.API) {
        API.post('/profile/locale', { locale: prefs.lang.value })
          .then(() => { note(sysNote, t('تم حفظ اللغة'), 'ok'); setTimeout(() => location.reload(), 400); })
          .catch(err => note(sysNote, (err.errors && Object.values(err.errors).flat())[0] || err.message || t('تعذر تنفيذ الطلب'), 'error'));
      }
    });
    ['timezone', 'dateFormat'].forEach(name => prefs[name].addEventListener('change', () => {
      const data = load(); data[name] = prefs[name].value; save(data);
      note(sysNote, t('تم حفظ الإعدادات'), 'ok');
    }));

    // ---- Change password (server-side: verifies current password) ----
    const form = $('st-password-form'), pwNote = $('st-password-note');
    const [current, next, confirm] = ['st-current', 'st-new', 'st-confirm'].map($);
    const mark = (el, bad) => el.parentNode.classList.toggle('is-invalid', bad);
    form.addEventListener('submit', e => {
      e.preventDefault();
      [current, next, confirm].forEach(el => mark(el, false));
      let error = '';
      if (!current.value) { mark(current, true); error = t('أدخل كلمة المرور الحالية.'); }
      else if (next.value.length < 8 || !/\d/.test(next.value) || !/\D/.test(next.value)) { mark(next, true); error = t('كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف.'); }
      else if (next.value !== confirm.value) { mark(confirm, true); error = t('تأكيد كلمة المرور غير مطابق.'); }
      if (error) return note(pwNote, error, 'error');
      if (!window.API) return;
      API.post('/settings/password', { current_password: current.value, password: next.value })
        .then(() => { form.reset(); note(pwNote, t('تم حفظ التغييرات بنجاح'), 'ok'); })
        .catch(err => {
          const msgs = (err.errors && Object.values(err.errors).flat()) || [];
          note(pwNote, msgs[0] || err.message || t('تعذر حفظ كلمة المرور'), 'error');
        });
    });

    // ---- Backup: downloads the saved settings as a JSON file (no server in this front-end project) ----
    const backupNote = $('st-backup-note');
    $('st-backup').addEventListener('click', () => {
      const stamp = new Date().toISOString();
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([JSON.stringify({ createdAt: stamp, settings: load() }, null, 2)], { type: 'application/json' }));
      a.download = `itqan-backup-${stamp.slice(0, 10)}.json`;
      a.addEventListener('click', e => e.stopPropagation());   // keep the page-router (motion.js) out of downloads
      document.body.appendChild(a); a.click(); a.remove();
      setTimeout(() => URL.revokeObjectURL(a.href), 1000);
      note(backupNote, t('تم إنشاء النسخة الاحتياطية'), 'ok');
    });
  }

  document.addEventListener('DOMContentLoaded', init);
  if (document.readyState !== 'loading') init();
})();
