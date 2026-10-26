<!DOCTYPE html>
<html lang="<?php echo e(app()->getLocale()); ?>" dir="<?php echo e(app()->isLocale('en') ? 'ltr' : 'rtl'); ?>">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>إتقان | <?php echo e(__('تعديل المستخدم')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/style.css')); ?>">
  <?php if(app()->isLocale('en')): ?><link rel="stylesheet" href="<?php echo e(asset('css/ltr.css')); ?>"><?php endif; ?>
  <link rel="stylesheet" href="<?php echo e(asset('css/components.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/responsive.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/motion.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/notifications.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/ui.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/animations.css')); ?>">
  <link rel="stylesheet" href="<?php echo e(asset('css/features.css')); ?>">
</head>

<body data-page="users">

  <div class="dash-shell">
    <aside class="sidebar">
      <div class="sidebar-brand"><?php echo e(__('نظام إدارة المعاملات الإلكترونية')); ?></div>
      <div class="sidebar-user">
        <img src="<?php echo e(auth()->user()?->avatar_url ?? asset('images/avatar.svg')); ?>" alt="<?php echo e(__('صورة المستخدم')); ?>">
        <div>
          <div class="su-name"><?php echo e(auth()->user()?->name ?? __('غير مسجل')); ?></div>
          <div class="su-role"><?php echo e(__(\App\Support\Roles::label(auth()->user()?->role))); ?></div>
        </div>
      </div>

      <nav class="sidebar-nav">
        <a href="/dashboard" data-page="home">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 11l9-8 9 8" />
            <path d="M5 10v10h14V10" />
          </svg>
          <span><?php echo e(__('الرئيسية')); ?></span>
        </a>
        <a href="/transactions" data-page="transactions">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M7 3h7l4 4v14H7z" />
            <path d="M11 3v5h5" />
          </svg>
          <span><?php echo e(__('المعاملات')); ?></span>
        </a>
        <a href="/inbox" data-page="inbox">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M22 12h-6l-2 3h-4l-2-3H2" />
            <path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z" />
          </svg>
          <span><?php echo e(__('صندوق الوارد')); ?></span>
        </a>
        <a href="/transactions/urgent" data-page="urgent">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="9" />
            <path d="M12 7v5l3 2" />
          </svg>
          <span><?php echo e(__('العاجلة والمتأخرة')); ?></span>
        </a>
        <a href="/dashboard/users" data-page="users">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75" />
          </svg>
          <span><?php echo e(__('المستخدمين')); ?></span>
        </a>
        <a href="/departments" data-page="departments">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-4h6v4M9 10h.01M15 10h.01M9 13h.01M15 13h.01" />
          </svg>
          <span><?php echo e(__('الأقسام')); ?></span>
        </a>
<a href="/types" data-page="types">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 6h16M4 12h16M4 18h10" />
          </svg>
          <span><?php echo e(__('أنواع المعاملات')); ?></span>
        </a>
        <a href="/reports" data-page="reports">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 4v16h16" />
            <path d="M8 15l3-4 3 3 4-6" />
          </svg>
          <span><?php echo e(__('التقارير')); ?></span>
        </a>
        <a href="/activity-log" data-page="activity">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 4h6a1 1 0 0 1 1 1v1H8V5a1 1 0 0 1 1-1z" />
            <path d="M8 6H6a1 1 0 0 0-1 1v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7a1 1 0 0 0-1-1h-2" />
            <path d="M9 12h6M9 16h4" />
          </svg>
          <span><?php echo e(__('سجل النشاطات')); ?></span>
        </a>
        <a href="/notifications" data-page="notifications">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9" />
            <path d="M13.7 21a2 2 0 0 1-3.4 0" />
          </svg>
          <span><?php echo e(__('الإشعارات')); ?></span><i class="nav-dot"></i>
        </a>
        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('roles.manage')): ?>
        <a href="/roles" data-page="roles">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
            <path d="M9 12l2 2 4-4" />
          </svg>
          <span><?php echo e(__('الأدوار والصلاحيات')); ?></span>
        </a>
        <?php endif; ?>
        <a href="/settings" data-page="settings">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="3" />
            <path
              d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1 1.55V21a2 2 0 0 1-4 0v-.09A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.55-1H3a2 2 0 0 1 0-4h.09A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.34-1.87l-.06-.06A2 2 0 1 1 7.03 4.24l.06.06A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.55V3a2 2 0 0 1 4 0v.09a1.7 1.7 0 0 0 1 1.55 1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.7 1.7 0 0 0 19.4 9a1.7 1.7 0 0 0 1.55 1H21a2 2 0 0 1 0 4h-.09a1.7 1.7 0 0 0-1.51 1z" />
          </svg>
          <span><?php echo e(__('الإعدادات')); ?></span>
        </a>
      </nav>

      <form method="POST" action="/logout" class="sidebar-logout-form">
        <?php echo csrf_field(); ?>
        <button type="submit" class="sidebar-logout">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
          <path d="M16 17l5-5-5-5" />
          <path d="M21 12H9" />
        </svg>
        <span><?php echo e(__('تسجيل الخروج')); ?></span>
      </button>
      </form>
    </aside>

    <main class="main-area">
      <div class="panel form-panel">
        <div class="form-panel-header"><?php echo e(__('تعديل المستخدم')); ?></div>
        <div class="form-panel-body">
          <form id="edit-user-form">
            <div class="form-section-title"><?php echo e(__('بيانات المستخدم')); ?></div>
            <div class="form-grid">
              <div class="field"><label for="u-name"><?php echo e(__('الاسم الكامل')); ?></label><input type="text" id="u-name" name="name"
                  required></div>
              <div class="field"><label for="u-username"><?php echo e(__('اسم المستخدم')); ?></label><input type="text" id="u-username"
                  name="username" autocomplete="off" dir="ltr" required></div>
              <div class="field"><label for="u-email"><?php echo e(__('البريد الإلكتروني')); ?></label><input type="email" id="u-email"
                  name="email" dir="ltr" required></div>
              <div class="field"><label for="u-phone"><?php echo e(__('رقم الهاتف')); ?></label><input type="tel" id="u-phone" name="phone"
                  dir="ltr" inputmode="tel" autocomplete="off" required></div>
              <div class="field"><label for="u-pass"><?php echo e(__('كلمة المرور')); ?></label><input type="password" id="u-pass"
                  name="password" autocomplete="new-password" minlength="8">
                <div class="hint"><?php echo e(__('اتركها فارغة للإبقاء على كلمة المرور الحالية')); ?></div>
              </div>
              <div class="field"><label for="u-role"><?php echo e(__('الدور')); ?></label><select id="u-role" name="role">
                  
                  <option value="مدير"><?php echo e(__('مدير')); ?></option>
                  <option value="موظف"><?php echo e(__('موظف')); ?></option>
                  <option value="مراجع"><?php echo e(__('مراجع')); ?></option>
                </select></div>
              <div class="field"><label for="u-status"><?php echo e(__('الحالة')); ?></label><select id="u-status" name="status">
                  <option value="active"><?php echo e(__('نشط')); ?></option>
                  <option value="inactive"><?php echo e(__('غير نشط')); ?></option>
                </select></div>
              <div class="field"><label for="u-college"><?php echo e(__('الكلية')); ?></label><select id="u-college" name="college_id">
                  <option value=""><?php echo e(__('— بدون كلية —')); ?></option>
                </select></div>
              <div class="field"><label for="u-dept"><?php echo e(__('القسم')); ?></label><select id="u-dept" name="department_id">
                  <option value=""><?php echo e(__('— بدون قسم —')); ?></option>
                </select></div>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn btn-primary"><?php echo e(__('حفظ')); ?></button>
              <button type="button" class="btn btn-outline" data-goto="/dashboard/users"><?php echo e(__('إلغاء')); ?></button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script src="<?php echo e(asset('js/main.js')); ?>"></script>
  <script src="<?php echo e(asset('js/motion.js')); ?>"></script>
  <script src="<?php echo e(asset('js/navigation.js')); ?>"></script>
  <script src="<?php echo e(asset('js/shell.js')); ?>"></script>
  <?php echo $__env->make('partials.itqan-data', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
  <script src="<?php echo e(asset('js/data.js')); ?>"></script>
  <script src="<?php echo e(asset('js/ui.js')); ?>"></script>
  <script src="<?php echo e(asset('js/tables.js')); ?>"></script>
  <script src="<?php echo e(asset('js/forms.js')); ?>"></script>
  <script src="<?php echo e(asset('js/dashboard.js')); ?>"></script>
  <script src="<?php echo e(asset('js/departments.js')); ?>"></script>
  <script src="<?php echo e(asset('js/activity.js')); ?>"></script>
  <script src="<?php echo e(asset('js/avatar.js')); ?>"></script>
</body>

</html><?php /**PATH C:\Users\Tofeq\Desktop\laravale\resources\views/pages/edit-user.blade.php ENDPATH**/ ?>