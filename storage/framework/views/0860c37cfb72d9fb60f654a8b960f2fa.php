<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->isLocale('en') ? 'ltr' : 'rtl'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | <?php echo e(__('كلمة مرور جديدة')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <?php if(app()->isLocale('en')): ?><link rel="stylesheet" href="<?php echo e(asset('css/ltr.css')); ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/auth.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/motion.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/animations.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/features.css')); ?>">
</head>

<body data-page="reset">
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
          <div class="ax-system"><?php echo e(__('نظام إدارة المعاملات الإلكترونية الذكي')); ?></div>
          <h1 class="ax-title"><?php echo e(__('كلمة مرور جديدة')); ?></h1>
          <p class="ax-desc"><?php echo e(__('اختر كلمة مرور قوية: 8 أحرف على الأقل وتجمع بين الأرقام والحروف.')); ?></p>
          <form id="reset-form" novalidate>
            <div class="ax-field"><label class="ax-sr" for="rp-password"><?php echo e(__('كلمة المرور الجديدة')); ?></label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <rect x="4" y="11" width="16" height="10" rx="2" />
                  <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg><input type="password" id="rp-password" name="password" placeholder="<?php echo e(__('كلمة المرور الجديدة')); ?>"
                  autocomplete="new-password" required><button type="button" class="ax-toggle"
                  aria-label="<?php echo e(__('إظهار كلمة المرور')); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg></button></div>
              <div class="ax-error" role="alert"></div>
            </div>
            <div class="ax-field"><label class="ax-sr" for="rp-confirm"><?php echo e(__('تأكيد كلمة المرور')); ?></label>
              <div class="ax-input"><svg class="ax-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                  stroke-width="2">
                  <rect x="4" y="11" width="16" height="10" rx="2" />
                  <path d="M8 11V7a4 4 0 0 1 8 0v4" />
                </svg><input type="password" id="rp-confirm" name="confirm" placeholder="<?php echo e(__('تأكيد كلمة المرور')); ?>"
                  autocomplete="new-password" required><button type="button" class="ax-toggle"
                  aria-label="<?php echo e(__('إظهار كلمة المرور')); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z" />
                    <circle cx="12" cy="12" r="3" />
                  </svg></button></div>
              <div class="ax-error" role="alert"></div>
            </div>
            <button type="submit" class="ax-submit"><?php echo e(__('حفظ كلمة المرور')); ?></button>
          </form>
          <div id="rp-done" class="ax-success" hidden>
            <div class="ok-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
                stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12l5 5 9-10" />
              </svg></div>
            <h2><?php echo e(__('تم تغيير كلمة المرور')); ?></h2>
            <p><?php echo e(__('يمكنك الآن تسجيل الدخول بكلمة المرور الجديدة.')); ?></p><a href="/login" class="ax-submit"
              data-ax-link><?php echo e(__('تسجيل الدخول')); ?></a>
          </div>
          <p class="ax-foot"></p>
        </div>
      </div>
      <div class="ax-scene-col">
        <div class="ax-scene" data-ax-scene aria-hidden="true"></div>
      </div>
    </main>
    <footer class="ax-copy"><?php echo e(__('جميع الحقوق محفوظة ©')); ?> <span data-year>2026</span></footer>
  </div>
  <?php echo $__env->make('partials.itqan-data', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <script src="<?php echo e(asset('js/main.js')); ?>"></script>
  <script src="<?php echo e(asset('js/motion.js')); ?>"></script>
  <script src="<?php echo e(asset('js/auth-scene.js')); ?>"></script>
  <script src="<?php echo e(asset('js/auth.js')); ?>"></script>
  <script src="<?php echo e(asset('js/forgot.js')); ?>"></script>
</body>

</html><?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/pages/reset-password.blade.php ENDPATH**/ ?>