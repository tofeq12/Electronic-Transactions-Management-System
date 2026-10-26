<?php

namespace App\Http\Controllers;

use App\Http\Requests\Permission\StorePermissionRequest;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * إدارة الصلاحيات (/permissions).
 *
 * كل طرقها تستدعي $this->authorize(...) بسياسة PermissionPolicy (قدرته
 * 'roles.manage' لمدير النظام فقط)، والمسارات محمية أيضاً ببوابة
 * can:roles.manage في routes/web.php.
 *
 * قواعد الحذف داخل المتحكّم (تُعيد JSON برسالة عربية واضحة):
 *   - الصلاحيات الأساسية (الـ12 المزروعة — انظر RolesAndPermissionsSeeder)
 *     غير قابلة للحذف إطلاقاً لأنها أسماء تُفحص في can:... والسياسات؛
 *     حذفها يكسر مصادقة كل الأدوار ويُصعّب إعادة إنشائها بنفس الاسم.
 *   - أي صلاحية مرتبطة بأدوار لا تُحذف (يُعاد 409) — أزلها من الأدوار
 *     أولًا من شاشة الأدوار.
 */
class PermissionController extends Controller
{
    /* ----------------------------------------------------------- pages */

    public function index()
    {
        $this->authorize('viewAny', Permission::class);

        return $this->page('pages.permissions');
    }

    /* --------------------------------------------------------- actions */

    public function store(StorePermissionRequest $request)
    {
        $this->authorize('create', Permission::class);

        $permission = Permission::create([
            'name' => $request->validated()['name'],
            'guard_name' => 'web',
        ]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        \App\Support\Activity::log($request->user(), 'إنشاء صلاحية', $permission->name);

        if ($request->expectsJson()) {
            return $this->json(true, 'تمت إضافة الصلاحية', ['redirect' => '/permissions', 'id' => $permission->id]);
        }

        return $this->redirect('/permissions', 'تمت إضافة الصلاحية');
    }

    public function destroy(Request $request, int $id)
    {
        $permission = Permission::findOrFail($id);
        $this->authorize('delete', $permission);

        // الصلاحيات الأساسية محمية من الحذف (أسماء تُفحص في can:... والسياسات).
        $core = array_merge(
            \Database\Seeders\RolesAndPermissionsSeeder::PERMISSIONS,
            [\Database\Seeders\RolesAndPermissionsSeeder::ROLES_MANAGE],
        );
        if (in_array($permission->name, $core, true)) {
            return $this->json(false, 'لا يمكن حذف صلاحية أساسية في النظام', null, [], 409);
        }

        if ($permission->roles()->exists()) {
            return $this->json(false, 'لا يمكن حذف صلاحية مرتبطة بأدوار', null, [], 409);
        }

        \App\Support\Activity::log($request->user(), 'حذف صلاحية', $permission->name);
        $permission->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->json(true, 'تم حذف الصلاحية');
    }
}
