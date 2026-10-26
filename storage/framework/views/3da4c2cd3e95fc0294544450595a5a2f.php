
<script>
  window.ITQAN_DATA = <?php echo json_encode($itqan ?? [], 15, 512) ?>;
  window.ITQAN_CSRF = <?php echo json_encode(csrf_token(), 15, 512) ?>;
  window.ITQAN_UNREAD = (window.ITQAN_DATA && window.ITQAN_DATA.unread) || 0;
  window.ITQAN_LANG = <?php echo json_encode(app()->getLocale(), 15, 512) ?>;
  window.ITQAN_T = <?php echo json_encode(\App\Support\I18n::jsMap(), 15, 512) ?>;
  window.API = (function () {
    const base = {
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': window.ITQAN_CSRF || ''
    };
    async function request(url, options) {
      options = options || {};
      const headers = Object.assign({}, base, options.headers || {});
      let body = options.body;
      if (body && typeof body === 'object' && !(body instanceof FormData)) {
        if (!headers['Content-Type']) headers['Content-Type'] = 'application/json';
        body = JSON.stringify(body);
      }
      const res = await fetch(url, Object.assign({}, options, { headers: headers, body: body }));
      let payload = null;
      try { payload = await res.json(); } catch (e) { /* non-JSON response */ }
      if (!res.ok) {
        const err = new Error((payload && payload.message) || (window.ITQAN && ITQAN.t ? ITQAN.t('تعذر تنفيذ الطلب') : 'تعذر تنفيذ الطلب'));
        err.status = res.status;
        err.errors = (payload && payload.errors) || {};
        throw err;
      }
      return payload;
    }
    return {
      post: (url, body) => request(url, { method: 'POST', body: body }),
      get: (url) => request(url, { method: 'GET' })
    };
  })();
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sidebar-nav .nav-dot').forEach(function (d) {
      d.hidden = !window.ITQAN_UNREAD;
    });
  });
</script>
<?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/partials/itqan-data.blade.php ENDPATH**/ ?>