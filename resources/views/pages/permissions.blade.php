<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('الصلاحيات') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
  <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
  <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
  <link rel="stylesheet" href="{{ asset('css/features.css') }}">
</head>

<body data-page="roles">

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
              d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 0 1-4 0v-.09A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 0 1 0-4h.09A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06A2 2 0 1 1 7.03 4.24l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 0 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87 1.7 1.7 0 0 0 1.55 1H21a2 2 0 0 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1z" />
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

    <main class="main-area">
      <div class="topbar">
        <h1>{{ __('الصلاحيات') }}</h1>
        <div class="topbar-actions">
          <div class="search-input">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="11" cy="11" r="7" />
              <path d="M21 21l-4-4" />
            </svg>
            <input type="text" placeholder="{{ __('بحث عن صلاحية...') }}" data-table-search="#perm-table">
          </div>
          <button class="btn btn-outline" type="button" data-goto="/roles">{{ __('الأدوار') }}</button>
        </div>
      </div>

      <div class="panel">
        <div class="panel-scroll">
          <table id="perm-table">
            <thead>
              <tr>
                <th>#</th>
                <th>{{ __('الصلاحية') }}</th>
                <th>{{ __('المجموعة') }}</th>
                <th>{{ __('الأدوار المستخدمة') }}</th>
                <th>{{ __('الإجراءات') }}</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        {{-- إضافة صلاحية مخصصة (نفس نمط نموذج الكلية في صفحة الأقسام). --}}
        <form id="perm-form" class="college-add">
          <input type="text" id="perm-name" maxlength="50" pattern="[a-z][a-z0-9_.]*"
            placeholder="{{ __('اسم الصلاحية (مثال: reports.export)') }}" required>
          <button class="btn btn-primary btn-sm" type="submit">{{ __('إضافة صلاحية') }}</button>
        </form>
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
  <script src="{{ asset('js/activity.js') }}"></script>
  <script src="{{ asset('js/avatar.js') }}"></script>
  <script src="{{ asset('js/roles.js') }}"></script>
  <script src="{{ asset('js/permissions-page.js') }}"></script>
</body>

</html>
