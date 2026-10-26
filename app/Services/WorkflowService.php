<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\TrxType;
use App\Models\TrxWorkflow;
use App\Models\TrxWorkflowStep;
use App\Models\User;
use App\Support\Roles;

/**
 * Phase 5 — configurable approval workflow engine.
 *
 * How it integrates with the EXISTING engine (no competing paths):
 * - Types without an active workflow keep today's single-step behaviour
 *   exactly (approve -> done).
 * - When a transaction is submitted (draft -> pending) and its type has an
 *   active workflow with steps, an immutable JSON snapshot (version +
 *   ordered steps) is stored on the transaction; the transaction is routed
 *   to the first step's target (dept/user) so it appears in that inbox.
 * - Approving on a non-final step records the decision, advances
 *   current_step and re-routes; ONLY the final step sets status=done.
 * - Rejecting at any step is a final rejection (existing semantics).
 * - Returning for completion keeps workflow_data; resuming (draft ->
 *   pending again) continues at the SAME step — never re-pathed.
 * - Every write runs inside the caller's DB transaction; the row is
 *   lockForUpdate()'d on advance so two concurrent approvals can never
 *   both pass the same step.
 *
 * Admin (seesEverything) keeps universal authority at every step — no
 * existing approval power is narrowed by the workflow.
 */
class WorkflowService
{
    /* ---------------------------------------------------- submission */

    /**
     * Attach the immutable snapshot on submission (draft -> pending) if the
     * type has an active workflow with steps, and route to step 1.
     * Returns true when a snapshot was attached.
     */
    public function attachOnSubmission(Transaction $trx, User $actor): bool
    {
        // Already has a snapshot (resuming after return-for-completion):
        // keep it — the path and completed steps must survive resubmission.
        if (! empty($trx->workflow_data)) {
            $this->routeToCurrentStep($trx);

            return false;
        }

        $typeId = TrxType::query()->where('slug', $trx->type)->value('id');

        if ($typeId === null) {
            return false;
        }

        $workflow = TrxWorkflow::query()
            ->active()
            ->where('trx_type_id', $typeId)
            ->with('steps')
            ->get()
            ->first(fn (TrxWorkflow $w) => $w->steps->isNotEmpty());

        if ($workflow === null) {
            return false; // no workflow -> single-step behaviour (unchanged)
        }

        $steps = $workflow->steps->values();

        $trx->update([
            'workflow_data' => [
                'id' => $workflow->id,
                'version' => $workflow->version,
                'started_at' => now()->toDateTimeString(),
                'current_step' => 1,
                'total' => $steps->count(),
                'steps' => $steps->map(fn (TrxWorkflowStep $s) => [
                    'name' => app()->isLocale('en') ? ($s->name_en ?: $s->name_ar) : $s->name_ar,
                    'target_type' => $s->target_type,
                    'target_dept' => $s->target_dept_id,
                    'target_role' => $s->target_role,
                    'target_user' => $s->target_user_id,
                ])->values()->all(),
                'completed' => [],
            ],
        ]);

        $this->routeToCurrentStep($trx);

        return true;
    }

    /* ------------------------------------------------------- queries */

    public function hasWorkflow(Transaction $trx): bool
    {
        return ! empty($trx->workflow_data['total']);
    }

    public function isFinalStep(Transaction $trx): bool
    {
        $d = $trx->workflow_data ?? [];

        return ($d['current_step'] ?? 1) >= ($d['total'] ?? 1);
    }

    /**
     * Raw snapshot step for the current position (null = no workflow).
     *
     * @return array{name: string, target_type: string, target_dept: ?int, target_role: ?string, target_user: ?int}|null
     */
    public function currentStep(Transaction $trx): ?array
    {
        $d = $trx->workflow_data ?? [];

        if (empty($d['total'])) {
            return null;
        }

        return $d['steps'][$d['current_step'] - 1] ?? null;
    }

    /**
     * Users allowed to decide on the current step: they match the step
     * target AND hold trx.approve (visibility/self-approval checks stay in
     * TransactionPolicy).
     *
     * @return array<int, int>
     */
    public function stepApproverIds(Transaction $trx): array
    {
        $step = $this->currentStep($trx);

        if ($step === null) {
            return [];
        }

        return User::query()
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $u) => $u->seesEverything() || $this->stepMatches($step, $u))
            ->filter(fn (User $u) => $u->can('trx.approve'))
            ->pluck('id')
            ->values()
            ->all();
    }

    /** Does the raw snapshot step match this user? */
    private function stepMatches(array $step, User $user): bool
    {
        return match ($step['target_type'] ?? '') {
            'department' => $user->department_id !== null && $user->department_id === $step['target_dept'],
            'role' => $user->role === ($step['target_role'] ?? null),
            'user' => $user->id === $step['target_user'],
            default => false,
        };
    }

    /**
     * May this user decide on the transaction's current step? Mirrors
     * stepApproverIds() for one user (service checks this before advancing).
     * Admins (seesEverything) may always act — approval power is never
     * narrowed by configuration.
     */
    public function allowsStep(Transaction $trx, User $user): bool
    {
        $step = $this->currentStep($trx);

        if ($step === null) {
            return true; // no workflow -> existing policy decides alone
        }

        return $user->seesEverything() || $this->stepMatches($step, $user);
    }

    /* ------------------------------------------------------- advance */

    /**
     * Record the current step as decided. Returns 'final' when this was the
     * last step (caller sets status=done), 'advanced' when the transaction
     * moves to the next step (stays pending). Runs inside the caller's DB
     * transaction; the row is locked against concurrent decisions.
     */
    public function advance(Transaction $trx, User $actor, string $decision, ?string $reason = null): string
    {
        if (! $this->hasWorkflow($trx)) {
            return 'final'; // no workflow -> caller keeps single-step semantics
        }

        $fresh = Transaction::query()->whereKey($trx->id)->lockForUpdate()->first();

        // Re-check under the lock: a concurrent decision may have finished
        // or moved this step between the controller's check and our write.
        if ($fresh->status !== 'pending') {
            abort(409, 'هذه الإجراءات متاحة للمعاملات قيد الانتظار فقط');
        }

        $data = $fresh->workflow_data ?? [];
        $index = ($data['current_step'] ?? 1) - 1;

        if (! isset($data['steps'][$index])) {
            abort(409, 'حالة سير الموافقات غير صالحة');
        }

        // Re-check step authority under the lock (skip-proof): the actor
        // must still match the CURRENT step — never the one they loaded.
        if (! ($actor->seesEverything() || $this->stepMatches($data['steps'][$index], $actor))) {
            abort(403, 'هذه الخطوة مخصصة لجهة معينة ولا يمكنك اعتمادها');
        }

        $data['completed'][] = [
            'step' => $data['current_step'],
            'name' => $data['steps'][$index]['name'],
            'by' => $actor->name,
            'at' => now()->toDateTimeString(),
            'decision' => $decision,
            'reason' => $reason,
        ];

        if ($data['current_step'] >= $data['total']) {
            $fresh->update(['workflow_data' => $data]);
            $trx->setRawAttributes($fresh->getAttributes(), true);

            return 'final';
        }

        $data['current_step']++;
        $fresh->update(['workflow_data' => $data]);
        $this->routeToCurrentStep($fresh);
        $trx->setRawAttributes($fresh->getAttributes(), true);

        return 'advanced';
    }

    /**
     * Record a decision WITHOUT advancing (reject / return-for-completion).
     * The current step stays where it is; the audit entry keeps the reason.
     */
    public function record(Transaction $trx, User $actor, string $decision, ?string $reason = null): void
    {
        if (! $this->hasWorkflow($trx)) {
            return;
        }

        $fresh = Transaction::query()->whereKey($trx->id)->lockForUpdate()->first();
        $data = $fresh->workflow_data ?? [];
        $index = ($data['current_step'] ?? 1) - 1;

        if (! isset($data['steps'][$index])) {
            return;
        }

        $data['completed'][] = [
            'step' => $data['current_step'],
            'name' => $data['steps'][$index]['name'],
            'by' => $actor->name,
            'at' => now()->toDateTimeString(),
            'decision' => $decision,
            'reason' => $reason,
        ];

        $fresh->update(['workflow_data' => $data]);
        $trx->setRawAttributes($fresh->getAttributes(), true);
    }

    /* --------------------------------------------------------- helpers */

    /** Route the transaction to the current step's target (inbox routing). */
    private function routeToCurrentStep(Transaction $trx): void
    {
        $step = $this->currentStep($trx);

        if ($step === null) {
            return;
        }

        $update = [];

        if ($step['target_type'] === 'department' && $step['target_dept']) {
            $update['dept_id'] = $step['target_dept'];
            $update['assigned_to'] = null;
        } elseif ($step['target_type'] === 'user' && $step['target_user']) {
            $update['assigned_to'] = $step['target_user'];
            $user = User::find($step['target_user']);
            if ($user?->department_id) {
                $update['dept_id'] = $user->department_id;
            }
        }
        // role targets: leave dept/assignee as-is; the role's holders act.

        if ($update !== []) {
            $trx->update($update);
        }
    }

    /** Human label of the current step for logs/notifications. */
    public function currentStepLabel(Transaction $trx): ?string
    {
        $step = $this->currentStep($trx);

        if ($step === null) {
            return null;
        }

        return $step['name'].' ('.$this->targetLabel($step).')';
    }

    private function targetLabel(array $step): string
    {
        return match ($step['target_type'] ?? '') {
            'department' => Department::find($step['target_dept'])?->name ?? 'قسم',
            'role' => Roles::label($step['target_role'] ?? null),
            'user' => User::find($step['target_user'])?->name ?? 'موظف',
            default => '—',
        };
    }

    /** Snapshot view for the details page (current step + history). */
    public function progressFor(Transaction $trx): ?array
    {
        $d = $trx->workflow_data ?? [];

        if (empty($d['total'])) {
            return null;
        }

        return [
            'current' => $d['current_step'],
            'total' => $d['total'],
            'version' => $d['version'] ?? 1,
            'steps' => collect($d['steps'])->map(fn (array $s, int $i) => [
                'name' => $s['name'],
                'target_type' => $s['target_type'],
                'target' => $this->targetLabel($s),
                'done' => $i < ($d['current_step'] - 1),
                'current' => $i === ($d['current_step'] - 1),
            ])->values()->all(),
            'completed' => $d['completed'] ?? [],
        ];
    }
}
