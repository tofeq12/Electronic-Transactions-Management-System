<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('نظام إدارة المعاملات الإلكترونية') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
</head>

<body data-page="splash1">

  <div class="splash">
    <header class="splash-header">
<div class="brand">
        <div class="brand-text">
          <div class="brand-name">إتقان</div>
          <div class="brand-sub">{{ __('منصة المعاملات') }}</div>
        </div>
        <div class="brand-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z" />
            <path d="M9 12l2 2 4-4" />
          </svg>
        </div>
      </div>
    </header>

    <main class="splash-body">
      <div class="hero-icon-wrap">
        <div class="hero-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z" />
            <path d="M9 12l2 2 4-4" />
          </svg>
        </div>
        <span class="hero-dot"></span>
        <span class="hero-chip chip-lock">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="5" y="11" width="14" height="9" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
          </svg>
        </span>
        <span class="hero-chip chip-doc">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M7 3h7l4 4v14H7z" />
            <path d="M11 3v5h5" />
          </svg>
        </span>
      </div>

      <div class="eyebrow-badge">{{ __('منصة رقمية موثوقة') }}</div>
      <h1>{{ __('نظام إدارة المعاملات') }}<br>{{ __('الإلكترونية') }}</h1>
      <p class="subtitle">{{ __('إدارة أذكى لمعاملاتك اليومية') }}</p>

      <button class="btn btn-primary" type="button" data-goto="/splash2">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M19 12H5M11 6l-6 6 6 6" />
        </svg>
        {{ __('اكتشف المنصة') }}
      </button>
    </main>

    <footer class="splash-footer">
      <div class="step-track">
        <span>02 / 01</span>
        <span class="step-bar"><span style="width:50%"></span></span>
      </div>
      <span>{{ __('إتقان - حلول رقمية موثوقة') }}</span>
    </footer>
  </div>

  <script src="{{ asset('js/main.js') }}"></script>
  <script src="{{ asset('js/motion.js') }}"></script>
  <script src="{{ asset('js/navigation.js') }}"></script>
</body>

</html>