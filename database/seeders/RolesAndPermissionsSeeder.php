<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RolesAndPermissionsSeeder.
 *
 * المصدر الوحيد لحقيقة الأدوار والصلاحيات عبر Spatie. يستبدل المصفوفة
 * المكتوبة يدويًا في App\Support\Roles بمصفوفة مخزّنة في قاعدة البيانات
 * (جداول roles / permissions / role_has_permissions).
 *
 * المرحلة 3 — صلاحيات دقيقة بنقطة (dot notation) بدل صلاحية واحدة لكل
 * متحكّم، على غرار الأنظمة الحقيقية ووثائق Laravel:
 *   - users.view/create/update/toggle/delete + departments.* + types.view
 *     + types.manage + workflows.manage + trx.* (منها trx.return الجديدة)
 *   - كل صلاحية تُفحص حيث تُستخدَم فعليًا (سياسات + بوابات مسارات).
 *
 * توزيع الأدوار (least-privilege مع الحفاظ على السلوك الحالي):
 *   - system_admin       => كل الصلاحيات + roles.manage.
 *   - university_admin   => كل الصلاحيات ما عدا roles.manage.
 *   - dept_manager       => كل الصلاحيات ما عدا roles.manage.
 *   - employee  => trx.create / trx.edit / trx.forward / urgent.view
 *   - reviewer  => urgent.view / reports.view
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /** كل الصلاحيات ما عدا roles.manage (حرجة الإدارية) — عامة لأن PermissionController يحمي حذفها. */
    public const PERMISSIONS = [
        'users.view',
        'users.create',
        'users.update',
        'users.toggle',
        'users.delete',
        'departments.view',
        'departments.create',
        'departments.update',
        'departments.delete',
        'types.view',
        'types.manage',
        'workflows.manage',
        'reports.view',
        'activity.view',
        'urgent.view',
        'trx.create',
        'trx.edit',
        'trx.forward',
        'trx.approve',
        'trx.return',
        'trx.delete',
    ];

    /**
     * إدارة الأدوار والصلاحيات — صلاحية مدير النظام فقط.
     *
     * قرار أمني مقصود: من يملك تعديل الأدوار يملك منح أي صلاحية لنفسه،
     * فلا تُمنح لبقية الأدوار الإدارية (university_admin / dept_manager)
     * حتى لا تكون هناك درجات صعود صلاحيات. وحدها شاشتا الأدوار تتطلبها.
     */
    public const ROLES_MANAGE = 'roles.manage';

    /** الأدوار الإدارية الثلاثة تحصل على كل الصلاحيات (ما عدا roles.manage — لمدير النظام وحده). */
    private const ADMIN_ROLES = ['system_admin', 'university_admin', 'dept_manager'];

    public function run(): void
    {
        // إعادة تعيين الذاكرة المؤقتة للصلاحيات قبل إعادة الإنشاء.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // إنشاء الصلاحيات (guard: web) — الحمولة الدقيقة + roles.manage.
        foreach (array_merge(self::PERMISSIONS, [self::ROLES_MANAGE]) as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // إنشاء الأدوار وربطها بصلاحياتها.
        foreach (self::ADMIN_ROLES as $role) {
            Role::findOrCreate($role, 'web')->syncPermissions(self::PERMISSIONS);
        }

        // مدير النظام وحده يحصل على إدارة الأدوار والصلاحيات (انظر ROLES_MANAGE).
        Role::findByName('system_admin', 'web')->syncPermissions(
            array_merge(self::PERMISSIONS, [self::ROLES_MANAGE])
        );

        Role::findOrCreate('employee', 'web')->syncPermissions([
            'trx.create',
            'trx.edit',
            'trx.forward',
            'urgent.view',
        ]);

        Role::findOrCreate('reviewer', 'web')->syncPermissions([
            'urgent.view',
            'reports.view',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
