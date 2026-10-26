<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('الصفحة غير موجودة') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
</head>

<body data-page="404">
  <main class="nf404">
    <div class="code">404</div>
    <h1>{{ __('الصفحة غير موجودة') }}</h1>
    <p>{{ __('الرابط الذي طلبته غير صحيح أو تم نقله.') }}</p>
    <a class="btn btn-primary" href="/dashboard">{{ __('العودة إلى الرئيسية') }}</a>
  </main>
</body>

</html>