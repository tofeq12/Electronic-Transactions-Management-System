<?php

/*
1. تنظيم الأدوار: مدير، موظف، مراجع.
2. ترجمة الأدوار: يحوّل employee إلى «موظف» للعرض في الواجهة.
3.
*/

namespace App\Support;

use App\Models\User;

/**
 * Role label/slug registry.
 *
 * هذا الملف لم يعد مصدر الصلاحيات. إنفاذ الصلاحيات والأدوار أصبح عبر
 * Spatie laravel-permission (جداول roles / permissions /
 * model_has_roles) — انظر database/seeders/RolesAndPermissionsSeeder
 * كمصدر وحيد لحقيقة الأدوار والصلاحيات، و$user->can(...) /
 * can: middleware / السياسات كنقاط الإنفاذ.
 *
 * ما يبقى هنا هو الطبقة التحريرية فقط (لا صلاحيات):
 *   - LABELS / FROM_LABEL / label() : ترجمة slug إلى تسمية عربية للواجهة.
 *   - slug() : تطبيع قيمة مُرسلة (slug أو تسمية عربية) إلى slug.
 *   - isAdmin() : تحقّق نطاق أعمال من slugs المدير (يُستخدم لحماية
 *     "آخر مسؤول" من الحذف/التخفيض في UserController).
 *   - seesEverything() : نطاق رؤية بيانات المعاملات (يُستخدم داخل
 *     Transaction::visibleTo وسياسة العرض) — قاعدة أعمال وليست صلاحية.
 *
 * ============================ الأدوار في النظام ============================
 * النظام يدعم من واجهة الإدخال ثلاثة أدوار رئيسية، تُخزَّن في عمود
 * users.role كـ slugs وتطابق أسماء الأدوار المسجّلة في Spatie:
 *
 *   • مدير  (slug = system_admin، ويُقبل أيضاً university_admin /
 *     dept_manager لكنهما غير قابلَّين للاختيار من الواجهة).
 *   • موظف  (slug = employee).
 *   • مراجع (slug = reviewer).
 *
 * كل دور يرتبط بدور Spatie المقابل تلقائياً عند حفظ النموذج (انظر
 * User::booted) عبر syncRoles، فلا يوجد مصدران متعارضان للدور.
 *
 * الفصل بين الأدوار يتحقّق في ثلاثة مستويات معاً (لا يعتمد أحدها وحده):
 *   1) الوسيطات (Middleware): "auth" + "active" تمنعان غير المسجّلِ دخولاً
 *      والحساب غير المفعّل (انظر EnsureUserIsActive).
 *   2) صلاحيات Spatie على المسارات: can:departments.view / types.view /
 *      types.manage / workflows.manage / reports.view / activity.view
 *      تحمي مسارات الإدارة (routes/web.php) وتُحلّ عبر Gate::before الذي
 *      تسجّله الحزمة.
 *   3) السياسات (Policies) داخل المتحكّمات: $this->authorize(...) تفحص كل
 *      طلب كتابة على مستوى السجل (UserPolicy / TransactionPolicy /
 *      DepartmentPolicy).
 */
class Roles
{
    public const SYSTEM_ADMIN = 'system_admin';
    public const UNIVERSITY_ADMIN = 'university_admin';
    public const DEPT_MANAGER = 'dept_manager';
    public const EMPLOYEE = 'employee';
    public const REVIEWER = 'reviewer';

    /** Every slug accepted by validation. */
    public const ALL = [
        self::SYSTEM_ADMIN,
        self::UNIVERSITY_ADMIN,
        self::DEPT_MANAGER,
        self::EMPLOYEE,
        self::REVIEWER,
    ];

    /** Slug => Arabic label shown by the UI. */
    public const LABELS = [
        self::SYSTEM_ADMIN => 'مدير',
        self::UNIVERSITY_ADMIN => 'مدير',
        self::DEPT_MANAGER => 'مدير',
        self::EMPLOYEE => 'موظف',
        self::REVIEWER => 'مراجع',
    ];

    /** Arabic label => canonical slug (what the UI role <select> submits). */
    public const FROM_LABEL = [
        'مدير' => self::SYSTEM_ADMIN,
        'موظف' => self::EMPLOYEE,
        'مراجع' => self::REVIEWER,
    ];

    /**
     * الأدوار الأساسية غير الإدارية التي يجوز لأي حامل users.create/update إسنادها
     * (موظف / مراجع). أي دور آخر — الإداري أو المخصّص — إسناده مقصور على
     * مدير النظام (انظر canAssign).
     */
    public const BASE_ASSIGNABLE = [
        self::EMPLOYEE,
        self::REVIEWER,
    ];

    /**
     * هل يحق لـ $actorRole أن يُسند $targetRole؟
     *
     * القاعدة الأمنية: حامل users.update يُسند الدورين الموظف/المراجع فقط.
     * إسناد أي دور إداري (system_admin/university_admin/dept_manager) أو
     * دور مخصّص (قد يحمل صلاحيات مرتفعة كـ roles.manage) مقصور على مدير
     * النظام — تُمنع درجات الصعود. تُستخدم في UserController::store/update.
     */
    public static function canAssign(?string $actorRole, ?string $targetRole): bool
    {
        if ($targetRole === null || $targetRole === '') {
            return true; // لا تغيير للدور.
        }

        if (in_array($targetRole, self::BASE_ASSIGNABLE, true)) {
            return true;
        }

        return $actorRole === self::SYSTEM_ADMIN;
    }

    public static function label(?string $role): string
    {
        if ($role === null || $role === '') {
            return '—';
        }

        if (isset(self::LABELS[$role])) {
            return self::LABELS[$role];
        }

        // دور مخصّص: التسمية العربية المُدخلة من شاشة الأدوار (roles.title).
        // استعلام واحد فقط للمقاطع غير المعروفة (الأساسية تُترجم محليًا).
        return \Spatie\Permission\Models\Role::where('name', $role)->value('title') ?? '—';
    }

    /**
     * Normalize a submitted value (slug or Arabic label) to a slug.
     */
    public static function slug(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (isset(self::LABELS[$value])) {
            return $value;
        }

        return self::FROM_LABEL[$value] ?? null;
    }

    /**
     * تطبيع قيمة مُرسلة إلى slug مع تمرير الأدوار المخصّصة.
     *
     * تختلف عن slug(): slug() تعيد null لأي اسم غير معروف (لأنها تُستخدم
     * في مواضع لا يُسمح فيها بأدوار جديدة)، أما هذه فتُستخدم في إسناد
     * الأدوار من إدارة المستخدمين حيث يسمح التحقق بوجود الدور فعليًا في
     * قاعدة البيانات — فتُعيد الاسم كما هو إذا لم يكن تسمية عربية.
     */
    public static function resolve(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (isset(self::LABELS[$value])) {
            return $value;
        }

        return self::FROM_LABEL[$value] ?? $value;
    }

    /**
     * أي من slugs المدير (يُستخدم لحماية "آخر مسؤول" من الحذف/التخفيض).
     */
    public static function isAdmin(?string $role): bool
    {
        return in_array($role, [self::SYSTEM_ADMIN, self::UNIVERSITY_ADMIN, self::DEPT_MANAGER], true);
    }

    /**
     * Full transaction-data scope. System and university admins see
     * everything; a department manager WITHOUT an assigned department acts
     * at university level. A department manager WITH a department is scoped
     * to that department (managerial scope — approve/reject stay limited to
     * what TransactionPolicy::view allows).
     *
     * نطاق رؤية بيانات المعاملات: المدير (جامعة/نظام) يرى كل شيء، ومدير
     * القسم بلا قسم معيّن يعامل كمدير جامعة، أما مدير القسم المعيّن فيقتصر
     * على معاملات قسمه (يُستخدم داخل Transaction::visibleTo وسياسة العرض).
     *
     * المرحلة 3: المصدر أدوار Spatie (hasAnyRole) لا عمود users.role — نفس
     * المصدر الذي تفحصه البوابات والسياسات فلا يتعارض ناتجين. يُطابق
     * User::booted() العمود مع الأدوار عند كل حفظ.
     */
    public static function seesEverything(User $user): bool
    {
        if ($user->hasAnyRole([self::SYSTEM_ADMIN, self::UNIVERSITY_ADMIN])) {
            return true;
        }

        return $user->hasRole(self::DEPT_MANAGER) && $user->department_id === null;
    }
}
