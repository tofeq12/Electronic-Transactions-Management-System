<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('مرحباً بك') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/responsive.css') }}">
  <link rel="stylesheet" href="{{ asset('css/motion.css') }}">
  <link rel="stylesheet" href="{{ asset('css/animations.css') }}">
</head>

<body data-page="onboarding">
  <div class="ob-page">
    <div class="ob-bg" aria-hidden="true"></div>
    <header class="ob-top">
      <span class="ob-step">01 / 04</span>
      <button type="button" class="ob-skip" data-goto="/auth-choice">{{ __('تخطي') }}</button>
    </header>
    <main class="ob-stage" id="ob-stage"></main>
  </div>
  @include('partials.itqan-data')
  <script src="{{ asset('js/main.js') }}"></script>
  <script src="{{ asset('js/motion.js') }}"></script>
  <script src="{{ asset('js/navigation.js') }}"></script>
  <script src="{{ asset('js/onboarding.js') }}"></script>
</body>

</html>