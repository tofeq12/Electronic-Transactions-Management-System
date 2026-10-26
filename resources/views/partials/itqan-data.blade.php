{{--
  Injected before js/data.js on every page that talks to the backend:
  - window.ITQAN_DATA: the server dataset consumed by the existing frontend
  - window.ITQAN_CSRF: token for the fetch() writes (API helper below)
  - window.API: tiny fetch wrapper implementing the JSON contract
    {success, message, data, errors}
  - sidebar notification dot reflects the real unread count
--}}
<script>
  window.ITQAN_DATA = @json($itqan ?? []);
  window.ITQAN_CSRF = @json(csrf_token());
  window.ITQAN_UNREAD = (window.ITQAN_DATA && window.ITQAN_DATA.unread) || 0;
  window.ITQAN_LANG = @json(app()->getLocale());
  window.ITQAN_T = @json(\App\Support\I18n::jsMap());
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
