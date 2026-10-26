<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->isLocale('en') ? 'ltr' : 'rtl'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | <?php echo e(__('كيف تريد المتابعة؟')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <?php if(app()->isLocale('en')): ?><link rel="stylesheet" href="<?php echo e(asset('css/ltr.css')); ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/components.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/responsive.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/motion.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/animations.css')); ?>">
</head>

<body data-page="auth-choice">

  <div class="simple-page">
    <header class="simple-header">
      <div class="brand">
        <div class="brand-text">
          <div class="brand-name">إتقان</div>
          <div class="brand-sub"><?php echo e(__('منصة المعاملات')); ?></div>
        </div>
        <div class="brand-badge">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z" />
            <path d="M9 12l2 2 4-4" />
          </svg>
        </div>
      </div>
    </header>

    <main class="simple-body">
      <div class="round-icon green">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M12 3l7 3v6c0 4.5-3 8-7 9-4-1-7-4.5-7-9V6l7-3z" />
          <path d="M9 12l2 2 4-4" />
        </svg>
      </div>
      <div class="step-label">02 / 02</div>
      <h1><?php echo e(__('كيف تريد المتابعة؟')); ?></h1>
      <p class="subtitle"><?php echo e(__('اختر الطريقة المناسبة للوصول إلى نظام المعاملات')); ?></p>

      <div class="choice-list">
        <div class="choice-row primary" data-goto="/login">
          <span class="cr-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </span>
          <div class="cr-main">
            <span class="cr-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4" />
                <path d="M10 17l5-5-5-5" />
                <path d="M15 12H3" />
              </svg>
            </span>
            <div>
              <div class="cr-title"><?php echo e(__('تسجيل الدخول')); ?></div>
              <div class="cr-desc"><?php echo e(__('لديك حساب بالفعل')); ?></div>
            </div>
          </div>
        </div>

        <div class="choice-row success" data-goto="/register">
          <span class="cr-arrow">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M15 18l-6-6 6-6" />
            </svg>
          </span>
          <div class="cr-main">
            <span class="cr-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M20 8v6M23 11h-6" />
              </svg>
            </span>
            <div>
              <div class="cr-title"><?php echo e(__('إنشاء الحساب')); ?></div>
              <div class="cr-desc"><?php echo e(__('ابدأ حساباً جديداً')); ?></div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>

  <script src="<?php echo e(asset('js/main.js')); ?>"></script>
  <script src="<?php echo e(asset('js/motion.js')); ?>"></script>
  <script src="<?php echo e(asset('js/navigation.js')); ?>"></script>
</body>

</html><?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/pages/auth-choice.blade.php ENDPATH**/ ?>