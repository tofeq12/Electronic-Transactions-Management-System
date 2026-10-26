<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\TrxType;
use App\Models\TrxWorkflow;
use App\Models\User;
use App\Services\WorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Phase 5 — configurable multi-step approval workflows.
 *
 * - Submission attaches an immutable snapshot (version + steps) and routes
 *   to the first step's target.
 * - Approving a non-final step advances + re-routes and STAYS pending; only
 *   the final step sets done.
 * - Reject is final at any step; return-for-completion keeps the step so
 *   resumption continues where it stopped.
 * - Editing the workflow mid-flight never re-paths existing transactions.
 * - Types without an active workflow keep the legacy single-step behaviour.
 */
class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------------ setup */

    private function payload(array $over = []): array
    {
        return array_merge([
            'type' => 'scholarship',
            'subject' => 'طلب منح دراسية',
            'from' => 'قسم القبول',
            'to' => 'عمادة الدراسات',
            'amount' => '1500.00',
            'date' => date('Y/m/d'),
            'desc' => '',
            'priority' => 'normal',
            'status' => 'pending',
        ], $over);
    }

    /** @return array{0: Department, 1: Department} */
    private function twoDepts(): array
    {
        return [
            Department::factory()->create(['name' => 'قسم أ']),
            Department::factory()->create(['name' => 'قسم ب']),
        ];
    }

    private function createTrxType(string $slug = 'scholarship'): TrxType
    {
        return TrxType::create([
            'slug' => $slug,
            'name_ar' => 'منح دراسية',
            'name_en' => 'Scholarship',
            'is_active' => true,
        ]);
    }

    /** Create an active workflow with ordered steps as the admin would. */
    private function makeWorkflow(TrxType $type, array $steps, User $admin): TrxWorkflow
    {
        $wf = TrxWorkflow::create([
            'trx_type_id' => $type->id,
            'name' => 'سير '.$type->name_ar,
            'is_active' => true,
            'version' => 1,
            'created_by' => $admin->id,
        ]);

        $order = 1;
        foreach ($steps as $s) {
            $wf->steps()->create($s + ['step_order' => $order++]);
        }

        return $wf;
    }

    private function submit(User $actor, array $over = []): Transaction
    {
        $this->actingAs($actor)->postJson('/transactions', $this->payload($over))->assertOk();

        return Transaction::latest('id')->firstOrFail();
    }

    /* ------------------------------------------------- access control */

    public function test_workflow_endpoints_require_workflows_manage(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $type = $this->createTrxType();
        $wf = $this->makeWorkflow($type, [['name_ar' => 'خطوة 1', 'target_type' => 'role', 'target_role' => 'dept_manager']], $employee);
        $step = $wf->steps()->firstOrFail();

        $this->actingAs($employee)
            ->postJson('/workflows', ['trx_type_id' => $type->id])->assertForbidden();
        $this->actingAs($employee)
            ->postJson('/workflows/'.$wf->id.'/update', ['name' => 'x'])->assertForbidden();
        $this->actingAs($employee)
            ->postJson('/workflows/'.$wf->id.'/toggle')->assertForbidden();
        $this->actingAs($employee)
            ->postJson('/workflows/'.$wf->id.'/delete')->assertForbidden();
        $this->actingAs($employee)
            ->postJson('/workflows/'.$wf->id.'/steps', ['name_ar' => 's', 'target_type' => 'role', 'target_role' => 'dept_manager'])->assertForbidden();
        $this->actingAs($employee)
            ->postJson('/workflow-steps/'.$step->id.'/update', ['name_ar' => 's2', 'target_type' => 'role', 'target_role' => 'dept_manager'])->assertForbidden();
        $this->actingAs($employee)
            ->postJson('/workflow-steps/'.$step->id.'/delete')->assertForbidden();
    }

    /* ------------------------------------------------ configuration CRUD */

    public function test_admin_creates_workflow_with_steps_and_version_bumps(): void
    {
        $admin = User::factory()->admin()->create();
        [$deptA] = $this->twoDepts();
        $type = $this->createTrxType();

        $this->actingAs($admin)->postJson('/workflows', ['trx_type_id' => $type->id])->assertOk();
        $wf = TrxWorkflow::where('trx_type_id', $type->id)->firstOrFail();
        $this->assertSame(1, $wf->version);

        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/steps', [
            'name_ar' => 'مراجعة القسم', 'target_type' => 'department', 'target_dept_id' => $deptA->id,
        ])->assertOk();
        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/steps', [
            'name_ar' => 'اعتماد المدير', 'target_type' => 'role', 'target_role' => 'dept_manager',
        ])->assertOk();

        $this->assertSame(3, $wf->fresh()->version);
        $this->assertSame(
            [1, 2],
            $wf->steps()->orderBy('step_order')->pluck('step_order')->map(fn ($v) => (int) $v)->all()
        );
        // steps() already orders ASC — take the collection's last entry.
        $this->assertSame('dept_manager', $wf->steps()->get()->last()->target_role);

        // Arabic labels normalize to slugs too (same as the user form).
        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/steps', [
            'name_ar' => 'مراجعة عامة', 'target_type' => 'role', 'target_role' => 'مراجع',
        ])->assertOk();
        $this->assertSame('reviewer', $wf->steps()->get()->last()->target_role);
    }

    public function test_one_workflow_per_type_and_step_target_validation(): void
    {
        $admin = User::factory()->admin()->create();
        $type = $this->createTrxType();
        $this->makeWorkflow($type, [], $admin);

        // Second workflow for the same type → 422.
        $this->actingAs($admin)
            ->postJson('/workflows', ['trx_type_id' => $type->id])->assertStatus(422);

        $wf = TrxWorkflow::where('trx_type_id', $type->id)->firstOrFail();

        // department target without a department → 422.
        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/steps', [
            'name_ar' => 'س', 'target_type' => 'department',
        ])->assertStatus(422);

        // Unknown role → 422.
        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/steps', [
            'name_ar' => 'س', 'target_type' => 'role', 'target_role' => 'ghost-role',
        ])->assertStatus(422);

        $this->assertSame(0, $wf->steps()->count());
    }

    public function test_step_delete_renumbers_and_bumps_version(): void
    {
        $admin = User::factory()->admin()->create();
        $type = $this->createTrxType();
        $wf = $this->makeWorkflow($type, [
            ['name_ar' => 'أ', 'target_type' => 'role', 'target_role' => 'dept_manager'],
            ['name_ar' => 'ب', 'target_type' => 'role', 'target_role' => 'dept_manager'],
            ['name_ar' => 'ج', 'target_type' => 'role', 'target_role' => 'dept_manager'],
        ], $admin);
        $middle = $wf->steps()->orderBy('step_order')->skip(1)->first();

        $this->actingAs($admin)->postJson('/workflow-steps/'.$middle->id.'/delete')->assertOk();

        $this->assertSame([1, 2], $wf->steps()->orderBy('step_order')->pluck('step_order')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('أ', $wf->steps()->orderBy('step_order')->first()->name_ar);
        $this->assertGreaterThan(1, $wf->fresh()->version);
    }

    public function test_active_workflow_with_steps_cannot_be_deleted_until_deactivated(): void
    {
        $admin = User::factory()->admin()->create();
        $type = $this->createTrxType();
        $wf = $this->makeWorkflow($type, [
            ['name_ar' => 'أ', 'target_type' => 'role', 'target_role' => 'dept_manager'],
        ], $admin);

        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/delete')->assertStatus(409);

        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/toggle')->assertOk();
        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/delete')->assertOk();

        $this->assertDatabaseMissing('trx_workflows', ['id' => $wf->id]);
        $this->assertDatabaseMissing('trx_workflow_steps', ['workflow_id' => $wf->id]);
    }

    /* ------------------------------------------------ submission snapshot */

    public function test_submission_attaches_snapshot_and_routes_to_first_step(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $managerB = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptB->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $managerA);

        $trx = $this->submit($employee);

        $d = $trx->workflow_data;
        $this->assertNotNull($d, 'snapshot must be attached on submission');
        $this->assertSame(1, $d['current_step']);
        $this->assertSame(2, $d['total']);
        $this->assertSame([], $d['completed']);
        $this->assertCount(2, $d['steps']);
        $this->assertSame($deptA->id, $trx->dept_id, 'routed to step 1 department');
        $this->assertNotNull(\App\Models\Notification::where('user_id', $managerA->id)->where('transaction_id', $trx->id)->first());
    }

    public function test_non_final_approval_advances_reroutes_and_stays_pending(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $managerB = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptB->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $managerA);

        $trx = $this->submit($employee);

        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();

        $trx->refresh();
        $this->assertSame('pending', $trx->status, 'non-final approval must keep the transaction pending');
        $this->assertSame(2, $trx->workflow_data['current_step']);
        $this->assertCount(1, $trx->workflow_data['completed']);
        $this->assertSame('approve', $trx->workflow_data['completed'][0]['decision']);
        $this->assertSame($deptB->id, $trx->dept_id, 're-routed to step 2 department');
        $this->assertTrue(
            \App\Models\TransactionLog::where('transaction_id', $trx->id)->where('action', 'like', 'اعتماد خطوة 1/2%')->exists()
        );
        $this->assertNotNull(\App\Models\Notification::where('user_id', $managerB->id)->where('transaction_id', $trx->id)->first());
    }

    public function test_only_final_step_sets_done(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $managerB = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptB->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $managerA);

        $trx = $this->submit($employee);

        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();
        $this->assertSame('pending', $trx->fresh()->status);

        $this->actingAs($managerB)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();

        $trx->refresh();
        $this->assertSame('done', $trx->status);
        $this->assertCount(2, $trx->workflow_data['completed']);
    }

    /* ------------------------------------------------------ step targeting */

    public function test_wrong_department_manager_cannot_advance_other_step(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $managerB = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptB->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $managerA);

        $trx = $this->submit($employee);

        // Step 1 (dept A): managerB must be blocked.
        $this->actingAs($managerB)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertForbidden();

        // Advance to step 2, then managerA must be blocked at the engine level.
        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();

        $svc = app(WorkflowService::class);
        $this->assertFalse($svc->allowsStep($trx->fresh(), $managerA), 'managerA does not match step 2');
        $this->assertTrue($svc->allowsStep($trx->fresh(), $managerB), 'managerB matches step 2');

        try {
            $svc->advance($trx->fresh(), $managerA, 'approve');
            $this->fail('advance() must reject a non-matching actor');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame('pending', $trx->fresh()->status);
    }

    public function test_role_targeted_step_matches_any_holder_and_user_target_matches_one(): void
    {
        $type = $this->createTrxType();
        [$deptA] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $managerOther = User::factory()->create(['role' => 'dept_manager']); // no dept
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);
        $reviewer = User::factory()->create(['role' => 'reviewer']);

        $this->makeWorkflow($type, [
            ['name_ar' => 'اعتماد المديرين', 'target_type' => 'role', 'target_role' => 'dept_manager'],
        ], $managerA);

        $trx = $this->submit($employee);

        // Any dept_manager (scoped or not) matches a role step.
        $this->assertTrue(app(WorkflowService::class)->allowsStep($trx, $managerOther));
        $this->assertFalse(app(WorkflowService::class)->allowsStep($trx, $reviewer));

        // Final role step → approve sets done.
        $this->actingAs($managerOther)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();
        $this->assertSame('done', $trx->fresh()->status);
    }

    public function test_self_approval_is_still_blocked_on_a_workflow_step(): void
    {
        $type = $this->createTrxType();
        [$deptA] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'اعتماد المديرين', 'target_type' => 'role', 'target_role' => 'dept_manager'],
        ], $managerA);

        // ManagerA submits their own transaction of the workflow type.
        $trx = $this->submit($managerA);

        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertForbidden();
    }

    public function test_admin_keeps_universal_authority_at_any_step(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $admin = User::factory()->admin()->create();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $managerA);

        $trx = $this->submit($employee);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();

        $trx->refresh();
        $this->assertSame('pending', $trx->status);
        $this->assertSame(2, $trx->workflow_data['current_step'], 'admin approval advances even when not the step target');
    }

    /* --------------------------------------------------------- decisions */

    public function test_reject_is_final_at_any_step_and_records_the_decision(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $managerA);

        $trx = $this->submit($employee);

        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/reject', ['reason' => 'غير مكتمل'])
            ->assertOk();

        $trx->refresh();
        $this->assertSame('rejected', $trx->status);
        $data = $trx->workflow_data;
        $this->assertSame(1, $data['current_step'], 'reject does not advance');
        $last = end($data['completed']);
        $this->assertSame('reject', $last['decision']);
        $this->assertSame('غير مكتمل', $last['reason']);
    }

    public function test_return_for_completion_keeps_step_and_resumption_continues_same_step(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $managerB = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptB->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $managerA);

        $trx = $this->submit($employee);

        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/return', [
                'reason' => 'أرفق المستند',
                'missing' => 'خطاب القبول',
            ])
            ->assertOk();

        $trx->refresh();
        $this->assertSame('returned', $trx->status);
        $data = $trx->workflow_data;
        $this->assertSame(1, $data['current_step'], 'return keeps the same step');
        $last = end($data['completed']);
        $this->assertSame('return', $last['decision']);

        // Employee fixes and resubmits.
        $this->actingAs($employee)
            ->postJson('/transactions/'.$trx->transaction_number.'/update', $this->payload(['status' => 'pending']))
            ->assertOk();

        $trx->refresh();
        $this->assertSame('pending', $trx->status);
        $this->assertSame(1, $trx->workflow_data['current_step'], 'resumption resumes at the SAME step');
        $this->assertSame(2, $trx->workflow_data['total']);
        $this->assertSame($deptA->id, $trx->dept_id);

        // Approvals now complete the same path.
        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();
        $this->actingAs($managerB)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();
        $this->assertSame('done', $trx->fresh()->status);
    }

    /* -------------------------------------------------- snapshot immutability */

    public function test_snapshot_is_immutable_when_admin_edits_workflow_mid_flight(): void
    {
        $type = $this->createTrxType();
        [$deptA, $deptB] = $this->twoDepts();
        $admin = User::factory()->admin()->create();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $wf = $this->makeWorkflow($type, [
            ['name_ar' => 'مراجعة قسم أ', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
            ['name_ar' => 'اعتماد قسم ب', 'target_type' => 'department', 'target_dept_id' => $deptB->id],
        ], $admin);

        $trx = $this->submit($employee);
        $snapshotVersion = $trx->workflow_data['version'];
        // The helper writes steps directly, so the version stays at 1 until
        // the controller bumps it — the snapshot carries whatever was current.
        $this->assertSame($wf->fresh()->version, $snapshotVersion);

        // Admin adds a third step → version bumps, in-flight snapshot untouched.
        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/steps', [
            'name_ar' => 'خطوة إضافية', 'target_type' => 'role', 'target_role' => 'dept_manager',
        ])->assertOk();
        $this->assertGreaterThan($snapshotVersion, $wf->fresh()->version, 'editing bumps the workflow version');

        $trx->refresh();
        $this->assertSame(2, $trx->workflow_data['total'], 'in-flight snapshot keeps its own step count');
        $this->assertSame($snapshotVersion, $trx->workflow_data['version']);

        // The in-flight transaction completes on its own (2-step) path —
        // the added third step never joins this transaction.
        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();
        $this->assertSame('pending', $trx->fresh()->status);
        $managerB = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptB->id]);
        $this->actingAs($managerB)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();
        $this->assertSame('done', $trx->fresh()->status);
    }

    public function test_deactivated_workflow_stops_new_snapshots_but_not_in_flight(): void
    {
        $type = $this->createTrxType();
        [$deptA] = $this->twoDepts();
        $admin = User::factory()->admin()->create();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $wf = $this->makeWorkflow($type, [
            ['name_ar' => 'اعتماد المديرين', 'target_type' => 'department', 'target_dept_id' => $deptA->id],
        ], $admin);

        $inFlight = $this->submit($employee);

        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/toggle')->assertOk();

        $new = $this->submit($employee);
        $this->assertNull($new->workflow_data, 'deactivated workflow must not attach snapshots');
        $this->assertNotNull($inFlight->fresh()->workflow_data, 'in-flight transactions keep their snapshot');

        // Legacy behaviour: immediate done.
        $this->actingAs($managerA)
            ->postJson('/transactions/'.$new->transaction_number.'/approve')->assertOk();
        $this->assertSame('done', $new->fresh()->status);
    }

    /* ------------------------------------------------- legacy compatibility */

    public function test_type_without_workflow_keeps_single_step_behaviour(): void
    {
        $type = $this->createTrxType();
        [$deptA] = $this->twoDepts();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $employee = User::factory()->create(['role' => 'employee', 'department_id' => $deptA->id]);

        $trx = $this->submit($employee);

        $this->assertNull($trx->workflow_data);

        $this->actingAs($managerA)
            ->postJson('/transactions/'.$trx->transaction_number.'/approve')->assertOk();
        $this->assertSame('done', $trx->fresh()->status);
        $this->assertNull($trx->fresh()->workflow_data);
    }

    public function test_workflow_data_column_is_nullable_and_preserves_legacy_rows(): void
    {
        // A transaction created without any workflow carries NULL — the
        // additive column never breaks reads of pre-existing rows.
        $this->createTrxType();
        $employee = User::factory()->create(['role' => 'employee']);
        $trx = $this->submit($employee);

        $this->assertNull($trx->workflow_data);
        $this->assertTrue(\Schema::hasColumn('transactions', 'workflow_data'));

        $col = collect(DB::select("PRAGMA table_info('transactions')"))->firstWhere('name', 'workflow_data');
        $this->assertNotNull($col, 'workflow_data column must exist');
        $this->assertSame(0, (int) $col->notnull, 'the additive column is nullable for legacy rows');
    }
}
