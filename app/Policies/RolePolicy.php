<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * سياسة إدارة الأدوار والصلاحيات: كل الطرق تسمح فقط لمن يملك صلاحية
 * 'roles.manage' (مدير النظام وحده — انظر RolesAndPermissionsSeeder).
 *
 * المسارات محمية أيضاً ببوابة can:roles.manage في routes/web.php، وهذه
 * السياسة هي نقطة الإنفاذ داخل المتحكّم ($this->authorize). تُسجَّل صراحةً
 * عبر Gate::policy في AppServiceProvider لأن النموذج ملك Spatie
 * (Spatie\Permission\Models\Role) فلا يكتشفه التلقائي في Laravel.
 *
 * القواعد الخاصة بحماية الدور نفسه (لا حذف system_admin / لا حذف دور
 * مستخدَم / لا سحب صلاحيات الإدارة من آخر دور يحملها) تُفحص في
 * RoleController وتعيد JSON برسالة عربية واضحة (409/422) لا 403 صامت.
 */
class RolePolicy
{
    /** عرض قائمة الأدوار (/roles) — مدير النظام فقط. */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /** فتح نموذج إضافة دور (/roles/form) — مدير النظام فقط. */
    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /** تعديل صلاحيات دور — مدير النظام فقط. */
    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }

    /** حذف دور — مدير النظام فقط (قيود الحذف الآمن في المتحكّم). */
    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.manage');
    }
}
