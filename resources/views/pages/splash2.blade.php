<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('نبذة عن المنصة') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
</head>

<body data-page="splash2">

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

    <main class="splash-columns">
      <div class="feature-cards">
        <div class="feature-card">
          <span class="fc-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 17L7 7M7 7h8M7 7v8" />
            </svg>
          </span>
          <div class="fc-main">
            <span class="fc-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z" />
                <path d="M9 12l2 2 4-4" />
              </svg>
            </span>
            <div>
              <div class="fc-title">{{ __('أمان موثوق') }}</div>
              <div class="fc-desc">{{ __('حماية متقدمة لكل معاملة') }}</div>
            </div>
          </div>
        </div>

        <div class="feature-card">
          <span class="fc-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 17L7 7M7 7h8M7 7v8" />
            </svg>
          </span>
          <div class="fc-main">
            <span class="fc-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M13 2L4 14h6l-1 8 9-12h-6l1-8z" />
              </svg>
            </span>
            <div>
              <div class="fc-title">{{ __('إنجاز أسرع') }}</div>
              <div class="fc-desc">{{ __('تابع أعمالك من مكان واحد') }}</div>
            </div>
          </div>
        </div>

        <div class="feature-card">
          <span class="fc-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M17 17L7 7M7 7h8M7 7v8" />
            </svg>
          </span>
          <div class="fc-main">
            <span class="fc-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M4 4v6h6M20 20v-6h-6" />
                <path d="M5 15a7 7 0 0 0 12.9 2M19 9A7 7 0 0 0 6.1 7" />
              </svg>
            </span>
            <div>
              <div class="fc-title">{{ __('وضوح كامل') }}</div>
              <div class="fc-desc">{{ __('تقارير وحالات محدثة دائماً') }}</div>
            </div>
          </div>
        </div>
      </div>

      <div class="splash-hero-text">
        <div class="eyebrow-badge">{{ __('✦ تجربة مصممة للعمل الحقيقي') }}</div>
        <h1>{{ __('كل معاملاتك تحت') }}<br>{{ __('سيطرة واحدة') }}</h1>
        <p class="subtitle">{{ __('من لوحة واحدة. أنجز، راجع، وتابع كل خطوة بثقة.') }}</p>
        <button class="btn btn-primary" type="button" data-goto="/onboarding">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M19 12H5M11 6l-6 6 6 6" />
          </svg>
          {{ __('ابدأ الآن') }}
        </button>
      </div>
    </main>

    <footer class="splash-footer">
      <div class="step-track">
        <span>02 / 02</span>
        <span class="step-bar"><span style="width:100%"></span></span>
      </div>
      <span>{{ __('إتقان - حلول رقمية موثوقة') }}</span>
    </footer>
  </div>

  <script src="{{ asset('js/main.js') }}"></script>
  <script src="{{ asset('js/motion.js') }}"></script>
  <script src="{{ asset('js/navigation.js') }}"></script>
</body>

</html>