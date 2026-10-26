<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('إنشاء حساب جديد') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
</head>

<body data-page="register">
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
          <h1 class="ax-title">{{ __('إنشاء حساب جديد') }}</h1>
          <p class="ax-foot" style="text-align:center;margin:0 0 14px" role="status">{{ __('تُنشأ الحسابات الجديدة بحالة غير نشطة حتى يفعّلها المسؤول، ثم يمكن تسجيل الدخول.') }}</p>
          @if (session('registered'))
            <p class="ax-foot" style="text-align:center;margin:0 0 14px;color:#1a7f4b;font-weight:700" role="status">{{ __('تم إنشاء حسابك بنجاح — بانتظار تفعيل المسؤول.') }}</p>
          @endif
          @if ($errors->any())
            <p class="ax-foot" style="text-align:center;margin:0 0 14px;color:#b42318;font-weight:700" role="alert">{{ __('يرجى تصحيح الحقول المميزة بالأحمر.') }}</p>
          @endif
          <form id="register-form" method="POST" action="{{ route('register.store') }}" novalidate>
            @csrf
            <div class="ax-field"><label class="ax-sr" for="reg-username">{{ __('اسم المستخدم') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                  <circle cx="12" cy="7" r="4" />
                </svg><input type="text" id="reg-username" name="username" placeholder="{{ __('اسم المستخدم') }}"
                  autocomplete="username" dir="ltr" value="{{ old('username') }}" data-rule="username" required></div>
              <div class="ax-error" role="alert">@error('username'){{ $message }}@enderror</div>
            </div>
            <div class="ax-field"><label class="ax-sr" for="reg-email">{{ __('البريد الإلكتروني') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z" />
                  <path d="M22 6l-10 7L2 6" />
                </svg><input type="email" id="reg-email" name="email" placeholder="{{ __('البريد الإلكتروني') }}"
                  autocomplete="email" dir="ltr" value="{{ old('email') }}" data-rule="email" required></div>
              <div class="ax-error" role="alert">@error('email'){{ $message }}@enderror</div>
            </div>
            <div class="ax-field"><label class="ax-sr" for="phone">{{ __('رقم الهاتف') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <rect x="7" y="2" width="10" height="20" rx="2" />
                  <path d="M11 18h2" />
                </svg><input type="tel" id="phone" name="phone" placeholder="{{ __('رقم الهاتف') }}" autocomplete="tel"
                  value="{{ old('phone') }}" data-rule="phone" required></div>
              <div class="ax-error" role="alert">@error('phone'){{ $message }}@enderror</div>
            </div>
            <div class="ax-field"><label class="ax-sr" for="reg-password">{{ __('كلمة المرور') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <rect x="4" y="11" width="16" height="10" rx="2" />
                  <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg><input type="password" id="reg-password" name="password" placeholder="{{ __('كلمة المرور') }}"
                  autocomplete="new-password" data-rule="newpassword" required><button type="button" class="ax-toggle"
                  aria-label="{{ __('إظهار كلمة المرور') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg></button></div>
              <div class="ax-error" role="alert">@error('password'){{ $message }}@enderror</div>
            </div>
            <div class="ax-field"><label class="ax-sr" for="reg-confirm">{{ __('تأكيد كلمة المرور') }}</label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <rect x="4" y="11" width="16" height="10" rx="2" />
                  <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg><input type="password" id="reg-confirm" name="password_confirmation" placeholder="{{ __('تأكيد كلمة المرور') }}"
                  autocomplete="new-password" data-rule="confirm" required><button type="button" class="ax-toggle"
                  aria-label="{{ __('إظهار كلمة المرور') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg></button></div>
              <div class="ax-error" role="alert">@error('password_confirmation'){{ $message }}@enderror</div>
            </div>
            <button type="submit" class="ax-submit">{{ __('إنشاء حساب') }}</button>
          </form>
          <p class="ax-foot">{{ __('لديك حساب؟') }} <a href="/login" class="ax-link" data-ax-link>{{ __('تسجيل الدخول') }}</a></p>
        </div>
      </div>
      <div class="ax-scene-col">
        <div class="ax-scene" data-ax-scene aria-hidden="true"></div>
      </div>
    </main>
    <footer class="ax-copy">{{ __('جميع الحقوق محفوظة') }} <span data-year>2026</span></footer>
  </div>
  @include('partials.itqan-data')
  <script src="{{ asset('js/main.js') }}"></script>
  <script src="{{ asset('js/motion.js') }}"></script>
  <script src="{{ asset('js/auth-scene.js') }}"></script>
  <script src="{{ asset('js/auth.js') }}"></script>
</body>

</html>
