<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('خطأ في الخادم') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
</head>

<body data-page="500">
  <main class="nf404">
    <div class="code">500</div>
    <h1>{{ __('حدث خطأ غير متوقع') }}</h1>
    <p>{{ __('تعذّر إتمام طلبك الآن. حاول مرة أخرى بعد قليل، وإن استمرت المشكلة فأبلغ مدير النظام.') }}</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center"><button class="btn btn-outline"
        type="button" onclick="location.reload()">{{ __('إعادة المحاولة') }}</button><a class="btn btn-primary"
        href="/dashboard">{{ __('العودة إلى الرئيسية') }}</a></div>
  </main>
</body>

</html>