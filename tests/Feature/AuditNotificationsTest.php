<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\Notification;
use App\Models\TrxType;
use App\Models\TrxWorkflow;
use App\Models\User;
use App\Support\ItqanData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 8 — notification bulk read + a complete audit trail.
 *
 * - POST /notifications/read-all marks every UNREAD notification of the
 *   calling user only (other people's notifications are never touched).
 * - Every admin configuration action (users, departments, transaction
 *   types, dynamic fields, approval workflows and their steps) appends one
 *   activity_logs row naming the actor and the target; security-relevant
 *   parts of an edit (role/status/password reset) land in `note`.
 * - The audit payload served to the activity page carries the note column.
 */
class AuditNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function unreadFor(User $user, int $i): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'title' => 'إشعار '.$i,
            'body' => 'متن الإشعار '.$i,
            'link' => '/transactions',
        ]);
    }

    /* ---------------------------------------------------- notifications */

    public function test_mark_all_read_touches_only_my_notifications(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $this->unreadFor($a, 1);
        $this->unreadFor($a, 2);
        $otherStillOpen = $this->unreadFor($b, 3);

        $res = $this->actingAs($a)->postJson('/notifications/read-all')->assertOk();

        $this->assertSame(2, $res->json('data.count'), 'both of MY unread rows were marked');
        $this->assertNull(
            Notification::where('user_id', $a->id)->whereNull('read_at')->first(),
            'no unread left for A'
        );
        $this->assertNull($otherStillOpen->fresh()->read_at, "other people's notifications stay unread");
    }

    public function test_mark_all_read_is_idempotent(): void
    {
        $user = User::factory()->create();
        $this->unreadFor($user, 1);

        $first = $this->actingAs($user)->postJson('/notifications/read-all')->assertOk();
        $second = $this->actingAs($user)->postJson('/notifications/read-all')->assertOk();

        $this->assertSame(1, $first->json('data.count'));
        $this->assertSame(0, $second->json('data.count'), 'nothing left to mark on a second run');
    }

    /* ----------------------------------------------------------- audit */

    public function test_user_management_actions_are_audited(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/users', [
            'name' => 'موظف جديد',
            'username' => 'auditee',
            'email' => 'auditee@example.com',
            'phone' => '0512345678',
            'password' => 'pass1234',
            'role' => 'موظف',
            'status' => 'active',
        ])->assertOk();
        $user = User::where('username', 'auditee')->firstOrFail();

        $this->actingAs($admin)->postJson('/users/'.$user->id.'/update', [
            'name' => 'موظف جديد',
            'username' => 'auditee',
            'email' => 'auditee@example.com',
            'phone' => '0512345678',
            'password' => 'newpass1234',
            'role' => 'موظف',
            'status' => 'active',
        ])->assertOk();

        $this->actingAs($admin)->postJson('/users/'.$user->id.'/toggle')->assertOk();

        $rows = ActivityLog::where('user_id', $admin->id)->pluck('action');
        $this->assertTrue($rows->contains('إنشاء مستخدم'), 'create is audited');
        $this->assertTrue($rows->contains('تعديل مستخدم'), 'update is audited');
        $this->assertTrue($rows->contains('تعطيل مستخدم'), 'the toggle deactivated the account');

        // The security-relevant part of the edit lands in `note`.
        $note = ActivityLog::where('action', 'تعديل مستخدم')->value('note');
        $this->assertStringContainsString('إعادة تعيين كلمة المرور', (string) $note);
        $this->assertSame('auditee', ActivityLog::where('action', 'تعديل مستخدم')->value('target'));
    }

    public function test_department_type_field_and_workflow_actions_are_audited(): void
    {
        $admin = $this->admin();

        // Departments
        $this->actingAs($admin)->postJson('/departments', ['name' => 'المالية'])->assertOk();
        $dept = Department::where('name', 'المالية')->firstOrFail();
        $this->actingAs($admin)
            ->postJson('/departments/'.$dept->id.'/update', ['name' => 'المالية العامة'])
            ->assertOk();

        // Transaction type + toggle (create default is active → toggle turns it off)
        $this->actingAs($admin)->postJson('/types', ['slug' => 'audit-type', 'name_ar' => 'نوع التدقيق'])->assertOk();
        $type = TrxType::where('slug', 'audit-type')->firstOrFail();
        $this->actingAs($admin)->postJson('/types/'.$type->id.'/toggle')->assertOk();

        // Dynamic field on that type
        $this->actingAs($admin)->postJson('/types/'.$type->id.'/fields', [
            'label_ar' => 'حقل التدقيق',
            'field_type' => 'text',
        ])->assertOk();

        // Approval workflow + first step
        $this->actingAs($admin)->postJson('/workflows', ['trx_type_id' => $type->id])->assertOk();
        $wf = TrxWorkflow::where('trx_type_id', $type->id)->firstOrFail();
        $this->actingAs($admin)->postJson('/workflows/'.$wf->id.'/steps', [
            'name_ar' => 'خطوة التدقيق',
            'target_type' => 'role',
            'target_role' => 'dept_manager',
        ])->assertOk();

        $actions = ActivityLog::where('user_id', $admin->id)->pluck('action');
        foreach ([
            'إنشاء قسم', 'تعديل قسم',
            'إنشاء نوع معاملة', 'إيقاف نوع معاملة',
            'إضافة حقل ديناميكي',
            'إنشاء سير موافقات', 'إضافة خطوة سير موافقات',
        ] as $action) {
            $this->assertTrue($actions->contains($action), "missing audit row: {$action}");
        }

        // Step/field rows carry their parent as context in `note`.
        $stepNote = ActivityLog::where('action', 'إضافة خطوة سير موافقات')->value('note');
        $this->assertSame($wf->name, $stepNote);
    }

    public function test_read_all_by_employee_writes_no_audit_and_stays_scoped(): void
    {
        $employee = User::factory()->create();
        $this->unreadFor($employee, 1);

        $this->actingAs($employee)->postJson('/notifications/read-all')->assertOk();

        // Consuming your own notifications is not an admin action: nothing
        // lands in the audit trail, and nothing outside the caller changed.
        $this->assertSame(0, ActivityLog::where('user_id', $employee->id)->count());
        $this->assertSame(1, Notification::where('user_id', $employee->id)
            ->whereNotNull('read_at')->count());
    }

    public function test_activity_payload_carries_note_and_target(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/users', [
            'name' => 'موظف جديد',
            'username' => 'payloaduser',
            'email' => 'payload@example.com',
            'phone' => '0512345678',
            'password' => 'pass1234',
            'role' => 'موظف',
            'status' => 'active',
        ])->assertOk();

        $entry = collect(ItqanData::for($admin)['activity'])
            ->firstWhere('action', 'إنشاء مستخدم');

        $this->assertNotNull($entry, 'the create row is served to the activity page');
        $this->assertSame('payloaduser', $entry['target']);
        $this->assertSame($admin->name, $entry['by'], 'the actor name rides along');
    }
}
