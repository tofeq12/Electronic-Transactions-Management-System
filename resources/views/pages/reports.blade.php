<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('التقارير') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
  <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
  <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
  <link rel="stylesheet" href="{{ asset('css/features.css') }}">
</head>

<body data-page="reports">

  <div class="dash-shell">
    <aside class="sidebar">
      <div class="sidebar-brand">{{ __('نظام إدارة المعاملات الإلكترونية') }}</div>
      <div class="sidebar-user">
        <img src="{{ auth()->user()?->avatar_url ?? asset('images/avatar.svg') }}" alt="{{ __('صورة المستخدم') }}">
        <div>
          <div class="su-name">{{ auth()->user()?->name ?? __('غير مسجل') }}</div>
          <div class="su-role">{{ __(\App\Support\Roles::label(auth()->user()?->role)) }}</div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a href="/dashboard" data-page="home">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 11l9-8 9 8" />
            <path d="M5 10v10h14V10" />
          </svg>
          <span>{{ __('الرئيسية') }}</span>
        </a>
        <a href="/transactions" data-page="transactions">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M7 3h7l4 4v14H7z" />
            <path d="M11 3v5h5" />
          </svg>
          <span>{{ __('المعاملات') }}</span>
        </a>
        <a href="/inbox" data-page="inbox">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 12h-6l-2 3h-4l-2-3H2" />
            <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
          </svg>
          <span>{{ __('صندوق الوارد') }}</span>
        </a>
        <a href="/transactions/urgent" data-page="urgent">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
          </svg>
          <span>{{ __('العاجلة والمتأخرة') }}</span>
        </a>
        <a href="/dashboard/users" data-page="users">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          <span>{{ __('المستخدمين') }}</span>
        </a>
        <a href="/departments" data-page="departments">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 10h.01M15 10h.01M9 13h.01M15 13h.01" />
          </svg>
          <span>{{ __('الأقسام') }}</span>
        </a>
<a href="/types" data-page="types">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 6h16M4 12h16M4 18h10" />
          </svg>
          <span>{{ __('أنواع المعاملات') }}</span>
        </a>
        <a href="/reports" data-page="reports">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4v16h16" />
            <path d="M8 15l3-4 3 3 4-6" />
          </svg>
          <span>{{ __('التقارير') }}</span>
        </a>
        <a href="/activity-log" data-page="activity">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z" />
            <path d="M8 6H6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2" />
            <path d="M9 12h6M9 16h4" />
          </svg>
          <span>{{ __('سجل النشاطات') }}</span>
        </a>
        <a href="/notifications" data-page="notifications">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.7 21a2 2 0 0 1-3.4 0" />
          </svg>
          <span>{{ __('الإشعارات') }}</span><i class="nav-dot"></i>
        </a>
        @can('roles.manage')
        <a href="/roles" data-page="roles">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="M9 12l2 2 4-4" />
          </svg>
          <span>{{ __('الأدوار والصلاحيات') }}</span>
        </a>
        @endcan
        <a href="/settings" data-page="settings">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3" />
            <path
              d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 0 1-4 0v-.09A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 0 1 0-4h.09A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06A2 2 0 1 1 7.03 4.24l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 0 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.55 1H21a2 2 0 0 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1z" />
          </svg>
          <span>{{ __('الإعدادات') }}</span>
        </a>
      </nav>

      <form method="POST" action="/logout" class="sidebar-logout-form">
        @csrf
        <button type="submit" class="sidebar-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <path d="M16 17l5-5-5-5" />
          <path d="M21 12H9" />
        </svg>
        <span>{{ __('تسجيل الخروج') }}</span>
      </button>
      </form>
    </aside>

    <main class="main-area rp-main">
      <div class="topbar">
        <h1>{{ __('التقارير') }}</h1>
      </div>
      <p class="rp-print-meta" id="rp-print-meta"></p>

      <section class="rp-filters" aria-label="{{ __('فلاتر التقرير') }}">
        <div class="rp-filter">
          <label for="rp-type">{{ __('نوع التقرير') }}</label>
          <div class="rp-control">
            <select id="rp-type">
              <option value="transactions" selected>{{ __('تقرير المعاملات') }}</option>
              <option value="monthly">{{ __('تقرير شهري') }}</option>
              <option value="yearly">{{ __('تقرير سنوي') }}</option>
            </select>
            <svg class="rp-control__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M6 9l6 6 6-6" />
            </svg>
          </div>
        </div>
        <div class="rp-filter">
          <label for="rp-from">{{ __('من تاريخ') }}</label>
          {{-- بلا تاريخ افتراضي: التقرير يعرض كل الفترات (أرقام حقيقية فور الدخول). --}}
          <div class="rp-control rp-control--date"><input type="date" id="rp-from"><span
              class="rp-control__value"></span><svg class="rp-control__icon" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="4" y="5" width="16" height="16" rx="3" />
              <path d="M8 3v4M16 3v4M4 11h16" />
            </svg></div>
        </div>
        <div class="rp-filter">
          <label for="rp-to">{{ __('إلى تاريخ') }}</label>
          <div class="rp-control rp-control--date"><input type="date" id="rp-to"><span
              class="rp-control__value"></span><svg class="rp-control__icon" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <rect x="4" y="5" width="16" height="16" rx="3" />
              <path d="M8 3v4M16 3v4M4 11h16" />
            </svg></div>
        </div>
      </section>
      <p class="rp-error" id="rp-error" role="alert" hidden>{{ __('تاريخ البداية يجب أن يكون قبل تاريخ النهاية.') }}</p>

      <section class="rp-stats" aria-label="{{ __('ملخص الإحصائيات') }}">
        {{-- ست بطاقات تغطي الحالات الخمس كاملة: المكتملة + المنتظرة +
             المعادة + الموثقة + المرفوضة = الإجمالي (تحقق مباشر من الجدول). --}}
        <article class="rp-stat rp-stat--blue" data-stat="total">
          <span class="rp-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M7 3h7l4 4v14H7z" />
              <path d="M14 3v5h4M10 13h5M10 17h5" />
            </svg></span>
          <div class="rp-stat__body"><span class="rp-stat__label">{{ __('إجمالي المعاملات') }}</span><strong
              class="rp-stat__value">0</strong></div>
        </article>
        <article class="rp-stat rp-stat--green" data-stat="approved">
          <span class="rp-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z" />
              <path d="M8.5 12l2.5 2.5 4.5-5" />
            </svg></span>
          <div class="rp-stat__body"><span class="rp-stat__label">{{ __('المكتملة') }}</span><strong
              class="rp-stat__value">0</strong></div>
        </article>
        <article class="rp-stat rp-stat--orange" data-stat="pending">
          <span class="rp-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3 2" />
            </svg></span>
          <div class="rp-stat__body"><span class="rp-stat__label">{{ __('قيد الانتظار') }}</span><strong
              class="rp-stat__value">0</strong></div>
        </article>
        <article class="rp-stat rp-stat--red" data-stat="rejected">
          <span class="rp-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z" />
              <path d="M9.5 9.5l5 5M14.5 9.5l-5 5" />
            </svg></span>
          <div class="rp-stat__body"><span class="rp-stat__label">{{ __('المرفوضة') }}</span><strong
              class="rp-stat__value">0</strong></div>
        </article>
        <article class="rp-stat rp-stat--purple" data-stat="returned">
          <span class="rp-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M9 14L4 9l5-5" />
              <path d="M4 9h10a6 6 0 0 1 0 12h-3" />
            </svg></span>
          <div class="rp-stat__body"><span class="rp-stat__label">{{ __('معادة للاكمال') }}</span><strong
              class="rp-stat__value">0</strong></div>
        </article>
        <article class="rp-stat rp-stat--gray" data-stat="draft">
          <span class="rp-stat__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
              stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M7 3h7l4 4v14H7z" />
              <path d="M14 3v5h4" />
            </svg></span>
          <div class="rp-stat__body"><span class="rp-stat__label">{{ __('مسودة') }}</span><strong
              class="rp-stat__value">0</strong></div>
        </article>
      </section>

      <section class="rp-chart" aria-labelledby="rp-chart-title">
        <h2 class="rp-chart__title" id="rp-chart-title">{{ __('إحصائيات المعاملات') }}</h2>
        <div class="rp-chart__body" id="rp-chart"></div>
      </section>

      <div class="rp-actions">
        <button type="button" class="rp-btn" id="rp-view"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z" />
            <circle cx="12" cy="12" r="3" />
          </svg><span>{{ __('عرض التقرير') }}</span></button>
        <button type="button" class="rp-btn rp-btn--pdf" id="rp-pdf"><svg viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
          </svg><span>{{ __('تصدير PDF') }}</span></button>
        {{-- Excel = CSV بترميز BOM يفتحه Excel مباشرة بنفس صفوف الأرقام
             (نطاق الرؤية + الفترة) — زر واحد لكل حاجة حقيقية بلا تكرار. --}}
        <button type="button" class="rp-btn rp-btn--excel" id="rp-excel"><svg viewBox="0 0 24 24" fill="none"
            stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
          </svg><span>{{ __('تصدير Excel') }}</span></button>
      </div>
    </main>
  </div>

  <script src="{{ asset('js/main.js') }}"></script>
  <script src="{{ asset('js/motion.js') }}"></script>
  <script src="{{ asset('js/navigation.js') }}"></script>
  <script src="{{ asset('js/shell.js') }}"></script>
  @include('partials.itqan-data')
  <script src="{{ asset('js/data.js') }}"></script>
  <script src="{{ asset('js/ui.js') }}"></script>
  <script src="{{ asset('js/tables.js') }}"></script>
  <script src="{{ asset('js/forms.js') }}"></script>
  <script src="{{ asset('js/dashboard.js') }}"></script>
  <script src="{{ asset('js/departments.js') }}"></script>
  <script src="{{ asset('js/activity.js') }}"></script>
  <script src="{{ asset('js/avatar.js') }}"></script>
  <script src="{{ asset('js/reports.js') }}"></script>
</body>

</html>