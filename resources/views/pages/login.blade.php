<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('تسجيل الدخول') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
</head>

<body data-page="login">
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
          <h1 class="ax-title">{{ __('تسجيل الدخول') }}</h1>
          @if (session('registered'))
            <p class="ax-foot" style="text-align:center;margin:0 0 14px;color:#1a7f4b;font-weight:700" role="status">{{ __('تم إنشاء حسابك بنجاح — بانتظار تفعيل المسؤول، ثم يمكنك تسجيل الدخول.') }}</p>
          @endif
          @if (session('password_reset'))
            <p class="ax-foot" style="text-align:center;margin:0 0 14px;color:#1a7f4b;font-weight:700" role="status">{{ __('تم إعادة تعيين كلمة المرور — يمكنك تسجيل الدخول الآن.') }}</p>
          @endif
          <form id="login-form" method="POST" action="{{ route('login') }}" novalidate>
            @csrf
            <div class="ax-field"><label class="ax-sr" for="email">{{ __('اسم المستخدم أو البريد الإلكتروني') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg><input type="text" id="email" name="email" placeholder="{{ __('اسم المستخدم أو البريد الإلكتروني') }}"
                  autocomplete="username" data-rule="user" readonly required></div>
              <div class="ax-error" role="alert"></div>
            </div>
            <div class="ax-field"><label class="ax-sr" for="password">{{ __('كلمة المرور') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <rect x="4" y="11" width="16" height="10" rx="2" />
                  <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg><input type="password" id="password" name="password" placeholder="{{ __('كلمة المرور') }}"
                  autocomplete="current-password" data-rule="loginpass" readonly required><button type="button" class="ax-toggle"
                  aria-label="{{ __('إظهار كلمة المرور') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg></button></div>
              <div class="ax-error" role="alert"></div>
            </div>
            <div class="ax-row"><label class="ax-check"><input type="checkbox" name="remember" checked> {{ __('تذكرني') }}</label><a
                href="/forgot-password" class="ax-link" data-ax-link>{{ __('نسيت كلمة المرور؟') }}</a></div>
            <button type="submit" class="ax-submit">{{ __('تسجيل الدخول') }}</button>
          </form>
          <p class="ax-foot">{{ __('ليس لديك حساب؟') }} <a href="/register" class="ax-link" data-ax-link>{{ __('إنشاء حساب جديد') }}</a></p>
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
  <script>
    // Server-side validation errors (native form fallback / wrong credentials).
    document.addEventListener('DOMContentLoaded', function () {
      var show = function (idx, messages) {
        if (!messages || !messages.length) return;
        var fields = document.querySelectorAll('#login-form .ax-field');
        if (!fields[idx]) return;
        fields[idx].classList.add('has-error');
        fields[idx].querySelector('.ax-error').textContent = messages[0];
      };
      show(0, @json($errors->get('email')));
      show(1, @json($errors->get('password')));
    });
  </script>
  <script>
    // منع التعبئة الصامتة: الحقلان يبدآن readonly فتُرفض أي تعبئة صامتة
    // من المتصفّح عند التحميل، وتفتح فور أول تفاعل (نقر/كتابة/تبويب) —
    // أداة حفظ كلمات المرور تبقى تعمل: رموز autocomplete موجودة وقائمة
    // الحفظ تظهر عند النقر في الحقل.
    ['email', 'password'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      ['focus', 'pointerdown', 'keydown', 'paste'].forEach(function (ev) {
        el.addEventListener(ev, function () { el.removeAttribute('readonly'); });
      });
    });
  </script>
  <script src="{{ asset('js/auth.js') }}"></script>
</body>

</html>
