<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('إضافة مستخدم') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
  <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
  <link rel="stylesheet" href="{{ asset('css/features.css') }}">
</head>

<body data-page="users">

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

    <main class="main-area">
      <div class="panel form-panel">
        <div class="form-panel-header">{{ __('إضافة مستخدم جديد') }}</div>
        <div class="form-panel-body">
          <form id="add-user-form">
            <div class="form-section-title">{{ __('بيانات المستخدم') }}</div>
            <div class="form-grid">
              <div class="field"><label for="u-name">{{ __('الاسم الكامل') }}</label><input type="text" id="u-name" name="name"
                  required></div>
              <div class="field"><label for="u-username">{{ __('اسم المستخدم') }}</label><input type="text" id="u-username"
                  name="username" autocomplete="off" dir="ltr" required></div>
              <div class="field"><label for="u-email">{{ __('البريد الإلكتروني') }}</label><input type="email" id="u-email"
                  name="email" dir="ltr" required></div>
              <div class="field"><label for="u-phone">{{ __('رقم الهاتف') }}</label><input type="tel" id="u-phone" name="phone"
                  dir="ltr" inputmode="tel" autocomplete="off" required></div>
              <div class="field"><label for="u-pass">{{ __('كلمة المرور') }}</label><input type="password" id="u-pass"
                  name="password" autocomplete="new-password" required minlength="8"></div>
              <div class="field"><label for="u-role">{{ __('الدور') }}</label><select id="u-role" name="role">
                  {{-- قيمة الخيار تبقى التسمية العربية (تُرسل كما هي ويحوّلها الخادم إلى slug) --}}
                  <option value="مدير">{{ __('مدير') }}</option>
                  <option value="موظف">{{ __('موظف') }}</option>
                  <option value="مراجع">{{ __('مراجع') }}</option>
                </select></div>
              <div class="field"><label for="u-status">{{ __('الحالة') }}</label><select id="u-status" name="status">
                  <option value="active">{{ __('نشط') }}</option>
                  <option value="inactive">{{ __('غير نشط') }}</option>
                </select></div>
              <div class="field"><label for="u-college">{{ __('الكلية') }}</label><select id="u-college" name="college_id">
                  <option value="">{{ __('— بدون كلية —') }}</option>
                </select></div>
              <div class="field"><label for="u-dept">{{ __('القسم') }}</label><select id="u-dept" name="department_id">
                  <option value="">{{ __('— بدون قسم —') }}</option>
                </select></div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary">{{ __('حفظ') }}</button>
              <button type="button" class="btn btn-outline" data-goto="/dashboard/users">{{ __('إلغاء') }}</button>
            </div>
          </form>
        </div>
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
</body>

</html>