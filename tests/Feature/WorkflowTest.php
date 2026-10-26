<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\TransactionLog;
use App\Models\TransactionTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $over
     * @return array<string, mixed>
     */
    private function payload(array $over = []): array
    {
        return array_merge([
            'type' => 'تحويل مبلغ',
            'subject' => 'معاملة للاختبار',
            'from' => 'الخزينة',
            'to' => 'البنك',
            'amount' => '100.00',
            'date' => date('Y/m/d'),
            'desc' => '',
            'priority' => 'normal',
            'status' => 'pending',
        ], $over);
    }

    private function createTransaction(User $actor, array $over = []): Transaction
    {
        $this->actingAs($actor)->postJson('/transactions', $this->payload($over))->assertOk();

        return Transaction::latest('id')->firstOrFail();
    }

    public function test_forward_records_history_log_notification_and_activity(): void
    {
        $admin = User::factory()->admin()->create();
        $targetDept = Department::factory()->create();
        $targetUser = User::factory()->create(['department_id' => $targetDept->id]);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/forward', [
                'dept_id' => $targetDept->id,
                'user_id' => $targetUser->id,
                'note' => 'يُرجى المتابعة',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $transaction->refresh();
        $this->assertSame($targetDept->id, $transaction->dept_id);
        $this->assertSame($targetUser->id, $transaction->assigned_to);

        // Forwarding history
        $this->assertDatabaseHas('transaction_transfers', [
            'transaction_id' => $transaction->id,
            'to_user_id' => $targetUser->id,
            'to_dept_id' => $targetDept->id,
            'note' => 'يُرجى المتابعة',
        ]);

        // Per-transaction log + activity log
        $this->assertTrue(
            TransactionLog::where('transaction_id', $transaction->id)
                ->where('action', 'like', 'تمت إحالة المعاملة إلى %')->exists()
        );
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'إحالة معاملة',
            'target' => $transaction->transaction_number,
        ]);

        // Notification went to the target, not to the actor
        $this->assertDatabaseHas('notifications', [
            'user_id' => $targetUser->id,
            'transaction_id' => $transaction->id,
        ]);
        $this->assertSame(0, Notification::where('user_id', $admin->id)->count());
    }

    public function test_forward_rejected_for_non_pending_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $dept = Department::factory()->create();
        $transaction = $this->createTransaction($admin, ['status' => 'draft']);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/forward', [
                'dept_id' => $dept->id,
            ])
            ->assertStatus(403);

        $this->assertSame(0, TransactionTransfer::count());
    }

    public function test_forward_requires_existing_department(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->createTransaction($admin);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/forward', [
                'dept_id' => 99999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('dept_id');
    }

    public function test_approve_sets_done_and_notifies_the_creator(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $transaction = $this->createTransaction($employee);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/approve')
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['status' => 'done']]);

        $this->assertSame('done', $transaction->refresh()->status);
        $this->assertDatabaseHas('transaction_logs', [
            'transaction_id' => $transaction->id,
            'action' => 'تمت الموافقة',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $employee->id,
            'transaction_id' => $transaction->id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'موافقة على معاملة',
            'target' => $transaction->transaction_number,
        ]);
    }

    public function test_reject_sets_rejected_and_notifies_the_creator(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $transaction = $this->createTransaction($employee);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/reject', [
                'reason' => 'المستندات غير مكتملة',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['status' => 'rejected']]);

        $this->assertSame('rejected', $transaction->refresh()->status);

        // The required reason is kept in the transaction log (review history) …
        $this->assertTrue(
            TransactionLog::where('transaction_id', $transaction->id)
                ->where('action', 'تم رفض المعاملة')
                ->where('note', 'المستندات غير مكتملة')
                ->exists()
        );
        // … and inside the creator's notification.
        $this->assertDatabaseHas('notifications', ['user_id' => $employee->id]);
        $this->assertTrue(
            Notification::where('user_id', $employee->id)
                ->where('body', 'like', '%المستندات غير مكتملة%')
                ->exists()
        );
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'رفض معاملة',
        ]);
    }

    public function test_reject_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $transaction = $this->createTransaction($employee);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/reject')
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/reject', ['reason' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->assertSame('pending', $transaction->refresh()->status);
    }

    public function test_approve_rejected_when_not_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create();
        $transaction = $this->createTransaction($employee);
        $this->actingAs($admin)->postJson('/transactions/'.$transaction->transaction_number.'/approve')->assertOk();

        // Second approve: already done → policy refuses (403).
        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/approve')
            ->assertStatus(403);
    }

    public function test_creator_cannot_approve_or_reject_own_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->createTransaction($admin); // the admin created it themselves

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/approve')
            ->assertStatus(403);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/reject', [
                'reason' => 'سبب غير مقبول',
            ])
            ->assertStatus(403);

        $this->assertSame('pending', $transaction->refresh()->status);
    }

    public function test_forward_to_self_is_blocked(): void
    {
        $admin = User::factory()->admin()->create();
        $dept = Department::factory()->create();
        $transaction = $this->createTransaction($admin);

        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/forward', [
                'dept_id' => $dept->id,
                'user_id' => $admin->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('user_id');

        $this->assertSame(0, TransactionTransfer::count());
        $this->assertNull($transaction->refresh()->assigned_to);
    }

    public function test_employee_cannot_approve_or_reject(): void
    {
        $employee = User::factory()->create();
        $transaction = $this->createTransaction($employee);

        $this->actingAs($employee)
            ->postJson('/transactions/'.$transaction->transaction_number.'/approve')
            ->assertStatus(403);

        $this->actingAs($employee)
            ->postJson('/transactions/'.$transaction->transaction_number.'/reject', [
                'reason' => 'سبب تجريبي',
            ])
            ->assertStatus(403);

        $this->assertSame('pending', $transaction->refresh()->status);
    }

    public function test_employee_data_boundary_hides_other_department_transactions(): void
    {
        $admin = User::factory()->admin()->create();
        $employeeB = User::factory()->create([
            'department_id' => Department::factory()->create()->id,
        ]);
        $transaction = $this->createTransaction($admin); // admin has no department → dept_id null

        // Admin sees it.
        $this->actingAs($admin)->get('/transactions')->assertOk()->assertSee($transaction->transaction_number);

        // Unrelated employee does not.
        $this->actingAs($employeeB)->get('/transactions')->assertOk()->assertDontSee($transaction->transaction_number);
        $this->actingAs($employeeB)
            ->get('/transactions/details?id='.$transaction->transaction_number)
            ->assertStatus(403);

        // After being forwarded to them, it becomes visible.
        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/forward', [
                'dept_id' => $employeeB->department_id,
                'user_id' => $employeeB->id,
            ])
            ->assertOk();

        $this->actingAs($employeeB)
            ->get('/transactions')
            ->assertOk()
            ->assertSee($transaction->transaction_number);
    }

    public function test_delete_removes_the_transaction_and_keeps_the_audit_trail(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->createTransaction($admin);
        $number = $transaction->transaction_number;

        $this->actingAs($admin)
            ->postJson('/transactions/'.$number.'/delete')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('transactions', ['transaction_number' => $number]);
        // The activity log keeps the record of the deletion.
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'حذف معاملة',
            'target' => $number,
        ]);
    }

    public function test_employee_cannot_delete_transactions(): void
    {
        $employee = User::factory()->create();
        $transaction = $this->createTransaction($employee);

        $this->actingAs($employee)
            ->postJson('/transactions/'.$transaction->transaction_number.'/delete')
            ->assertStatus(403);

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    /* ------------------------------------- return for completion (Phase 2) */

    public function test_return_requires_reason_and_missing_items(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason', 'missing']);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', ['reason' => 'المرفقات ناقصة'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('missing');

        $this->assertSame('pending', $transaction->refresh()->status);
    }

    public function test_manager_returns_pending_transaction_with_full_record_and_submitter_notification(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [
                'reason' => 'المرفقات غير مكتملة',
                'missing' => 'خطاب الجهة المالكة وتوقيع مدير القسم',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['status' => 'returned']]);

        $this->assertSame('returned', $transaction->refresh()->status);

        // The record: executor + reason + missing items + timestamp.
        $log = TransactionLog::where('transaction_id', $transaction->id)
            ->where('action', 'أعاد المعاملة إلى مقدم الطلب لاستكمال النواقص')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($manager->id, $log->user_id);
        $this->assertStringContainsString('المرفقات غير مكتملة', (string) $log->note);
        $this->assertStringContainsString('خطاب الجهة المالكة وتوقيع مدير القسم', (string) $log->note);
        $this->assertNotNull($log->created_at);

        // The submitter is notified with the reason.
        $notification = Notification::where('user_id', $admin->id)
            ->where('transaction_id', $transaction->id)
            ->where('title', 'المعاملة تحتاج استكمالاً')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('المرفقات غير مكتملة', $notification->body);
        $this->assertSame('/transactions/details?id='.$transaction->transaction_number, $notification->link);
        $this->assertNull($notification->read_at);

        // The global activity log records who performed the action.
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $manager->id,
            'action' => 'إعادة معاملة لاستكمال',
            'target' => $transaction->transaction_number,
        ]);
    }

    public function test_return_is_denied_without_rights_for_own_transaction_and_for_non_pending(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $employee = User::factory()->create();
        $transaction = $this->createTransaction($admin);

        $body = ['reason' => 'سبب الإعادة', 'missing' => 'النواقص المطلوبة'];

        // An employee has no approve/return rights.
        $this->actingAs($employee)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', $body)
            ->assertStatus(403);

        // A manager can never return their own transaction.
        $own = $this->createTransaction($manager);
        $this->actingAs($manager)
            ->postJson('/transactions/'.$own->transaction_number.'/return', $body)
            ->assertStatus(403);

        // Not available for non-pending transactions — the policy refuses
        // with 403 first (same convention as the forward tests).
        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/approve')
            ->assertOk();
        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', $body)
            ->assertStatus(403);

        $this->assertSame('done', $transaction->refresh()->status);
        $this->assertSame('pending', $own->refresh()->status);
    }

    public function test_submitter_edits_and_resends_after_return_and_history_is_kept(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [
                'reason' => 'ناقص توقيع مدير القسم',
                'missing' => 'توقيع مدير القسم على الخطاب',
            ])
            ->assertOk();

        $logsBeforeResend = $transaction->logs()->count();

        // The submitter completes the items and re-sends
        // (returned -> pending through the existing edit flow).
        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/update', $this->payload([
                'subject' => 'معاملة بعد استكمال النواقص',
                'status' => 'pending',
            ]))
            ->assertOk();

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);
        $this->assertSame('معاملة بعد استكمال النواقص', $transaction->subject);

        // Full history kept + the re-send was recorded on top of it.
        $this->assertGreaterThanOrEqual($logsBeforeResend + 1, $transaction->logs()->count());
        $this->assertTrue(
            $transaction->logs()->where('action', 'أعاد المعاملة إلى مقدم الطلب لاستكمال النواقص')->exists()
        );
        $this->assertTrue($transaction->logs()->where('action', 'أعاد إرسال المعاملة بعد الاستكمال')->exists());

        // It continues its normal path: a manager can approve it again.
        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/approve')
            ->assertOk();
        $this->assertSame('done', $transaction->refresh()->status);
    }

    public function test_details_page_renders_the_return_button_with_manager_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $transaction = $this->createTransaction($admin);

        $this->actingAs($admin)
            ->get('/transactions/details?id='.$transaction->transaction_number)
            ->assertOk()
            ->assertSee('data-action="trx-return"', false);
    }
}
