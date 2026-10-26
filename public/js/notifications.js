// notifications.js — فلترة (الكل / غير مقروء) + تعليم كمقروء + نقطة الشريط الجانبي
document.addEventListener('DOMContentLoaded', () => {
  const dots = () => document.querySelectorAll('.sidebar-nav .nav-dot');
  const list = document.querySelector('.nf-list');
  // لا إشعارات حقيقية بعد → تُخفى نقاط الإشعارات في كل الصفحات.
  dots().forEach(d => d.hidden = !document.querySelector('.nf-list .nf-item.unread'));
  if (!list) return;
  const tabs = document.querySelectorAll('.nf-tab');
  const empty = document.querySelector('.nf-empty');

  function render() {
    const onlyUnread = document.querySelector('.nf-tab.active')?.dataset.filter === 'unread';
    let shown = 0;
    list.querySelectorAll('.nf-item').forEach(it => {
      const hide = onlyUnread && !it.classList.contains('unread');
      it.hidden = hide; if (!hide) shown++;
    });
    empty?.classList.toggle('show', shown === 0);
    const anyUnread = !!list.querySelector('.unread');
    dots().forEach(d => d.hidden = !anyUnread);
  }
  tabs.forEach(t => t.addEventListener('click', () => { tabs.forEach(x => x.classList.remove('active')); t.classList.add('active'); render(); }));
  list.addEventListener('click', e => {
    const it = e.target.closest('.nf-item'); if (!it) return;
    const wasUnread = it.classList.contains('unread');
    it.classList.remove('unread'); render();
    // حفظ حالة القراءة في الخادم ثم فتح الرابط إن وجد.
    if (wasUnread && it.dataset.id && window.API) API.post('/notifications/' + it.dataset.id + '/read').catch(() => {});
    const link = it.dataset.link;
    if (link) { window.MotionNav ? MotionNav.go(link) : (location.href = link); }
  });
  document.querySelector('.nf-more')?.addEventListener('click', e => {
    e.preventDefault(); tabs.forEach(x => x.classList.toggle('active', x.dataset.filter === 'all')); render();
  });
  // Phase 8: bulk "mark all as read" — server-first, then refresh the page
  // so the sidebar dot and the unread tab reflect the new state.
  document.getElementById('nf-read-all')?.addEventListener('click', async () => {
    const btn = document.getElementById('nf-read-all');
    if (btn.disabled) return;
    btn.disabled = true;
    try {
      await API.post('/notifications/read-all');
      location.reload();
    } catch (e) {
      btn.disabled = false;
      if (window.UI && UI.toast) UI.toast((e && e.message) || 'تعذر تنفيذ الطلب', 'err');
    }
  });
  render();
});
