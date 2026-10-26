<?php

namespace App\Http\Controllers;

use App\Http\Requests\Department\StoreDepartmentRequest;
use App\Http\Requests\Department\UpdateDepartmentRequest;
use App\Models\Department;
use Illuminate\Http\Request;

/**
 * إدارة الأقسام: كل طرقها تستدعي $this->authorize(...) بسياسة
 * DepartmentPolicy (قدرته departments.view للعرض؛ departments.create /
 * update / delete للكتابة). المسارات محمية
 * أيضاً ببوابة can:departments.view في routes/web.php.
 */
class DepartmentController extends Controller
{
    /* ----------------------------------------------------------- pages */

    public function index()
    {
        $this->authorize('viewAny', Department::class);

        return $this->page('pages.departments');
    }

    public function form(Request $request)
    {
        $id = $request->query('id');

        if ($id) {
            $department = Department::findOrFail($id);
            $this->authorize('update', $department);
        } else {
            $this->authorize('create', Department::class);
        }

        return $this->page('pages.department-form');
    }

    /* --------------------------------------------------------- actions */

    public function store(StoreDepartmentRequest $request)
    {
        $this->authorize('create', Department::class);

        $department = Department::create([
            'name' => $request->validated()['name'],
            'description' => $request->validated()['desc'] ?? null,
            'college_id' => $request->validated()['college_id'] ?? null,
        ]);

        \App\Support\Activity::log($request->user(), 'إنشاء قسم', $department->name);

        if ($request->expectsJson()) {
            return $this->json(true, 'تمت إضافة القسم', ['redirect' => '/departments', 'id' => $department->id]);
        }

        return $this->redirect('/departments', 'تمت إضافة القسم');
    }

    public function update(UpdateDepartmentRequest $request, int $id)
    {
        $department = Department::findOrFail($id);
        $this->authorize('update', $department);

        $department->update([
            'name' => $request->validated()['name'],
            'description' => $request->validated()['desc'] ?? null,
            'college_id' => $request->validated()['college_id'] ?? null,
        ]);

        \App\Support\Activity::log($request->user(), 'تعديل قسم', $department->name);

        if ($request->expectsJson()) {
            return $this->json(true, 'تم حفظ القسم', ['redirect' => '/departments']);
        }

        return $this->redirect('/departments', 'تم حفظ القسم');
    }

    public function destroy(Request $request, int $id)
    {
        $department = Department::findOrFail($id);
        $this->authorize('delete', $department);

        // Mirror the UI rule: departments with forwarded transactions stay.
        if ($department->transactions()->exists()) {
            return $this->json(false, 'لا يمكن حذف قسم أُحيلت إليه معاملات', null, [], 409);
        }

        $department->users()->update(['department_id' => null]);
        \App\Support\Activity::log($request->user(), 'حذف قسم', $department->name);
        $department->delete();

        return $this->json(true, 'تم حذف القسم');
    }
}
