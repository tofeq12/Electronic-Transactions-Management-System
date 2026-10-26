<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('الرئيسية') }}</title>
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

<body data-page="home">

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
      <div class="topbar">
        <h1>{{ __('الرئيسية') }}</h1>
        <div class="topbar-actions">
          <button class="notif-btn" type="button" data-goto="/notifications" aria-label="{{ __('الإشعارات') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
              <path d="M13.7 21a2 2 0 0 1-3.4 0" />
            </svg>
          </button>
        </div>
      </div>

      {{-- تصميم مرجعي: أيقونة مربعة ملوّنة يمينًا + التسمية فوق الرقم الكبير
             (بفاصل آلاف مثل 1,250) — أربع بطاقات: إجمالي/مكتملة/منتظار/مرفوضة. --}}
      <div class="stats-row">
        <div class="stat-card">
          <span class="stat-icon icon-blue">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="7" y="7" width="13" height="13" rx="2" />
              <path d="M4 14V6a2 2 0 0 1 2-2h8" />
            </svg>
          </span>
          <div>
            <div class="stat-label">{{ __('إجمالي المعاملات') }}</div>
            <div class="stat-value">{{ number_format($stats['total']) }}</div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon icon-green">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9" />
              <path d="M8 12l3 3 5-6" />
            </svg>
          </span>
          <div>
            <div class="stat-label">{{ __('المكتملة') }}</div>
            <div class="stat-value">{{ number_format($stats['done']) }}</div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon icon-orange">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9" />
              <path d="M12 7v5l3.5 3.5" />
            </svg>
          </span>
          <div>
            <div class="stat-label">{{ __('قيد الانتظار') }}</div>
            <div class="stat-value">{{ number_format($stats['pending']) }}</div>
          </div>
        </div>
        <div class="stat-card">
          <span class="stat-icon icon-red">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9" />
              <path d="M9 9l6 6M15 9l-6 6" />
            </svg>
          </span>
          <div>
            <div class="stat-label">{{ __('المرفوضة') }}</div>
            <div class="stat-value">{{ number_format($stats['rejected']) }}</div>
          </div>
        </div>
      </div>

      <div class="content-grid">
        <div class="panel">
          <div class="panel-title">{{ __('أخر المعاملات') }}</div>
          <div class="recent-list">
            @php
              $statusMeta = [
                'done' => [__('مكتملة'), 'badge-green'],
                'pending' => [__('قيد الانتظار'), 'badge-orange'],
                'rejected' => [__('مرفوضة'), 'badge-red'],
                'draft' => [__('مسودة'), 'badge-gray'],
                'returned' => [__('معادة للاكمال'), 'badge-blue'],
              ];
            @endphp
            @forelse($recent as $recentTrx)
              <div class="recent-item" data-href="/transactions/details?id={{ $recentTrx->transaction_number }}">
                <div class="recent-item-top">
                  <span class="ri-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3h7l4 4v14H7z" /><path d="M14 3v5h5" /></svg>
                  </span>
                  <div class="ri-main">
                    <div class="ri-title">{{ $recentTrx->subject }}</div>
                    <div class="ri-date">{{ $recentTrx->date?->format('Y/m/d') }} — {{ $recentTrx->transaction_number }}</div>
                  </div>
                </div>
                <span class="badge {{ $statusMeta[$recentTrx->status][1] ?? 'badge-gray' }}">{{ $statusMeta[$recentTrx->status][0] ?? $recentTrx->status }}</span>
              </div>
            @empty
              <div class="empty-state">
                <div class="es-title">{{ __('لا توجد معاملات لعرضها') }}</div>
                <div class="es-desc">{{ __('ستظهر أحدث المعاملات هنا عند إضافتها.') }}</div>
              </div>
            @endforelse
          </div>
        </div>

        <div class="panel chart-panel">
          <div class="panel-title">{{ __('إحصائيات المعاملات') }}</div>
          <div class="chart-wrap">
            <svg class="chart-svg" viewBox="0 0 600 280" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="chartFade" x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0" stop-color="#3d5fe0" stop-opacity=".35" />
                  <stop offset="1" stop-color="#3d5fe0" stop-opacity="0" />
                </linearGradient>
              </defs>

              @php
                // سلّم ديناميكي حسب أعلى عدّاد
                $chartMax = max(1, ...array_column($chart, 'count'));
                $roughStep = $chartMax / 4;
                $niceSteps = [1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000];
                $chartStep = 1;
                foreach ($niceSteps as $ns) {
                    if ($ns >= $roughStep) { $chartStep = $ns; break; }
                    $chartStep = $ns;
                }
                $chartTop = $chartStep * 4;
                $chartMonths = ['يناير', 'فبراير', 'مارس', 'ابريل', 'مايو', 'يونيو', 'يوليو', 'اغسطس', 'سبتمبر', 'اكتوبر', 'نوفمبر', 'ديسمبر'];

                // نقاط المنحنى: لكل شهر نقطة (أقدم يسارًا — ترتيب زمني في كلا الاتجاهين)
                $slotWidth = 525 / max(count($chart), 1);
                $plotPts = [];
                foreach ($chart as $i => $point) {
                    $plotPts[] = [
                        'x' => round(55 + ($i * $slotWidth) + ($slotWidth / 2), 2),
                        'y' => round(220 - ($point['count'] / $chartTop) * 210, 2),
                        'count' => $point['count'],
                        'month' => $point['month'],
                    ];
                }
                if (!$plotPts) { // حماية: رسم صالح حتى مع مصفوفة فارغة
                    $plotPts[] = ['x' => 317.5, 'y' => 220, 'count' => 0, 'month' => 1];
                }

                // خط دائري ناعم: Catmull-Rom ← بيزييه مكعّب (تحوّل بسيط لكل قطعة)
                $last = count($plotPts) - 1;
                $linePath = 'M ' . $plotPts[0]['x'] . ' ' . $plotPts[0]['y'];
                for ($i = 0; $i < $last; $i++) {
                    $p0 = $plotPts[$i - 1] ?? $plotPts[$i];
                    $p1 = $plotPts[$i];
                    $p2 = $plotPts[$i + 1];
                    $p3 = $plotPts[$i + 2] ?? $plotPts[$i + 1];
                    $c1x = round($p1['x'] + ($p2['x'] - $p0['x']) / 6, 2);
                    $c1y = round($p1['y'] + ($p2['y'] - $p0['y']) / 6, 2);
                    $c2x = round($p2['x'] - ($p3['x'] - $p1['x']) / 6, 2);
                    $c2y = round($p2['y'] - ($p3['y'] - $p1['y']) / 6, 2);
                    $linePath .= ' C ' . $c1x . ' ' . $c1y . ', ' . $c2x . ' ' . $c2y . ', ' . $p2['x'] . ' ' . $p2['y'];
                }
                $areaPath = $linePath . ' L ' . $plotPts[$last]['x'] . ' 220 L ' . $plotPts[0]['x'] . ' 220 Z';
              @endphp

              <!-- grid lines + y-axis -->
              @for ($i = 0; $i <= 4; $i++)
                @php $gridY = 220 - (($chartStep * $i) / $chartTop) * 210; @endphp
                <line class="chart-grid-line" x1="55" y1="{{ $gridY }}" x2="580" y2="{{ $gridY }}" />
                <text class="chart-axis-label" x="40" y="{{ $gridY + 4 }}" text-anchor="end">{{ $chartStep * $i }}</text>
              @endfor

              <!-- التعبئة المتدرجة تحت المنحنى ثم الخط (انميشن رسم تدريجي) -->
              <path class="chart-area" d="{{ $areaPath }}" />
              <path class="chart-line" pathLength="1" d="{{ $linePath }}" />

              <!-- نقاط البيانات + قيمها (تنبثق بترتيب زمني) -->
              @foreach ($plotPts as $i => $plotPt)
                @if ($plotPt['count'] > 0)
                  <circle class="chart-dot" style="--dot-delay: {{ number_format(0.55 + $i * 0.12, 2) }}s" cx="{{ $plotPt['x'] }}" cy="{{ $plotPt['y'] }}" r="5">
                    <title>{{ __($chartMonths[$plotPt['month'] - 1]) }}: {{ $plotPt['count'] }}</title>
                  </circle>
                  <text class="chart-value" style="--dot-delay: {{ number_format(0.55 + $i * 0.12, 2) }}s" x="{{ $plotPt['x'] }}" y="{{ max($plotPt['y'] - 12, 12) }}" text-anchor="middle">{{ $plotPt['count'] }}</text>
                @endif
              @endforeach

              <!-- x-axis labels (أشهر فعلية ديناميكية) -->
              @foreach ($plotPts as $plotPt)
                <text class="chart-axis-label" x="{{ $plotPt['x'] }}" y="245" text-anchor="middle">{{ __($chartMonths[$plotPt['month'] - 1]) }}</text>
              @endforeach
            </svg>
          </div>
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