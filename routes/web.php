<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CollegeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\FileController;
use App\Http\Controllers\InboxController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TrxTypeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes
|--------------------------------------------------------------------------
| Public entry/auth pages stay reachable without a session; everything
| behind "auth + active" requires a logged-in, enabled account. All write
| endpoints answer with the JSON contract {success,message,data,errors}
| when requested with Accept: application/json (the existing frontend),
| and redirect back otherwise.
*/

// Splash / entry
Route::view('/', 'index')->name('splash');
Route::view('/splash2', 'pages.splash2')->name('splash2');
Route::view('/auth-choice', 'pages.auth-choice')->name('auth-choice');
Route::view('/onboarding', 'pages.onboarding')->name('onboarding');

// Auth (public)
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.store');
// Phase 9 — real recovery flow (honest about the mail transport, see
// AuthController::forgotPassword). الاسترجاع يمرّ عبر forgot-password ثم
// reset-password بالرمز المرسل، ولا توجد شاشة verify-code مستقلة.
Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('forgot-password');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1')->name('forgot-password.store');
Route::get('/reset-password', [AuthController::class, 'showResetPassword'])->name('reset-password');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1')->name('reset-password.store');
// Phase 12 — logout is POST-only: a GET can no longer end a session
// (classic "logout CSRF"); the sidebar renders the form itself.
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Error screens
Route::view('/403', 'errors.403')->name('error.403');
Route::view('/404', 'errors.404')->name('error.404');
Route::view('/500', 'errors.500')->name('error.500');

/*
| Authenticated application
|--------------------------------------------------------------------------
| كل ما بداخل هذه المجموعة يتطلب تسجيل دخولاً ناجحاً ('auth') وحساباً
| مفعّلاً ('active' ← وسيط EnsureUserIsActive). البوابات can:... تضيف طبقة
| دور فوق ذلك لمسارات الإدارة، والتحقق الدقيق لكل سجل يتم داخل المتحكّم عبر
| سياسات authorize.
*/
Route::middleware(['auth', 'active'])->group(function () {

    // Dashboard / pages
    Route::get('/dashboard', [PageController::class, 'dashboard'])->name('dashboard');
    Route::get('/settings', [PageController::class, 'settings'])->name('settings');
    Route::post('/settings/password', [SettingsController::class, 'password'])->name('settings.password');

    // Inbox — personal action queue, filtered on the server
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox');

    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/locale', [ProfileController::class, 'locale'])->name('profile.locale');
    Route::post('/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');
    Route::post('/profile/avatar/delete', [ProfileController::class, 'removeAvatar'])->name('profile.avatar.delete');
    Route::get('/profile/avatar', [ProfileController::class, 'serveAvatar'])->name('profile.avatar.show');

    // User management (users.view للعرض + users.create/update/toggle/delete)
    // إدارة المستخدمين: لا بوابة can: هنا — الحماية داخل المتحكّم عبر
    // UserPolicy ($this->authorize)؛ لا يصل غير المدير إلى أي طرق أدناه.
    Route::get('/dashboard/users', [UserController::class, 'index'])->name('dashboard.users');
    Route::get('/users/add', [UserController::class, 'create'])->name('users.add');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::post('/users/{id}/update', [UserController::class, 'update'])->name('users.update');
    Route::post('/users/{id}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    Route::post('/users/{id}/delete', [UserController::class, 'destroy'])->name('users.delete');

    // Departments (departments.view — الحد الأدنى لدخول الشاشة)
    // البوابة تحمي عرض مسارات الأقسام والكليات؛ وكل إجراء كتابة يُفحص
    // بمفتاحه الدقيق داخل المتحكّم (DepartmentPolicy / CollegeController:
    // departments.create / update / delete) — الموظف/المراجع يُمنعون بـ403.
    Route::middleware('can:departments.view')->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments');
        Route::get('/departments/form', [DepartmentController::class, 'form'])->name('departments.form');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::post('/departments/{id}/update', [DepartmentController::class, 'update'])->name('departments.update');
        Route::post('/departments/{id}/delete', [DepartmentController::class, 'destroy'])->name('departments.delete');

        // Phase 11 — colleges (organization structure); same gate.
        Route::post('/colleges', [CollegeController::class, 'store'])->name('colleges.store');
        Route::post('/colleges/{id}/update', [CollegeController::class, 'update'])->name('colleges.update');
        Route::post('/colleges/{id}/delete', [CollegeController::class, 'delete'])->name('colleges.delete');
    });

    // Roles & permissions (roles.manage)
    // إدارة الأدوار والصلاحيات: بوابة can:roles.manage تحمي كل المسارات
    // أدناه — مدير النظام فقط (باقٍ الأدوار الإدارية يُمنعون بـ 403 قبل
    // بلوغ المتحكّم). الحماية الدقيقة لكل سجل داخل المتحكّم عبر RolePolicy /
    // PermissionPolicy ($this->authorize).
    Route::middleware('can:roles.manage')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::get('/roles/form', [RoleController::class, 'form'])->name('roles.form');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::post('/roles/{id}/update', [RoleController::class, 'update'])->name('roles.update');
        Route::post('/roles/{id}/delete', [RoleController::class, 'destroy'])->name('roles.delete');

        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions');
        Route::post('/permissions', [PermissionController::class, 'store'])->name('permissions.store');
        Route::post('/permissions/{id}/delete', [PermissionController::class, 'destroy'])->name('permissions.delete');
    });

    // Transaction types (types.view للعرض + types.manage للكتابة)
    // شاشة الأنواع تُفتح بصلاحية العرض، وكل عملية كتابة (نوع/حقل) تُفحص
    // بمفتاحها الدقيق types.manage داخل المتحكّم أيضًا (طبقتان).
    Route::get('/types', [TrxTypeController::class, 'index'])->middleware('can:types.view')->name('types');
    Route::middleware('can:types.manage')->group(function () {
        Route::post('/types', [TrxTypeController::class, 'store'])->name('types.store');
        Route::post('/types/{id}/update', [TrxTypeController::class, 'update'])->name('types.update');
        Route::post('/types/{id}/toggle', [TrxTypeController::class, 'toggle'])->name('types.toggle');
        Route::post('/types/{id}/delete', [TrxTypeController::class, 'destroy'])->name('types.delete');
        Route::post('/types/{id}/fields', [TrxTypeController::class, 'storeField'])->name('types.fields.store');
        Route::post('/fields/{id}/update', [TrxTypeController::class, 'updateField'])->name('fields.update');
        Route::post('/fields/{id}/toggle', [TrxTypeController::class, 'toggleField'])->name('fields.toggle');
        Route::post('/fields/{id}/delete', [TrxTypeController::class, 'destroyField'])->name('fields.delete');
    });

    // Phase 5 — configurable approval workflows (workflows.manage المستقلة).
    Route::middleware('can:workflows.manage')->group(function () {
        Route::get('/workflows', [WorkflowController::class, 'index'])->name('workflows');
        Route::post('/workflows', [WorkflowController::class, 'store'])->name('workflows.store');
        Route::post('/workflows/{id}/update', [WorkflowController::class, 'update'])->name('workflows.update');
        Route::post('/workflows/{id}/toggle', [WorkflowController::class, 'toggle'])->name('workflows.toggle');
        Route::post('/workflows/{id}/delete', [WorkflowController::class, 'destroy'])->name('workflows.delete');
        Route::post('/workflows/{id}/steps', [WorkflowController::class, 'storeStep'])->name('workflows.steps.store');
        Route::post('/workflow-steps/{id}/update', [WorkflowController::class, 'updateStep'])->name('workflow-steps.update');
        Route::post('/workflow-steps/{id}/delete', [WorkflowController::class, 'destroyStep'])->name('workflow-steps.delete');
    });

    // Transactions
    // المعاملات: لا بوابة can: على المسار — كل طريقة تستدعي $this->authorize
    // بسياسة TransactionPolicy (قدرة الدور + حدود الرؤية view()).
    Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions');
    Route::get('/transactions/new', [TransactionController::class, 'create'])->name('transactions.new');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::get('/transactions/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
    Route::get('/transactions/details', [TransactionController::class, 'show'])->name('transactions.details');
    Route::get('/transactions/forward', [TransactionController::class, 'forwardPage'])->name('transactions.forward');
    Route::get('/transactions/urgent', [TransactionController::class, 'urgent'])->name('transactions.urgent');
    // Phase 7 — pagination endpoints (same visibleTo() boundary).
    Route::get('/transactions/list', [TransactionController::class, 'list'])->name('transactions.list');
    Route::get('/transactions/view', [TransactionController::class, 'view'])->name('transactions.view');
    Route::post('/transactions/{id}/update', [TransactionController::class, 'update'])->name('transactions.update');
    Route::post('/transactions/{id}/forward', [TransactionController::class, 'forward'])->name('transactions.forward.action');
    Route::post('/transactions/{id}/approve', [TransactionController::class, 'approve'])->name('transactions.approve');
    Route::post('/transactions/{id}/reject', [TransactionController::class, 'reject'])->name('transactions.reject');
    Route::post('/transactions/{id}/return', [TransactionController::class, 'returnToSubmitter'])->name('transactions.return');
    Route::post('/transactions/{id}/delete', [TransactionController::class, 'destroy'])->name('transactions.delete');

    // Private attachments
    Route::get('/files/{id}/download', [FileController::class, 'download'])->name('files.download');

    // Reports (reports.view)
    // بوابة can:reports.view — المدير والمراجع فقط (الموظف لا يرى التقارير).
    Route::get('/reports', [ReportController::class, 'index'])->middleware('can:reports.view')->name('reports');
    // Phase 10 — server-computed figures + CSV export (same gate, same
    // visibleTo() boundary as the page itself).
    Route::get('/reports/data', [ReportController::class, 'data'])->middleware('can:reports.view')->name('reports.data');
    Route::get('/reports/export', [ReportController::class, 'export'])->middleware('can:reports.view')->name('reports.export');

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.readAll');

    // Activity log (activity.view)
    // بوابة can:activity.view — المدير فقط (سجل النشاط يعرض أفعال الآخرين).
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->middleware('can:activity.view')->name('activity-log');
});
