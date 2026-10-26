<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->isLocale('en') ? 'ltr' : 'rtl'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | <?php echo e(__('مرحباً بك')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <?php if(app()->isLocale('en')): ?><link rel="stylesheet" href="<?php echo e(asset('css/ltr.css')); ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/components.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/responsive.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/motion.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/animations.css')); ?>">
</head>

<body data-page="onboarding">
  <div class="ob-page">
    <div class="ob-bg" aria-hidden="true"></div>
    <header class="ob-top">
      <span class="ob-step">01 / 04</span>
      <button type="button" class="ob-skip" data-goto="/auth-choice"><?php echo e(__('تخطي')); ?></button>
    </header>
    <main class="ob-stage" id="ob-stage"></main>
  </div>
  <?php echo $__env->make('partials.itqan-data', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <script src="<?php echo e(asset('js/main.js')); ?>"></script>
  <script src="<?php echo e(asset('js/motion.js')); ?>"></script>
  <script src="<?php echo e(asset('js/navigation.js')); ?>"></script>
  <script src="<?php echo e(asset('js/onboarding.js')); ?>"></script>
</body>

</html><?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/pages/onboarding.blade.php ENDPATH**/ ?>