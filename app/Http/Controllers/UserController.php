<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;

/**
 * إدارة المستخدمين: كل طرق الصفحات والإجراءات تستدعي $this->authorize(...)
 * بسياسة UserPolicy (قدرته users.view للعرض؛ users.create/update/toggle /
 * delete للكتابة). تُضاف حماية عملية:
 * منع تعطيل/حذف الحساب الحالي، وحماية "آخر مسؤول" من التخفيض أو الحذف
 * حتى لا يبقى النظام بلا مدير.
 */
class UserController extends Controller
{
    /* ----------------------------------------------------------- pages */

    public function index()
    {
        $this->authorize('viewAny', User::class);

        return $this->page('pages.dashboard-users', withFullUsers: true);
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return $this->page('pages.add-user', withFullUsers: true);
    }

    public function edit(Request $request)
    {
        // The page injects every user's full record (withFullUsers) — it
        // must not render for callers without user-management rights even
        // when no ?id= is supplied (leak fix, Phase 1 hardening).
        $this->authorize('viewAny', User::class);

        if ($request->filled('id')) {
            $user = User::findOrFail($request->query('id'));
            $this->authorize('update', $user);
        }

        return $this->page('pages.edit-user', withFullUsers: true);
    }

    /* --------------------------------------------------------- actions */

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $data = $request->validated();

        // حارس منع التصعيد: الأدوار الإدارية والأدوار المخصّصة (قد تحمل
        // صلاحيات مرتفعة) لا تُسند إلا من مدير النظام — انظر Roles::canAssign.
        $actor = $request->user();
        $targetRole = Roles::resolve($data['role']);
        if (! Roles::canAssign($actor->role, $targetRole)) {
            return $this->json(false, 'لا يمكنك منح هذا الدور', null, ['role' => ['لا يمكنك منح هذا الدور']], 422);
        }

        $user = User::create([
            'name' => $data['name'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            'role' => $targetRole,
            'status' => $data['status'],
            'college_id' => $data['college_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
        ]);

        \App\Support\Activity::log($request->user(), 'إنشاء مستخدم', $user->username);

        if ($request->expectsJson()) {
            return $this->json(true, 'تمت إضافة المستخدم', ['redirect' => '/dashboard/users']);
        }

        return $this->redirect('/dashboard/users', 'تمت إضافة المستخدم');
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        $target = User::findOrFail($id);
        $this->authorize('update', $target);

        $data = $request->validated();
        $actor = $request->user();

        if ($target->is($actor) && isset($data['status']) && $data['status'] !== 'active') {
            return $this->json(false, 'لا يمكنك تعطيل حسابك الحالي', null, ['status' => ['لا يمكنك تعطيل حسابك الحالي']], 422);
        }

        // حارس منع التصعيد (نفس قاعدة store): رفع دور أي مستخدم إلى دور
        // إداري أو مخصّص مقصور على مدير النظام — انظر Roles::canAssign.
        $newRole = array_key_exists('role', $data) && $data['role'] !== null
            ? Roles::resolve($data['role'])
            : null;
        if (! Roles::canAssign($actor->role, $newRole)) {
            return $this->json(false, 'لا يمكنك منح هذا الدور', null, ['role' => ['لا يمكنك منح هذا الدور']], 422);
        }

        // Never demote or deactivate the last active administrator — the
        // same protection destroy() already applies, extended to role and
        // status changes so the system can never be left without an admin.
        if ($target->isAdmin()) {
            $anotherActiveAdmin = User::whereIn('role', [
                Roles::SYSTEM_ADMIN, Roles::UNIVERSITY_ADMIN, Roles::DEPT_MANAGER,
            ])->where('status', 'active')->where('id', '!=', $target->id)->exists();

            $demotesLastAdmin = ! $anotherActiveAdmin
                && $newRole !== null
                && ! Roles::isAdmin($newRole);
            $disablesLastAdmin = ! $anotherActiveAdmin
                && isset($data['status']) && $data['status'] !== 'active';

            if ($demotesLastAdmin) {
                return $this->json(false, 'لا يمكن تخفيض آخر مسؤول في النظام', null, ['role' => ['لا يمكن تخفيض آخر مسؤول في النظام']], 422);
            }
            if ($disablesLastAdmin) {
                return $this->json(false, 'لا يمكن تعطيل آخر مسؤول في النظام', null, ['status' => ['لا يمكن تعطيل آخر مسؤول في النظام']], 422);
            }
        }

        $target->name = $data['name'];
        $target->username = $data['username'];
        $target->email = $data['email'];
        $target->phone = $data['phone'];

        if (array_key_exists('role', $data) && $data['role'] !== null) {
            $target->role = Roles::resolve($data['role']);
        }
        if (isset($data['status'])) {
            $target->status = $data['status'];
        }
        if (array_key_exists('college_id', $data)) {
            $target->college_id = $data['college_id'] ?: null;
        }
        if (array_key_exists('department_id', $data)) {
            $target->department_id = $data['department_id'] ?: null;
        }
        if (! empty($data['password'])) {
            $target->password = $data['password'];
        }
        $target->save();

        // Phase 8 audit: flag the security-relevant parts of the edit.
        $changes = [];
        if (array_key_exists('role', $data) && $data['role'] !== null) {
            $changes[] = 'الدور';
        }
        if (isset($data['status'])) {
            $changes[] = 'الحالة';
        }
        if (! empty($data['password'])) {
            $changes[] = 'إعادة تعيين كلمة المرور';
        }
        \App\Support\Activity::log($actor, 'تعديل مستخدم', $target->username,
            $changes ? implode('، ', $changes) : null);

        if ($request->expectsJson()) {
            return $this->json(true, 'تم حفظ بيانات المستخدم', ['redirect' => '/dashboard/users']);
        }

        return $this->redirect('/dashboard/users', 'تم حفظ بيانات المستخدم');
    }

    public function toggle(Request $request, int $id)
    {
        $target = User::findOrFail($id);
        $this->authorize('toggle', $target);

        if ($target->is($request->user())) {
            return $this->json(false, 'لا يمكنك تعطيل حسابك الحالي', null, ['status' => ['لا يمكنك تعطيل حسابك الحالي']], 422);
        }

        $target->status = $target->status === 'active' ? 'inactive' : 'active';
        $target->save();

        \App\Support\Activity::log($request->user(), $target->status === 'active' ? 'تفعيل مستخدم' : 'تعطيل مستخدم', $target->username);

        return $this->json(true, $target->status === 'active' ? 'تم تفعيل المستخدم' : 'تم تعطيل المستخدم', [
            'status' => $target->status,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $target = User::findOrFail($id);
        $this->authorize('delete', $target);

        // Never remove the last administrator.
        if ($target->isAdmin()) {
            $remaining = User::whereIn('role', [
                Roles::SYSTEM_ADMIN, Roles::UNIVERSITY_ADMIN, Roles::DEPT_MANAGER,
            ])->where('id', '!=', $target->id)->count();

            if ($remaining === 0) {
                return $this->json(false, 'لا يمكن حذف آخر مسؤول في النظام', null, [], 409);
            }
        }

        $target->delete();

        \App\Support\Activity::log($request->user(), 'حذف مستخدم', $target->username);

        return $this->json(true, 'تم حذف المستخدم');
    }
}
