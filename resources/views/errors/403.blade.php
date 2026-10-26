<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->isLocale('en') ? 'ltr' : 'rtl' }}">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | {{ __('غير مصرح') }}</title>
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  @if(app()->isLocale('en'))<link rel="stylesheet" href="{{ asset('css/ltr.css') }}">@endif
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/ui.css') }}">
</head>

<body data-page="403">
  <main class="nf404">
    <div class="code">403</div>
    <h1>{{ __('غير مصرح لك بالدخول') }}</h1>
    <p>{{ __('ليست لديك صلاحية لعرض هذه الصفحة أو تنفيذ هذا الإجراء. إن كنت تحتاجها فتواصل مع مدير النظام.') }}</p>
    <a class="btn btn-primary" href="/dashboard">{{ __('العودة إلى الرئيسية') }}</a>
  </main>
</body>

</html>