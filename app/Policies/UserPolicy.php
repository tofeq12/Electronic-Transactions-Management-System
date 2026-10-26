<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management — granular permissions (one per action), replacing the
 * single users.manage switch:
 *   viewAny → users.view, create → users.create, update → users.update,
 *   toggle → users.toggle, delete → users.delete.
 *
 * سياسة إدارة المستخدمين: صلاحية دقيقة لكل إجراء (عرض/إنشاء/تعديل/
 * تفعيل/حذف) بدل صلاحية واحدة شاملة — كل إجراء يُفحص حيث يُنفَّذ عبر
 * $user->can (Spatie). الفحص يمرّ عبر Gate::before الذي تسجّله حزمة
 * spatie/laravel-permission. تُستدعى تلقائيًا لأن اسم السياسة UserPolicy
 * يتوافق مع النموذج User.
 */
class UserPolicy
{
    /** عرض قائمة المستخدمين (/dashboard/users) — users.view. */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /** فتح نموذج الإضافة (/users/add) — users.create. */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /** حفظ تعديل مستخدم — users.update (لا يشترط استهداف سجل معيّن). */
    public function update(User $user, User $target): bool
    {
        return $user->can('users.update');
    }

    /** تفعيل/تعطيل حساب — users.toggle؛ ولا يُسمح بتعطيل الحساب الحالي (يُفحص في المتحكّم). */
    public function toggle(User $user, User $target): bool
    {
        return $user->can('users.toggle');
    }

    /** حذف مستخدم — users.delete؛ ولا يُسمح لأحد بحذف حسابه هو. */
    public function delete(User $user, User $target): bool
    {
        if ($target->id === $user->id) {
            return false; // never delete your own account
        }

        return $user->can('users.delete');
    }
}
