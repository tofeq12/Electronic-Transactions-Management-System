<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\TransactionFile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5 privacy / IDOR contract (§6):
 * - every transaction action (view, update, forward, approve, reject,
 *   delete, attachment download) is re-checked server-side against the
 *   caller's visibility boundary — not just the role
 * - lists, dashboard and notification pages only expose the caller's own
 *   data scope; there is no server search endpoint outside that scope
 * - department managers are bound to their department; a department-less
 *   manager acts at university level
 * - users cannot reach user-management or the audit log, and the audit
 *   log exposes no write endpoint at all.
 */
class PrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $over = []): array
    {
        return array_merge([
            'type' => 'تحويل مبلغ',
            'subject' => 'معاملة سرية',
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

    /* ------------------------------------- full IDOR action matrix */

    public function test_outsider_employee_cannot_touch_a_foreign_transaction(): void
    {
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create(['department_id' => Department::factory()->create()->id]);
        $t = $this->createTransaction($admin, ['subject' => 'معاملة لا يراها أحد آخر']);
        $uri = '/transactions/'.$t->transaction_number;

        // Read pages: details / edit / forward all re-check the boundary.
        $this->actingAs($outsider)->get('/transactions/details?id='.$t->transaction_number)->assertStatus(403);
        $this->actingAs($outsider)->get('/transactions/edit?id='.$t->transaction_number)->assertStatus(403);
        $this->actingAs($outsider)->get('/transactions/forward?id='.$t->transaction_number)->assertStatus(403);

        // Writes: valid payloads so the denial comes from the policy,
        // not from validation.
        $this->actingAs($outsider)
            ->postJson($uri.'/update', $this->payload(['subject' => 'محاولة تعديل']))
            ->assertStatus(403);

        $this->actingAs($outsider)
            ->postJson($uri.'/forward', ['dept_id' => Department::factory()->create()->id])
            ->assertStatus(403);

        $this->actingAs($outsider)->postJson($uri.'/approve')->assertStatus(403);

        $this->actingAs($outsider)
            ->postJson($uri.'/reject', ['reason' => 'سبب غير مقبول'])
            ->assertStatus(403);

        $this->actingAs($outsider)->postJson($uri.'/delete')->assertStatus(403);

        // Nothing changed on the record.
        $t->refresh();
        $this->assertSame('pending', $t->status);
        $this->assertSame('معاملة لا يراها أحد آخر', $t->subject);
        $this->assertDatabaseHas('transactions', ['id' => $t->id]);
    }

    public function test_transaction_lists_and_dashboard_do_not_leak_foreign_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create(['department_id' => Department::factory()->create()->id]);
        $canary = 'SECRET-CANARY-7391';
        $this->createTransaction($admin, ['subject' => $canary]);

        // Outsider: neither the transactions page nor the dashboard
        // contains the foreign record (ITQAN_DATA is scope-filtered).
        $this->actingAs($outsider)->get('/transactions')->assertOk()->assertDontSee($canary);
        $this->actingAs($outsider)->get('/dashboard')->assertOk()->assertDontSee($canary);

        // Positive control: the admin does see it (the list isn't empty).
        $this->actingAs($admin)->get('/transactions')->assertOk()->assertSee($canary);
        $this->actingAs($admin)->get('/dashboard')->assertOk()->assertSee($canary);
    }

    public function test_notifications_are_private_and_cannot_be_touched_cross_user(): void
    {
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create(['department_id' => Department::factory()->create()->id]);
        $t = $this->createTransaction($admin);

        $n = Notification::create([
            'user_id' => $admin->id,
            'transaction_id' => $t->id,
            'title' => 'CANARY-NOTIF-4411',
            'body' => 'CANARY-BODY-4411',
            'link' => '/transactions',
        ]);

        // Page content is scoped per recipient.
        $this->actingAs($outsider)->get('/notifications')->assertOk()->assertDontSee('CANARY-NOTIF-4411');
        $this->actingAs($admin)->get('/notifications')->assertOk()->assertSee('CANARY-NOTIF-4411');

        // Cross-user marking is impossible (lookup is user-scoped → 404).
        $this->actingAs($outsider)->postJson('/notifications/'.$n->id.'/read')->assertStatus(404);
        $this->assertNull($n->fresh()->read_at);

        // The owner can mark their own.
        $this->actingAs($admin)->postJson('/notifications/'.$n->id.'/read')->assertOk();
        $this->assertNotNull($n->fresh()->read_at);
    }

    public function test_attachment_download_is_denied_outside_the_visibility_boundary(): void
    {
        $admin = User::factory()->admin()->create();
        $outsider = User::factory()->create(['department_id' => Department::factory()->create()->id]);

        $payload = $this->payload(['subject' => 'مرفق خاص']);
        $payload['files'] = [UploadedFile::fake()->createWithContent('canary-plan.pdf', '%PDF-1.4 CANARY-7788')];
        $this->actingAs($admin)->postJson('/transactions', $payload)->assertOk();

        $file = TransactionFile::firstOrFail();
        Storage::disk('local')->assertExists($file->stored_path);

        $this->actingAs($outsider)->get('/files/'.$file->id.'/download')->assertStatus(403);
        $this->actingAs($admin)->get('/files/'.$file->id.'/download')->assertOk();
    }

    /* --------------------------------------- department manager scope */

    public function test_department_manager_is_scoped_to_their_department(): void
    {
        $d1 = Department::factory()->create();
        $d2 = Department::factory()->create();

        $admin = User::factory()->admin()->create();
        $manager1 = User::factory()->create(['role' => 'dept_manager', 'department_id' => $d1->id]);
        $manager2 = User::factory()->create(['role' => 'dept_manager', 'department_id' => $d2->id]);
        $managerU = User::factory()->create(['role' => 'dept_manager', 'department_id' => null]);
        $officer = User::factory()->create(['department_id' => $d1->id]);

        $t = $this->createTransaction($admin);
        $this->actingAs($admin)->postJson('/transactions/'.$t->transaction_number.'/forward', [
            'dept_id' => $d1->id,
            'user_id' => $officer->id,
            'note' => 'إحالة إلى القسم الأول',
        ])->assertOk();
        $this->assertSame($d1->id, $t->fresh()->dept_id);

        $uri = '/transactions/'.$t->transaction_number;

        // In-scope manager + university-level manager can read it.
        $this->actingAs($manager1)->get('/transactions/details?id='.$t->transaction_number)->assertOk();
        $this->actingAs($managerU)->get('/transactions/details?id='.$t->transaction_number)->assertOk();

        // Out-of-scope manager: denied on every action despite holding
        // the '*' ability — the visibility boundary is what limits them.
        $this->actingAs($manager2)->get('/transactions/details?id='.$t->transaction_number)->assertStatus(403);
        $this->actingAs($manager2)->get('/transactions/edit?id='.$t->transaction_number)->assertStatus(403);
        $this->actingAs($manager2)
            ->postJson($uri.'/update', $this->payload(['subject' => 'محاولة تعديل خارج النطاق']))
            ->assertStatus(403);
        $this->actingAs($manager2)->postJson($uri.'/approve')->assertStatus(403);
        $this->actingAs($manager2)
            ->postJson($uri.'/reject', ['reason' => 'سبب خارج النطاق'])
            ->assertStatus(403);
        $this->actingAs($manager2)->postJson($uri.'/delete')->assertStatus(403);

        // In-scope manager can decide (double-eyes rule: creator is admin).
        $this->actingAs($manager1)->postJson($uri.'/approve')->assertOk();
        $this->assertSame('done', $t->fresh()->status);

        // Delete follows the same boundary in both directions.
        $this->actingAs($manager2)->postJson($uri.'/delete')->assertStatus(403);
        $this->actingAs($manager1)->postJson($uri.'/delete')->assertOk();
        $this->assertDatabaseMissing('transactions', ['id' => $t->id]);
    }

    /* ------------------------------------------- admin surfaces */

    public function test_employee_cannot_reach_user_management_or_the_audit_log(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = User::factory()->create(['department_id' => Department::factory()->create()->id]);

        $this->actingAs($employee)->get('/dashboard/users')->assertStatus(403);
        $this->actingAs($employee)->get('/users/add')->assertStatus(403);
        $this->actingAs($employee)->get('/users/edit?id='.$admin->id)->assertStatus(403);
        $this->actingAs($employee)->postJson('/users/'.$admin->id.'/toggle')->assertStatus(403);
        $this->actingAs($employee)->postJson('/users/'.$admin->id.'/delete')->assertStatus(403);
        $this->actingAs($employee)->get('/activity-log')->assertStatus(403);

        // The admin does reach them (positive control).
        $this->actingAs($admin)->get('/dashboard/users')->assertOk();
        $this->actingAs($admin)->get('/activity-log')->assertOk();
    }

    public function test_audit_log_is_read_only(): void
    {
        $admin = User::factory()->admin()->create();

        // Only a GET route exists for the audit log — no endpoint can
        // modify or delete audit records.
        $this->actingAs($admin)->postJson('/activity-log')->assertStatus(405);
        $this->actingAs($admin)->postJson('/activity-log/1/delete')->assertStatus(404);
    }
}
