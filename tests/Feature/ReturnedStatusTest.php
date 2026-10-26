<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use App\Support\TransactionTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 6 — a dedicated "returned" status for return-for-completion.
 *
 * Before: a returned transaction went back to "draft" (indistinguishable
 * from a never-submitted draft). Now it carries its own status so the
 * submitter — and everyone watching the list — can tell it apart. Approve/
 * forward stay blocked (they require pending); the submitter edits and
 * re-sends (returned -> pending), which resumes the same workflow step.
 * No migration: transactions.type/status are VARCHAR (Phase 4) and old
 * rows that were returned earlier simply keep their "draft" value.
 */
class ReturnedStatusTest extends TestCase
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

    public function test_return_sets_the_dedicated_returned_status(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [
                'reason' => 'ناقص التوقيع',
                'missing' => 'توقيع مدير القسم',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['status' => 'returned']]);

        $this->assertSame('returned', $transaction->refresh()->status);

        // The status is a first-class citizen: known to the status catalog
        // with its own label (used by filters, badges and reports).
        $this->assertContains('returned', TransactionTypes::STATUSES);
        $this->assertSame('معادة للاكمال', TransactionTypes::statusLabel('returned'));
    }

    public function test_returned_cannot_be_approved_or_forwarded(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [
                'reason' => 'سبب', 'missing' => 'نواقص',
            ])->assertOk();

        // Every decision path requires status === pending — the policy
        // refuses with 403 before the service even runs.
        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/approve')
            ->assertForbidden();
        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/reject', ['reason' => 'سبب الرفض'])
            ->assertForbidden();
        $this->assertSame('returned', $transaction->refresh()->status);
    }

    public function test_submitter_resends_returned_and_history_records_the_difference(): void
    {
        $admin = User::factory()->admin()->create();
        // Created BEFORE the resubmission — notifications are written then.
        $otherAdmin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [
                'reason' => 'نواقص', 'missing' => 'الخطاب',
            ])->assertOk();

        // The submitter completes and re-sends through the edit flow.
        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/update', $this->payload([
                'subject' => 'بعد الاستكمال',
                'status' => 'pending',
            ]))
            ->assertOk();

        $transaction->refresh();
        $this->assertSame('pending', $transaction->status);

        // The history tells drafts and returns apart.
        $this->assertTrue(
            $transaction->logs()->where('action', 'أعاد إرسال المعاملة بعد الاستكمال')->exists(),
            'resending a RETURNED transaction is logged distinctly'
        );
        $this->assertTrue(
            $transaction->logs()->where('action', 'أعاد المعاملة إلى مقدم الطلب لاستكمال النواقص')->exists()
        );

        // Admins are told it is a return-resend, not a fresh draft (the
        // submitter themself is excluded from their own notification).
        $this->assertNotNull(
            Notification::where('user_id', $otherAdmin->id)
                ->where('transaction_id', $transaction->id)
                ->where('title', 'معادة للاكمال أُرسلت بانتظار المراجعة')
                ->first()
        );
        $this->assertNull(
            Notification::where('user_id', $admin->id)
                ->where('transaction_id', $transaction->id)
                ->where('title', 'معادة للاكمال أُرسلت بانتظار المراجعة')
                ->first(),
            'the submitter never receives their own resubmission notification'
        );
    }

    public function test_editing_without_resending_keeps_the_returned_status(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($admin);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [
                'reason' => 'نواقص', 'missing' => 'الخطاب',
            ])->assertOk();

        // Plain edit (no resubmission): fields update, status stays
        // "returned" — the transaction is still waiting on the submitter.
        $this->actingAs($admin)
            ->postJson('/transactions/'.$transaction->transaction_number.'/update', $this->payload([
                'subject' => 'تعديل بسيط',
                'status' => 'draft',
            ]))
            ->assertOk();

        $transaction->refresh();
        $this->assertSame('تعديل بسيط', $transaction->subject);
        $this->assertSame('returned', $transaction->status);
    }

    public function test_returned_status_is_visible_in_the_transactions_payload_and_filter_is_accepted(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $manager = User::factory()->create(['role' => 'dept_manager']);
        $transaction = $this->createTransaction($employee);

        $this->actingAs($manager)
            ->postJson('/transactions/'.$transaction->transaction_number.'/return', [
                'reason' => 'نواقص', 'missing' => 'الخطاب',
            ])->assertOk();

        // The submitter still sees their transaction in the payload with
        // the new status (the details/list badges read it from here).
        $this->actingAs($employee)
            ->get('/transactions')
            ->assertOk()
            ->assertSee('"status":"returned"', false);

        // The status filter accepts "returned" (no silent fallback to the
        // default pending filter) on both list surfaces.
        $this->actingAs($employee)->get('/inbox?status=returned')->assertOk();
        $this->actingAs($employee)->get('/transactions')->assertOk();
    }
}
