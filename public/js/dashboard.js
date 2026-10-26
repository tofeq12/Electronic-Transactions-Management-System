// =========================================================
// dashboard.js — Users / Transactions tables + new-transaction form
// =========================================================
const L = s => (window.ITQAN && ITQAN.t) ? ITQAN.t(s) : ((window.ITQAN_T && ITQAN_T[s]) || s);

document.addEventListener('DOMContentLoaded', () => {

  // ---- Live search filter for any table on the page ----
  document.querySelectorAll('[data-table-search]').forEach(input => {
    const tableSelector = input.getAttribute('data-table-search');
    const table = document.querySelector(tableSelector);
    if (!table) return;
    input.addEventListener('input', () => {
      const term = input.value.trim().toLowerCase();
      table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
      });
    });
  });

  // ---- Pagination buttons (visual only — no real backend paging in this demo) ----
  document.querySelectorAll('.pagination').forEach(pagination => {
    pagination.addEventListener('click', (e) => {
      const btn = e.target.closest('button');
      if (!btn || btn.classList.contains('nav-btn')) return;
      pagination.querySelectorAll('button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  // ---- Attachments dropzone (new transaction page) ----
  const dropzone = document.querySelector('.dropzone');
  if (dropzone) {
    const fileInput = document.createElement('input');
    fileInput.type = 'file';
    fileInput.multiple = true;
    fileInput.style.display = 'none';
    dropzone.appendChild(fileInput);

    dropzone.addEventListener('click', () => fileInput.click());
    dropzone.addEventListener('dragover', (e) => { e.preventDefault(); dropzone.style.background = '#eef1fd'; });
    dropzone.addEventListener('dragleave', () => { dropzone.style.background = ''; });
    dropzone.addEventListener('drop', (e) => {
      e.preventDefault();
      dropzone.style.background = '';
      try { fileInput.files = e.dataTransfer.files; } catch (_) { }
      updateDropzoneLabel(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', () => updateDropzoneLabel(fileInput.files));

    function updateDropzoneLabel(files) {
      if (files && files.length) {
        dropzone.querySelector('span')?.remove();
        dropzone.firstChild.textContent = files.length === 1
          ? L('تم اختيار ملف: :name').replace(':name', files[0].name)
          : L('تم اختيار :n ملفات').replace(':n', files.length);
      }
    }
  }

});
