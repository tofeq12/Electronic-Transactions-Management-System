// data.js — مصدر البيانات المشترك للواجهة.
// تُحقن البيانات الحقيقية من الخادم (Laravel) في window.ITQAN_DATA،
// وعند عدم توفرها تُبقى القوائم فارغة وتُعرض حالات فارغة في الواجهة.
window.ITQAN = (function () {
  const D = (window.ITQAN_DATA && typeof window.ITQAN_DATA === 'object') ? window.ITQAN_DATA : {};
  const arr = v => (Array.isArray(v) ? v : []);
  // Display-time translation: exact Arabic-string lookup in the injected map
  // (empty for Arabic locale, so Arabic passes through unchanged).
  const TMAP = (window.ITQAN_T && typeof window.ITQAN_T === 'object') ? window.ITQAN_T : {};
  const t = s => (typeof s === 'string' && TMAP[s]) || s;

  const CURRENT_USER = Object.assign(
    { id: null, name: t('غير مسجل'), username: '', email: '', role: '', phone: '' },
    (D.currentUser && typeof D.currentUser === 'object') ? D.currentUser : {}
  );

  const STATUS = {
    done: { label: t('مكتملة'), cls: 'badge-green' },
    pending: { label: t('قيد الانتظار'), cls: 'badge-orange' },
    rejected: { label: t('مرفوضة'), cls: 'badge-red' },
    draft: { label: t('مسودة'), cls: 'badge-gray' },
    // Phase 6: returned-for-completion has its own badge (blue) so it is
    // visually distinct from a fresh draft.
    returned: { label: t('معادة للاكمال'), cls: 'badge-blue' }
  };

  const PRIORITY = {
    normal: { label: t('عادية'), cls: 'badge-gray' },
    urgent: { label: t('عاجلة'), cls: 'badge-red' }
  };

  const OVERDUE_DAYS = 3;
  const transactions = arr(D.transactions);
  const users = arr(D.users);
  const param = k => new URLSearchParams(location.search).get(k);

  return {
    CURRENT_USER,
    STATUS,
    PRIORITY,
    OVERDUE_DAYS,
    transactions,
    users,
    param,
    t,
    trx: id => transactions.find(t2 => String(t2.id) === String(id)),
    // Phase 7: fetch ONE transaction on demand when it is older than the
    // 500-row page payload (details/edit screens); cached after first hit.
    loadTrx: async id => {
      const hit = transactions.find(t2 => String(t2.id) === String(id));
      if (hit) return hit;
      try {
        const res = await fetch('/transactions/view?id=' + encodeURIComponent(id), {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (res.ok) {
          const payload = await res.json();
          if (payload && payload.success && payload.data) { transactions.push(payload.data); return payload.data; }
        }
      } catch (e) { /* offline — fall through */ }
      return null;
    },
    user: id => users.find(u => String(u.id) === String(id))
  };
})();
