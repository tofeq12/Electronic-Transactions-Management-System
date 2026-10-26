// colleges-page.js — المرحلة 11: إدارة الكليات داخل صفحة الأقسام
// (أعلى الهيكل التنظيمي). نفس البوابة الخادمية departments.manage؛
// الحذف ممنوع من الخادم ما دامت أقساماً أو مستخدمين يرتبطون بالكلية.
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const $ = id => document.getElementById(id);
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  function deptCount(id) {
    return (ITQAN.departments || []).filter(d => String(d.collegeId) === String(id)).length;
  }

  function render() {
    const table = $('college-table'); if (!table) return;
    ITQAN.colleges = ITQAN.colleges || [];
    table.tBodies[0].innerHTML = ITQAN.colleges.length ? ITQAN.colleges.map(c =>
      '<tr data-id="' + c.id + '"><td>' + c.id + '</td><td class="cg-name">' + esc(c.name) + '</td><td>' + deptCount(c.id) + '</td>' +
      '<td><div class="row-actions"><button class="icon-btn edit" data-action="cg-edit" aria-label="' + L('تعديل') + '">' + UI.icons.edit + '</button>' +
      '<button class="icon-btn delete" data-action="cg-delete" aria-label="' + L('حذف') + '">' + UI.icons.trash + '</button></div></td></tr>').join('')
      : '<tr class="table-empty"><td colspan="4">' + L('لا توجد كليات. أضف الكلية الأولى.') + '</td></tr>';
  }

  async function cgAction(act, id) {
    const c = (ITQAN.colleges || []).find(x => String(x.id) === String(id)); if (!c) return;

    if (act === 'edit') {
      // تحرير الاسم داخل الخلية نفسها: تحويل إلى حقل ثم حفظ/إلغاء.
      const tr = c && $('college-table').querySelector('tr[data-id="' + c.id + '"]');
      const cell = tr && tr.querySelector('.cg-name');
      if (!cell || cell.querySelector('input')) return;
      const prev = c.name;
      cell.innerHTML = '<input type="text" class="cg-input" maxlength="255" value="' + esc(prev) + '">';
      const inp = cell.querySelector('input');
      inp.focus(); inp.select();
      const save = async () => {
        const name = inp.value.trim();
        if (name.length < 2) { UI.toast(L('اسم الكلية حرفان على الأقل'), 'err'); inp.focus(); return; }
        if (ITQAN.colleges.some(x => x !== c && x.name.trim().toLowerCase() === name.toLowerCase())) { UI.toast(L('اسم الكلية مستخدم مسبقاً'), 'err'); inp.focus(); return; }
        try {
          await API.post('/colleges/' + c.id + '/update', { name: name });
          c.name = name;
          UI.toast(L('تم حفظ الكلية'));
          render();
          document.dispatchEvent(new Event('itqan:changed')); // خيارات القسم في النماذج
        } catch (err) {
          UI.toast((err.errors && Object.values(err.errors).flat()[0]) || err.message || L('تعذر تنفيذ الطلب'), 'err');
        }
      };
      inp.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); save(); }
        if (e.key === 'Escape') render();
      });
      inp.addEventListener('blur', () => { if (inp.value.trim() !== prev) save(); else render(); });
      return;
    }

    if (act === 'delete') {
      const yes = await UI.confirm({ title: L('حذف الكلية'), text: L('سيتم حذف كلية ":name" نهائياً ولا يمكن التراجع.').replace(':name', c.name), ok: L('حذف'), kind: 'danger' });
      if (!yes) return;
      try {
        const res = await API.post('/colleges/' + c.id + '/delete');
        ITQAN.colleges.splice(ITQAN.colleges.indexOf(c), 1);
        UI.toast(res.message || L('تم حذف الكلية'));
        render();
        document.dispatchEvent(new Event('itqan:changed'));
      } catch (err) {
        UI.toast(err.message || L('تعذر تنفيذ الطلب'), 'err');
      }
    }
  }

  function bindForm() {
    const f = $('college-form'); if (!f || f.dataset.bound) return; f.dataset.bound = 1;
    f.addEventListener('submit', async e => {
      e.preventDefault();
      const inp = $('college-name'), name = inp.value.trim();
      if (name.length < 2) { UI.toast(L('اسم الكلية حرفان على الأقل'), 'err'); inp.focus(); return; }
      if ((ITQAN.colleges || []).some(x => x.name.trim().toLowerCase() === name.toLowerCase())) { UI.toast(L('اسم الكلية مستخدم مسبقاً'), 'err'); inp.focus(); return; }
      try {
        const res = await API.post('/colleges', { name: name });
        ITQAN.colleges.push({ id: (res.data && res.data.id) || Math.max(0, ...ITQAN.colleges.map(x => x.id)) + 1, name });
        inp.value = '';
        UI.toast(L('تمت إضافة الكلية'));
        render();
        document.dispatchEvent(new Event('itqan:changed'));
      } catch (err) {
        UI.toast((err.errors && Object.values(err.errors).flat()[0]) || err.message || L('تعذر تنفيذ الطلب'), 'err');
      }
    });
  }

  if (!window.__cgBound) {
    window.__cgBound = true;
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-action^="cg-"]'); if (!b) return;
      const tr = b.closest('tr'); cgAction(b.dataset.action.slice(3), tr && tr.dataset.id);
    });
  }
  const init = () => { render(); bindForm(); };
  document.addEventListener('DOMContentLoaded', init);
  document.addEventListener('itqan:changed', render);
  if (document.readyState !== 'loading') init();
})();
