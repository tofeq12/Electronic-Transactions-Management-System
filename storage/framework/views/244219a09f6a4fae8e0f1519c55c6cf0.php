<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->isLocale('en') ? 'ltr' : 'rtl'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | <?php echo e(__('الصفحة غير موجودة')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <?php if(app()->isLocale('en')): ?><link rel="stylesheet" href="<?php echo e(asset('css/ltr.css')); ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/components.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/ui.css')); ?>">
</head>

<body data-page="404">
  <main class="nf404">
    <div class="code">404</div>
    <h1><?php echo e(__('الصفحة غير موجودة')); ?></h1>
    <p><?php echo e(__('الرابط الذي طلبته غير صحيح أو تم نقله.')); ?></p>
    <a class="btn btn-primary" href="/dashboard"><?php echo e(__('العودة إلى الرئيسية')); ?></a>
  </main>
</body>

</html><?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/errors/404.blade.php ENDPATH**/ ?>