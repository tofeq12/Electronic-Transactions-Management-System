// departments.js — بيانات الأقسام والكليات تُحقن من الخادم عبر window.ITQAN_DATA.
// عند عدم توفر البيانات تبقى القوائم فارغة وتُعرض حالات فارغة في الواجهة.
(function () {
  if (!window.ITQAN || ITQAN.departments) return;
  const D = (window.ITQAN_DATA && typeof window.ITQAN_DATA === 'object') ? window.ITQAN_DATA : {};
  ITQAN.departments = Array.isArray(D.departments) ? D.departments : [];
  // المرحلة 11: الكليات تُحقن هنا أيضاً — جداول الأقسام/الكليات وقوائم
  // «الكلية» في نماذج المستخدم والقسم تقرأ من هذا السطر.
  ITQAN.colleges = Array.isArray(D.colleges) ? D.colleges : [];
  ITQAN.dept = id => ITQAN.departments.find(d => String(d.id) === String(id));
  ITQAN.deptTrxCount = id => ITQAN.transactions.filter(t => String(t.deptId) === String(id)).length;
})();
