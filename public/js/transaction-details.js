// transaction-details.js — تعبئة صفحة تفاصيل المعاملة من ITQAN حسب ?id=
(function () {
  const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);
  const FILE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v14H7z"/><path d="M14 3v5h5"/></svg>';
  const DL = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M7 11l5 5 5-5"/><path d="M5 21h14"/></svg>';
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const $ = id => document.getElementById(id);

  async function render() {
    const page = $('td-page'); if (!page) return;
    const id = ITQAN.param('id');
    let t = ITQAN.trx(id);
    // Phase 7: records older than the 500-row page payload are fetched
    // on demand (cached in ITQAN.transactions after the first hit).
    if (!t && id && window.ITQAN && ITQAN.loadTrx) {
      t = await ITQAN.loadTrx(id);
    }
    page.hidden = !t; $('td-notfound').hidden = !!t;
    if (!t) {
      if (page.dataset.rendered) { (window.MotionNav ? MotionNav.go('/transactions') : (location.href = '/transactions')); }
      return;
    }
    page.dataset.rendered = 1;
    $('td-no').textContent = t.id;
    const st = ITQAN.STATUS[t.status];
    const b = $('td-status'); b.className = 'badge ' + st.cls; b.textContent = st.label;
    $('td-type').textContent = t.type;
    $('td-date').textContent = t.date + ' - ' + t.time;
    $('td-subject').textContent = t.subject;
    $('td-to').textContent = t.to;
    $('td-from').textContent = t.from;
    const pr = ITQAN.PRIORITY[t.priority] || ITQAN.PRIORITY.normal;
    $('td-priority').innerHTML = '<span class="badge ' + pr.cls + '">' + pr.label + '</span>';
    const dept = ITQAN.dept && ITQAN.dept(t.deptId), who = t.assignedTo && ITQAN.user(t.assignedTo);
    $('td-assign').textContent = (dept || who) ? [dept && dept.name, who && who.name].filter(Boolean).join(' — ') : '—';
    $('td-amount').textContent = t.amount + ' ' + L('ريال');
    $('td-desc').textContent = t.desc || '—';

    // Phase 4: dynamic per-type field values — labels resolved from
    // ITQAN_DATA.trxTypes (current user's locale, set by the server).
    const fdWrap = $('td-formdata-wrap'), fdBox = $('td-formdata');
    if (fdWrap && fdBox) {
      const types = (window.ITQAN_DATA && ITQAN_DATA.trxTypes) || [];
      const cfg = types.find(x => x.slug === t.typeSlug);
      const labels = {}; (cfg ? cfg.fields : []).forEach(f => { labels['f' + f.id] = f.label; });
      const entries = Object.entries(t.formData || {}).filter(([k, v]) => v !== '' && v !== null && v !== undefined);
      fdWrap.hidden = entries.length === 0;
      fdBox.innerHTML = entries.map(([k, v]) =>
        '<div class="td-f"><label>' + esc(L(labels[k] || k)) + '</label><div>' + esc(v === '1' ? L('نعم') : String(v)) + '</div></div>').join('');
    }

    // Phase 5: workflow progress — the immutable snapshot taken at
    // submission. Renders the step list (done/current/pending) plus the
    // recorded decisions with their reasons. Hidden when the transaction
    // followed the legacy single-step path (workflow === null).
    const wfWrap = $('td-workflow-wrap'), wfBox = $('td-workflow');
    if (wfWrap && wfBox) {
      const wf = t.workflow;
      if (!wf || !wf.total) {
        wfWrap.hidden = true; wfBox.innerHTML = '';
      } else {
        wfWrap.hidden = false;
        const tgt = { department: s => s.targetDept, role: s => s.targetRoleLabel || s.targetRole, user: s => s.targetUser };
        wfBox.innerHTML =
          '<div class="wf-steps">' + wf.steps.map((s, i) => {
            const state = s.current ? ' current' : (s.done ? ' done' : '');
            const target = (tgt[s.target_type] ? tgt[s.target_type](s) : '') || '—';
            return '<div class="wf-step' + state + '">' +
              '<span class="wf-i">' + (s.done ? '✓' : (i + 1)) + '</span>' +
              '<span class="wf-n">' + esc(s.name) + '</span>' +
              '<span class="wf-t">' + esc(target) + '</span>' +
              (s.current ? '<span class="wf-badge">' + esc(L('الحالية')) + '</span>' : '') +
              '</div>';
          }).join('') + '</div>' +
          (wf.completed && wf.completed.length
            ? '<div class="wf-decisions">' + wf.completed.map(c =>
                '<div class="wf-dec' + (/reject/.test(c.decision) ? ' rej' : '') + '">' +
                '<div class="s-t">' + esc(L('خطوة') + ' ' + c.step + ' — ' + c.name) + '</div>' +
                '<div class="s-by">' + esc(L('قرار') + ': ' + L(c.decision === 'approve' ? 'اعتماد' : (c.decision === 'reject' ? 'رفض' : 'إعادة لاستكمال'))) + '</div>' +
                (c.reason ? '<div class="s-n">' + esc(c.reason) + '</div>' : '') +
                '<div class="s-at">' + esc((c.by || '') + ' — ' + (c.at || '')) + '</div>' +
                '</div>').join('') + '</div>'
            : '');
      }
    }

    $('td-files').innerHTML = t.files.length
      ? t.files.map((name, i) => {
          const meta = (t.fileMeta || [])[i];
          const dl = meta
            ? '<a class="f-dl icon-btn" href="' + esc(meta.url) + '" target="_blank" rel="noopener" aria-label="' + L('تنزيل') + '">' + DL + '</a>'
            : '<button type="button" class="f-dl icon-btn" data-action="download" aria-label="' + L('تنزيل') + '">' + DL + '</button>';
          return '<div class="td-file"><span class="f-ico">' + FILE + '</span>' + dl + '<span class="f-name">' + esc(name) + '</span></div>';
        }).join('')
      : '<div class="td-none">' + L('لا توجد مرفقات') + '</div>';

    // ملاحظة: /رفض/ يطابق نصوص السجل المخزّنة في الخادم (عربية دائماً) على
    // القيمة الخام للتمييز؛ أما العرض فيمر عبر L() (مطابقة تامة للخريطة).
    $('td-log').innerHTML = t.log.map(s => '<div class="td-step' + (/رفض/.test(s.t) ? ' rej' : '') + '"><div><div class="s-t">' + esc(L(s.t)) + '</div><div class="s-by">' + esc(s.by) + '</div>' + (s.n ? '<div class="s-n">' + esc(s.n) + '</div>' : '') + '</div><div class="s-at">' + esc(s.at) + '</div></div>').join('');

    const acts = $('td-actions');
    acts.querySelectorAll('[data-action]').forEach(a => { a.dataset.id = t.id; });
    acts.querySelectorAll('[data-only-pending]').forEach(a => { a.hidden = t.status !== 'pending'; });
  }
  document.addEventListener('DOMContentLoaded', render);
  document.addEventListener('itqan:changed', render);
  if (document.readyState !== 'loading') render();
})();