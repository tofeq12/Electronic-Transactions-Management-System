<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;

/**
 * Departments — granular permissions (one per action), replacing the
 * single departments.manage switch:
 *   viewAny → departments.view, create → departments.create,
 *   update → departments.update, delete → departments.delete.
 *
 * سياسة الأقسام: صلاحية دقيقة لكل إجراء (عرض/إنشاء/تعديل/حذف) بدل
 * departments.manage الواحدة. الكليات (CollegeController) تستخدم نفس
 * المجموعة لأنها أعلى الهيكل التنظيمي نفسه.
 */
class DepartmentPolicy
{
    /** عرض شاشة إدارة الأقسام — departments.view. */
    public function viewAny(User $user): bool
    {
        return $user->can('departments.view');
    }

    /** إضافة قسم — departments.create. */
    public function create(User $user): bool
    {
        return $user->can('departments.create');
    }

    /** تعديل قسم — departments.update. */
    public function update(User $user, Department $department): bool
    {
        return $user->can('departments.update');
    }

    /** حذف قسم — departments.delete. */
    public function delete(User $user, Department $department): bool
    {
        return $user->can('departments.delete');
    }
}
