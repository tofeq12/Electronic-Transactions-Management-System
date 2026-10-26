<?php

namespace App\Http\Controllers;

use App\Models\TrxType;
use App\Models\TrxWorkflow;
use App\Models\TrxWorkflowStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Phase 5 — administrator configuration of approval workflows (one active
 * workflow per transaction type). Editing steps bumps `version`; in-flight
 * transactions keep the snapshot taken at submission and are never re-pathed.
 * All routes sit behind can:workflows.manage.
 *
 * إعداد سِيَر الموافقات: كل طرقها تستدعي authorize('types.manage') للمدير
 * للمدير فقط، والمسارات محمية أيضاً ببوابة can:workflows.manage.
 */
class WorkflowController extends Controller
{
    /* ----------------------------------------------------------- pages */

    public function index()
    {
        $this->authorize('workflows.manage');

        // Workflow configuration lives inside the transaction-types page
        // (one workflow per type — the "سير الموافقة" action per row), so
        // this route stays a compatibility redirect.
        return redirect('/types');
    }

    /* ------------------------------------------------------- workflows */

    public function store(Request $request)
    {
        $this->authorize('workflows.manage');

        $data = $request->validate([
            'trx_type_id' => ['required', 'integer', 'exists:trx_types,id'],
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $type = TrxType::findOrFail($data['trx_type_id']);

        $workflow = TrxWorkflow::firstOrNew(['trx_type_id' => $type->id]);
        if ($workflow->exists) {
            return $this->json(false, 'لدي هذا النوع سير موافقات موجود بالفعل', ['id' => $workflow->id], [], 422);
        }

        $workflow->name = $data['name'] ?? ('سير '.$type->name_ar);
        $workflow->is_active = true;
        $workflow->version = 1;
        $workflow->created_by = auth()->id();
        $workflow->save();

        \App\Support\Activity::log($request->user(), 'إنشاء سير موافقات', $workflow->name, $type->name_ar);

        return $this->json(true, 'تم إنشاء سير الموافقات', ['id' => $workflow->id]);
    }

    public function update(Request $request, int $id)
    {
        $this->authorize('workflows.manage');

        $workflow = TrxWorkflow::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $workflow->update($data);

        \App\Support\Activity::log($request->user(), 'تعديل سير موافقات', $workflow->name);

        return $this->json(true, 'تم حفظ السير');
    }

    public function destroy(int $id)
    {
        $this->authorize('workflows.manage');

        $workflow = TrxWorkflow::withCount('steps')->findOrFail($id);

        if ($workflow->is_active && $workflow->steps_count > 0) {
            // An active workflow with steps may be referenced by pending
            // snapshots — deactivate first; deletion stays available once
            // it is off (snapshots on transactions are immutable copies).
            return $this->json(false, 'أوقف السير قبل حذفه — المعاملات قيد المسار تستخدمه', null, [], 409);
        }

        \App\Support\Activity::log(auth()->user(), 'حذف سير موافقات', $workflow->name);

        $workflow->delete(); // cascades steps; snapshots on transactions stay

        return $this->json(true, 'تم حذف سير الموافقات');
    }

    public function toggle(int $id)
    {
        $this->authorize('workflows.manage');

        $workflow = TrxWorkflow::findOrFail($id);
        $workflow->update(['is_active' => ! $workflow->is_active]);

        \App\Support\Activity::log(auth()->user(), $workflow->is_active ? 'تفعيل سير موافقات' : 'إيقاف سير موافقات', $workflow->name);

        return $this->json(true, $workflow->is_active ? 'تم تفعيل السير' : 'تم إيقاف السير');
    }

    /* ----------------------------------------------------------- steps */

    public function storeStep(Request $request, int $workflowId)
    {
        $this->authorize('workflows.manage');

        $workflow = TrxWorkflow::findOrFail($workflowId);
        $data = $this->validateStep($request);

        DB::transaction(function () use ($workflow, $data) {
            $order = (int) $workflow->steps()->max('step_order') + 1;

            $workflow->steps()->create($data + ['step_order' => $order]);
            $workflow->increment('version');
        });

        \App\Support\Activity::log($request->user(), 'إضافة خطوة سير موافقات', $data['name_ar'], $workflow->name);

        return $this->json(true, 'تمت إضافة الخطوة');
    }

    public function updateStep(Request $request, int $stepId)
    {
        $this->authorize('workflows.manage');

        $step = TrxWorkflowStep::findOrFail($stepId);
        $data = $this->validateStep($request, $step);

        DB::transaction(function () use ($step, $data) {
            $step->update($data);
            $step->workflow->increment('version');
        });

        \App\Support\Activity::log($request->user(), 'تعديل خطوة سير موافقات', $data['name_ar'],
            $step->workflow?->name);

        return $this->json(true, 'تم حفظ الخطوة');
    }

    public function destroyStep(int $stepId)
    {
        $this->authorize('workflows.manage');

        $step = TrxWorkflowStep::with('workflow')->findOrFail($stepId);

        \App\Support\Activity::log(auth()->user(), 'حذف خطوة سير موافقات', $step->name_ar, $step->workflow?->name);

        DB::transaction(function () use ($step) {
            $step->delete();

            // Keep step_order contiguous after a deletion.
            $step->workflow->steps()->orderBy('step_order')->get()
                ->values()
                ->each(fn (TrxWorkflowStep $s, int $i) => $s->update(['step_order' => $i + 1]));

            $step->workflow->increment('version');
        });

        return $this->json(true, 'تم حذف الخطوة');
    }

    /* --------------------------------------------------------- helpers */

    /**
     * A step targets exactly one: department, role, or employee. Roles are
     * normalized (Arabic label or slug both accepted, like the user form).
     *
     * @return array<string, mixed>
     */
    private function validateStep(Request $request, ?TrxWorkflowStep $existing = null): array
    {
        $targetType = $request->input('target_type', $existing?->target_type);

        $data = $request->validate([
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['nullable', 'string', 'max:150'],
            'target_type' => ['required', 'in:department,role,user'],
            'target_dept_id' => [($targetType === 'department' ? 'required' : 'nullable'), 'integer', 'exists:departments,id'],
            'target_role' => [($targetType === 'role' ? 'required' : 'nullable'), 'string', 'max:50'],
            'target_user_id' => [($targetType === 'user' ? 'required' : 'nullable'), 'integer', 'exists:users,id'],
        ]);

        // Cross-field: only the matching target column is kept.
        unset($data['target_dept_id'], $data['target_role'], $data['target_user_id']);
        $data['target_dept_id'] = $targetType === 'department' ? $request->integer('target_dept_id') : null;
        $data['target_role'] = $targetType === 'role'
            ? \App\Support\Roles::slug($request->input('target_role'))
            : null;
        $data['target_user_id'] = $targetType === 'user' ? $request->integer('target_user_id') : null;

        if ($targetType === 'role' && $data['target_role'] === null) {
            abort(422, 'الدور غير صالح');
        }

        return $data;
    }
}
