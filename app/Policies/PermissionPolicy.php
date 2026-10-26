<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * سياسة إدارة الصلاحيات (/permissions): نفس بوابة 'roles.manage' —
 * مدير النظام فقط، لأن تعديل الصلاحيات أقوى من تعديل الدور (منح أي
 * قدرة مباشرة). المسارات محمية أيضاً ببوابة can:roles.manage، والسياسة
 * هي نقطة الإنفاذ داخل المتحكّم عبر $this->authorize.
 *
 * ملاحظة: النموذج ملك Spatie فلا يكتشفه Laravel تلقائياً — مُسجَّل في
 * AppServiceProvider عبر Gate::policy.
 */
class PermissionPolicy
{
    /** عرض شاشة الصلاحيات (/permissions) — مدير النظام فقط. */
    public function viewAny(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /** إنشاء صلاحية مخصصة — مدير النظام فقط. */
    public function create(User $user): bool
    {
        return $user->can('roles.manage');
    }

    /** حذف صلاحية — مدير النظام فقط (شرط عدم استخدامها في المتحكّم). */
    public function delete(User $user, Permission $permission): bool
    {
        return $user->can('roles.manage');
    }
}
