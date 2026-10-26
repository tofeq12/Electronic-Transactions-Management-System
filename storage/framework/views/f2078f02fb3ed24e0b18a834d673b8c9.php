<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->isLocale('en') ? 'ltr' : 'rtl'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | <?php echo e(__('خطأ في الخادم')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <?php if(app()->isLocale('en')): ?><link rel="stylesheet" href="<?php echo e(asset('css/ltr.css')); ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/components.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/ui.css')); ?>">
</head>

<body data-page="500">
  <main class="nf404">
    <div class="code">500</div>
    <h1><?php echo e(__('حدث خطأ غير متوقع')); ?></h1>
    <p><?php echo e(__('تعذّر إتمام طلبك الآن. حاول مرة أخرى بعد قليل، وإن استمرت المشكلة فأبلغ مدير النظام.')); ?></p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center"><button class="btn btn-outline"
        type="button" onclick="location.reload()"><?php echo e(__('إعادة المحاولة')); ?></button><a class="btn btn-primary"
        href="/dashboard"><?php echo e(__('العودة إلى الرئيسية')); ?></a></div>
  </main>
</body>

</html><?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/errors/500.blade.php ENDPATH**/ ?>