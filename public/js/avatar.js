// avatar.js — صورة الملف الشخصي. تُرفع إلى الخادم (تخزين خاص) وتُخدَم من
// /profile/avatar للمستخدم الحالي فقط. لا تُخزَّن أي بيانات في المتصفح.
(function () {
  const MAX = 2 * 1024 * 1024, TYPES = ['image/png', 'image/jpeg', 'image/webp'];
  const DEF = 'images/avatar.svg';
  const toast = (m, type) => window.UI ? UI.toast(m, type) : alert(m);
  const t = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const bust = url => url + (url.indexOf('?') > -1 ? '&' : '?') + 'v=' + Date.now();

  function paint(src) {
    const target = src || DEF;
    document.querySelectorAll('.sidebar-user img, .pf-head img').forEach(img => { img.src = target; });
    document.querySelectorAll('[data-avatar-remove]').forEach(b => { b.hidden = !src; });
  }

  function currentHasPhoto() {
    const cu = window.ITQAN_DATA && ITQAN_DATA.currentUser;
    return !!(cu && cu.avatar);
  }

  if (!window.__avatarBound) {
    window.__avatarBound = true;

    document.addEventListener('click', async e => {
      if (e.target.closest('[data-avatar-pick]')) {
        const inp = document.getElementById('pf-avatar-input');
        if (inp) inp.click();
        return;
      }
      if (e.target.closest('[data-avatar-remove]')) {
        if (!window.API) return;
        try {
          const res = await API.post('/profile/avatar/delete', {});
          paint(res && res.data && res.data.url ? res.data.url : DEF);
          toast(t('تمت إزالة الصورة'));
        } catch (err) { toast(err.message || t('تعذر تنفيذ الطلب'), 'err'); }
      }
    });

    document.addEventListener('change', async e => {
      if (e.target.id !== 'pf-avatar-input') return;
      const f = e.target.files[0];
      e.target.value = '';
      if (!f) return;
      if (!TYPES.includes(f.type)) return toast(t('صيغ الصورة المسموحة: JPG أو PNG أو WEBP'), 'err');
      if (f.size > MAX) return toast(t('حجم الصورة لا يتجاوز 2 ميغابايت'), 'err');
      if (!window.API) return;
      const body = new FormData();
      body.append('avatar', f);
      try {
        const res = await API.post('/profile/avatar', body);
        paint(res && res.data && res.data.url ? bust(res.data.url) : bust('/profile/avatar'));
        toast(t('تم تحديث الصورة'));
      } catch (err) {
        const msgs = (err.errors && Object.values(err.errors).flat()) || [];
        toast(msgs[0] || err.message || t('تعذر تنفيذ الطلب'), 'err');
      }
    });
  }

  // الرسم الأول: الخادم يضع الرابط الصحيح في القوالب؛ نُظهر زر الإزالة فقط
  // عند وجود صورة مرفوعة.
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-avatar-remove]').forEach(b => { b.hidden = !currentHasPhoto(); });
  });
  if (document.readyState !== 'loading') {
    document.querySelectorAll('[data-avatar-remove]').forEach(b => { b.hidden = !currentHasPhoto(); });
  }
})();
