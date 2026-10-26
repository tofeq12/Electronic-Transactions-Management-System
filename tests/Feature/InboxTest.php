<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    /**
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

    /**
     * Decodes window.ITQAN_INBOX (the server-filtered payload).
     *
     * @return array<string, mixed>
     */
    private function inboxData(string $content): array
    {
        $this->assertSame(
            1,
            preg_match('/window\.ITQAN_INBOX = (\{.*\});/', $content, $m),
            'ITQAN_INBOX payload missing from the page.'
        );

        $data = json_decode($m[1], true);
        $this->assertIsArray($data, 'ITQAN_INBOX is not valid JSON.');

        return $data;
    }

    /**
     * @return array<int, string> transaction numbers shown in the inbox
     */
    private function idsFor(User $user, string $url): array
    {
        $response = $this->actingAs($user)->get($url)->assertOk();

        return collect($this->inboxData($response->getContent())['rows'])->pluck('id')->all();
    }

    public function test_inbox_requires_login(): void
    {
        $this->get('/inbox')->assertRedirect('/login');
    }

    public function test_inbox_shows_assigned_and_department_items_and_hides_the_rest(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $employeeA = User::factory()->create(['department_id' => $deptA->id]);
        $employeeB = User::factory()->create(['department_id' => $deptB->id]);
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);

        // In scope: forwarded to employeeA personally + pending in dept A.
        $assigned = $this->createTransaction($employeeB);
        $assigned->update(['assigned_to' => $employeeA->id]);
        $deptItem = $this->createTransaction($managerA);

        // Out of scope: another department + assigned to someone else.
        $otherDept = $this->createTransaction($employeeB);
        $assignedToB = $this->createTransaction($managerA);
        $assignedToB->update(['assigned_to' => $employeeB->id, 'dept_id' => $deptB->id]);

        $ids = $this->idsFor($employeeA, '/inbox');

        $this->assertContains($assigned->transaction_number, $ids);
        $this->assertContains($deptItem->transaction_number, $ids);
        $this->assertNotContains($otherDept->transaction_number, $ids);
        $this->assertNotContains($assignedToB->transaction_number, $ids);
    }

    public function test_manager_inbox_is_their_personal_queue_not_everything(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $managerA = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptA->id]);
        $managerB = User::factory()->create(['role' => 'dept_manager', 'department_id' => $deptB->id]);

        $mine = $this->createTransaction($managerA); // dept A queue
        $theirs = $this->createTransaction($managerB); // dept B queue

        $ids = $this->idsFor($managerA, '/inbox');

        $this->assertContains($mine->transaction_number, $ids);
        $this->assertNotContains($theirs->transaction_number, $ids);
    }

    public function test_filters_status_type_and_date_run_on_the_server(): void
    {
        $dept = Department::factory()->create();
        $employee = User::factory()->create(['department_id' => $dept->id]);
        $manager = User::factory()->create(['role' => 'dept_manager', 'department_id' => $dept->id]);

        $t1 = $this->createTransaction($manager, ['type' => 'تحويل مبلغ', 'date' => '2026/01/10']);
        $t2 = $this->createTransaction($manager, ['type' => 'شراء أجهزة', 'date' => '2026/02/15']);
        $t3 = $this->createTransaction($manager, ['type' => 'تحويل مبلغ', 'date' => '2026/03/20']);
        $t4 = $this->createTransaction($employee, ['type' => 'تحويل مبلغ']);
        // A completed transaction reaches the inbox only when personally assigned.
        $t4->update(['assigned_to' => $employee->id]);
        $this->actingAs($manager)
            ->postJson('/transactions/'.$t4->transaction_number.'/approve')
            ->assertOk();

        // Default = pending only (the action queue).
        $ids = $this->idsFor($employee, '/inbox');
        $this->assertContains($t1->transaction_number, $ids);
        $this->assertContains($t2->transaction_number, $ids);
        $this->assertContains($t3->transaction_number, $ids);
        $this->assertNotContains($t4->transaction_number, $ids);

        // status=done.
        $this->assertSame([$t4->transaction_number], $this->idsFor($employee, '/inbox?status=done'));

        // type by the Arabic label the form submits.
        $this->assertSame(
            [$t2->transaction_number],
            $this->idsFor($employee, '/inbox?type='.rawurlencode('شراء أجهزة'))
        );

        // Date range — both bounds inclusive.
        $ids = $this->idsFor($employee, '/inbox?from=2026/02/01&to=2026/03/31');
        $this->assertContains($t2->transaction_number, $ids);
        $this->assertContains($t3->transaction_number, $ids);
        $this->assertNotContains($t1->transaction_number, $ids);

        // q by transaction number.
        $this->assertSame(
            [$t3->transaction_number],
            $this->idsFor($employee, '/inbox?q='.rawurlencode($t3->transaction_number))
        );
    }

    public function test_search_and_filters_cannot_leak_transactions_outside_the_scope(): void
    {
        $deptA = Department::factory()->create();
        $deptB = Department::factory()->create();
        $employeeA = User::factory()->create(['department_id' => $deptA->id]);
        $employeeB = User::factory()->create(['department_id' => $deptB->id]);

        $secret = $this->createTransaction($employeeB, ['subject' => 'معاملة سرية جداً']);

        $urls = [
            '/inbox?q='.rawurlencode($secret->transaction_number),
            '/inbox?q='.rawurlencode('معاملة سرية جداً'),
            '/inbox?status=all&q='.rawurlencode('معاملة سرية جداً'),
            '/inbox?status=all&type='.rawurlencode('تحويل مبلغ'),
            '/inbox?status=all&from=2000/01/01&to=2099/12/31',
        ];

        foreach ($urls as $url) {
            $this->assertNotContains($secret->transaction_number, $this->idsFor($employeeA, $url), "Leaked through: $url");
        }

        // status=all widens statuses, never the scope: anything assigned to
        // me (any status) + my department's pending. Other departments stay out.
        $mine = $this->createTransaction($employeeB);
        $mine->update(['assigned_to' => $employeeA->id]);
        $ids = $this->idsFor($employeeA, '/inbox?status=all');
        $this->assertContains($mine->transaction_number, $ids);
        $this->assertNotContains($secret->transaction_number, $ids);
    }

    public function test_invalid_filter_values_are_ignored_safely(): void
    {
        $dept = Department::factory()->create();
        $employee = User::factory()->create(['department_id' => $dept->id]);
        $manager = User::factory()->create(['role' => 'dept_manager', 'department_id' => $dept->id]);

        $pending = $this->createTransaction($manager);

        // Garbage status/type/dates fall back to the default pending scope.
        $ids = $this->idsFor(
            $employee,
            '/inbox?status=DROP%20TABLE&type=غير%20موجود&from=99-99-99&to=abc'
        );

        $this->assertSame([$pending->transaction_number], $ids);
    }

    public function test_waiting_indicator_counts_pending_in_scope_and_ignores_filters(): void
    {
        $dept = Department::factory()->create();
        $employee = User::factory()->create(['department_id' => $dept->id]);
        $manager = User::factory()->create(['role' => 'dept_manager', 'department_id' => $dept->id]);
        $other = Department::factory()->create();
        $otherUser = User::factory()->create(['role' => 'dept_manager', 'department_id' => $other->id]);

        $this->createTransaction($manager);
        $this->createTransaction($manager);
        $done = $this->createTransaction($employee);
        $done->update(['assigned_to' => $employee->id]); // personally assigned => reachable when done
        $this->actingAs($manager)->postJson('/transactions/'.$done->transaction_number.'/approve')->assertOk();
        $this->createTransaction($otherUser); // out of scope

        // Even with status=done filter, the indicator counts the 2 pending.
        $response = $this->actingAs($employee)->get('/inbox?status=done')->assertOk();
        $data = $this->inboxData($response->getContent());

        $this->assertSame(2, $data['waiting']);
        $this->assertCount(1, $data['rows']);
    }

    public function test_inbox_renders_in_english_ltr(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get('/inbox')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('Inbox', false);
    }
}
