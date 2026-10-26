<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->isLocale('en') ? 'ltr' : 'rtl'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | <?php echo e(__('غير مصرح')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <?php if(app()->isLocale('en')): ?><link rel="stylesheet" href="<?php echo e(asset('css/ltr.css')); ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/components.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/ui.css')); ?>">
</head>

<body data-page="403">
  <main class="nf404">
    <div class="code">403</div>
    <h1><?php echo e(__('غير مصرح لك بالدخول')); ?></h1>
    <p><?php echo e(__('ليست لديك صلاحية لعرض هذه الصفحة أو تنفيذ هذا الإجراء. إن كنت تحتاجها فتواصل مع مدير النظام.')); ?></p>
    <a class="btn btn-primary" href="/dashboard"><?php echo e(__('العودة إلى الرئيسية')); ?></a>
  </main>
</body>

</html><?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/errors/403.blade.php ENDPATH**/ ?>