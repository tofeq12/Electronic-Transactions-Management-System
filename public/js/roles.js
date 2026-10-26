// roles.js — بيانات الأدوار والصلاحيات تُحقن من الخادم عبر window.ITQAN_DATA
// (محمية ببوابة roles.manage فيخلى المدير فقط). عند عدم توفر البيانات تبقى
// القوائم فارغة وتُعرض حالات فارغة في الواجهة.
(function () {
  if (!window.ITQAN || ITQAN.roles) return;
  const D = (window.ITQAN_DATA && typeof window.ITQAN_DATA === 'object') ? window.ITQAN_DATA : {};
  ITQAN.roles = Array.isArray(D.roles) ? D.roles : [];
  ITQAN.permissions = Array.isArray(D.permissions) ? D.permissions : [];
  ITQAN.role = id => ITQAN.roles.find(r => String(r.id) === String(id));
  ITQAN.perm = id => ITQAN.permissions.find(p => String(p.id) === String(id));
})();
