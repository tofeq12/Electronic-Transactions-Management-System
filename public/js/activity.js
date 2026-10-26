// activity.js — سجل النشاطات: المصدر الأساسي هو جدول activity_logs في الخادم
// (ITQAN_DATA.activity المحقون من Laravel). عند عدم التوفر يُبنى fallback
// من سجلات المعاملات، وتُضاف الأحداث الجديدة مؤقتاً عبر ITQAN.logActivity.
(function () {
  if (!window.ITQAN) return;
  const injected = (window.ITQAN_DATA && Array.isArray(window.ITQAN_DATA.activity)) ? window.ITQAN_DATA.activity : null;
  const act = ITQAN.activity = (injected && injected.length) ? injected.slice() : [];
  if (!injected || !injected.length) {
    ITQAN.transactions.forEach(t => (t.log || []).forEach(l => act.push({ at: l.at, by: l.by, action: l.t, target: t.id })));
  }
  ITQAN.logActivity = (action, target) => {
    act.push({ at: window.UI ? UI.nowStamp() : '', by: ITQAN.CURRENT_USER.name, action, target: target || '—' });
  };
})();
