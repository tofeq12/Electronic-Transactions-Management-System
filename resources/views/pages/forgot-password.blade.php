<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('استعادة كلمة المرور') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
  <link rel="stylesheet" href="{{ asset('css/features.css') }}">
</head>

<body data-page="forgot">
  <div class="ax-page">
    <svg class="ax-waves" viewBox="0 0 1440 900" preserveAspectRatio="none" aria-hidden="true">
      <path d="M0 640C240 560 420 760 720 680s520-160 720-60" fill="none" stroke="#c9d4ff" stroke-width="1.5" />
      <path d="M0 700C260 620 460 820 760 740s500-140 680-40" fill="none" stroke="#d6defe" stroke-width="1.5" />
    </svg>
<main class="ax-main">
      <div class="ax-card-col">
        <div class="ax-card">
          <svg class="ax-logo" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"
            stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 2.5 3 8h18z" fill="currentColor" fill-opacity=".15" />
            <path d="M3 21.5h18M5.5 18v-7.5M9.5 18v-7.5M14.5 18v-7.5M18.5 18v-7.5M4 18h16" />
          </svg>
          <div class="ax-system">{{ __('نظام إدارة المعاملات الإلكترونية الذكي') }}</div>
          <h1 class="ax-title">{{ __('استعادة كلمة المرور') }}</h1>
          <p class="ax-desc">{{ __('أدخل بريدك الإلكتروني المسجّل وسنرسل إليك رمز التحقق لإعادة تعيين كلمة المرور.') }}</p>
          <form id="forgot-form" novalidate>
            <div class="ax-field"><label class="ax-sr" for="fp-email">{{ __('البريد الإلكتروني') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" />
                  <path d="M22 6l-10 7L2 6" />
                </svg><input type="text" id="fp-email" name="email" inputmode="email" placeholder="{{ __('البريد الإلكتروني') }}"
                  autocomplete="email" required></div>
              <div class="ax-error" role="alert"></div>
            </div>
            <button type="submit" class="ax-submit">{{ __('إرسال رمز التحقق') }}</button>
          </form>
          <p class="ax-foot"><a href="/login" class="ax-link" data-ax-link>{{ __('العودة إلى تسجيل الدخول') }}</a></p>
        </div>
      </div>
      <div class="ax-scene-col">
        <div class="ax-scene" data-ax-scene aria-hidden="true"></div>
      </div>
    </main>
    <footer class="ax-copy">{{ __('جميع الحقوق محفوظة ©') }} <span data-year>2026</span></footer>
  </div>
  @include('partials.itqan-data')
  <script src="{{ asset('js/main.js') }}"></script>
  <script src="{{ asset('js/motion.js') }}"></script>
  <script src="{{ asset('js/auth-scene.js') }}"></script>
  <script src="{{ asset('js/auth.js') }}"></script>
  <script src="{{ asset('js/forgot.js') }}"></script>
</body>

</html>