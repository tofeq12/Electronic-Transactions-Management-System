<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * إدارة الأدوار والصلاحيات (/roles).
 *
 * كل طرقها تستدعي $this->authorize(...) بسياسة RolePolicy (قدرته
 * 'roles.manage' لمدير النظام فقط)، والمسارات محمية أيضاً ببوابة
 * can:roles.manage في routes/web.php (طبقتان: بوابة المسار + سياسة السجل).
 *
 * قواعد أعمال صارمة داخل المتحكّم (تُعيد JSON برسالة عربية واضحة):
 *   - اسم الدور (slug) غير قابل للتغيير بعد الإنشاء — يُخزَّن في
 *     users.role وفي target_role لخطوات سير الموافقات.
 *   - لا حذف للأدوار الأساسية الخمسة (Roles::ALL).
 *   - لا حذف لدور مرتبط بمستخدمين (يُعاد 409).
 *   - لا سحب صلاحيتي users.view / roles.manage من آخر دور يحملها
 *     (حماية من قفل الوصول الإداري بالكامل — تُعاد 422).
 */
class RoleController extends Controller
{
    /* ----------------------------------------------------------- pages */

    public function index()
    {
        $this->authorize('viewAny', Role::class);

        return $this->page('pages.roles');
    }

    public function form(Request $request)
    {
        $id = $request->query('id');

        if ($id) {
            $role = Role::findOrFail($id);
            $this->authorize('update', $role);
        } else {
            $this->authorize('create', Role::class);
        }

        return $this->page('pages.role-form');
    }

    /* --------------------------------------------------------- actions */

    public function store(StoreRoleRequest $request)
    {
        $this->authorize('create', Role::class);

        $data = $request->validated();
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);
        // تسمية الدور العربية للعرض (اختيارية — انظر عمود roles.title).
        $role->title = $data['title'] ?? null;
        $role->save();
        $role->syncPermissions($data['permissions'] ?? []);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        \App\Support\Activity::log($request->user(), 'إنشاء دور', $role->name);

        if ($request->expectsJson()) {
            return $this->json(true, 'تم إنشاء الدور', ['redirect' => '/roles', 'id' => $role->id]);
        }

        return $this->redirect('/roles', 'تم إنشاء الدور');
    }

    public function update(UpdateRoleRequest $request, int $id)
    {
        $role = Role::findOrFail($id);
        $this->authorize('update', $role);

        $validated = $request->validated();
        $permissions = $validated['permissions'] ?? [];

        // منع قفل الوصول: لا سحب صلاحية حرجة من آخر دور يحملها.
        if ($lock = $this->lockoutGuard($role, $permissions)) {
            return $lock;
        }

        // تحديث التسمية العربية إذا أُرسلت (null يمسحها، الغياب يُبقيها).
        if (array_key_exists('title', $validated)) {
            $role->title = $validated['title'];
            $role->save();
        }

        $role->syncPermissions($permissions);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        \App\Support\Activity::log($request->user(), 'تعديل صلاحيات دور', $role->name);

        if ($request->expectsJson()) {
            return $this->json(true, 'تم حفظ صلاحيات الدور', ['redirect' => '/roles']);
        }

        return $this->redirect('/roles', 'تم حفظ صلاحيات الدور');
    }

    public function destroy(Request $request, int $id)
    {
        $role = Role::findOrFail($id);
        $this->authorize('delete', $role);

        // الأدوار الأساسية الخمسة محمية من الحذف (مرتبطة بمنطق النظام).
        if (in_array($role->name, \App\Support\Roles::ALL, true)) {
            return $this->json(false, 'لا يمكن حذف دور أساسي في النظام', null, [], 409);
        }

        if ($role->users()->exists()) {
            return $this->json(false, 'لا يمكن حذف دور مرتبط بمستخدمين', null, [], 409);
        }

        // منع قفل الوصول: الحذف يكافئ سحب كل صلاحيات الدور.
        if ($lock = $this->lockoutGuard($role, [])) {
            return $lock;
        }

        \App\Support\Activity::log($request->user(), 'حذف دور', $role->name);
        $role->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->json(true, 'تم حذف الدور');
    }

    /* ------------------------------------------------------ internals */

    /**
     * يمنع قفل الوصول الإداري: لا يمكن سحب صلاحيتي الإدارة (users.view /
     * roles.manage) من آخر دور يحفظهما في النظام — سواء بالتعديل أو
     * (مكافئه) بالحذف. يعيد 422 برسالة عربية أو null إذا كان التغيير آمناً.
     *
     * ملاحظة: المُرسَل أسماء صلاحيات (permissions[]=name) — فالبحث بالاسم
     * لا بالمعرّف (كان خطأً سابقًا يجعل $keeps فارغة دائمًا).
     *
     * @param  array<int, int|string>  $newPermissionIds  الصلاحيات الجديدة بعد التعديل (أسماء — فارغة عند الحذف)
     */
    private function lockoutGuard(Role $role, array $newPermissionIds): ?\Illuminate\Http\JsonResponse
    {
        // الصلاحيات التي سيحتفظ بها هذا الدور بعد التعديل (أسماء).
        $keeps = $newPermissionIds
            ? \Spatie\Permission\Models\Permission::whereIn('name', $newPermissionIds)->pluck('name')->all()
            : [];

        foreach (['users.view', 'roles.manage'] as $critical) {
            $willHold = in_array($critical, $keeps, true);

            // الأدوار الأخرى (غير هذا الدور) التي تملك الصلاحية الحرجة.
            $othersHold = Role::where('id', '!=', $role->id)
                ->whereHas('permissions', fn ($q) => $q->where('name', $critical))
                ->exists();

            if (! $willHold && ! $othersHold) {
                $msg = 'لا يمكن سحب صلاحية ' . $critical . ' من آخر دور يحفظها';

                return $this->json(false, $msg, null, ['permissions' => [$msg]], 422);
            }
        }

        return null;
    }
}
