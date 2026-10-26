<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentAndAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Decodes window.ITQAN_DATA from a rendered page so assertions run on
     * the real values (the payload Unicode-escapes Arabic for JS safety).
     *
     * @return array<string, mixed>
     */
    private function itqanData(string $content): array
    {
        $this->assertSame(
            1,
            preg_match('/window\.ITQAN_DATA = (\{.*\});/', $content, $m),
            'ITQAN_DATA payload missing from the page.'
        );

        $data = json_decode($m[1], true);
        $this->assertIsArray($data, 'ITQAN_DATA is not valid JSON.');

        return $data;
    }

    public function test_admin_creates_updates_and_lists_departments(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/departments', ['name' => 'المالية', 'desc' => 'شئون مالية'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $department = Department::first();
        $this->assertNotNull($department);
        $this->assertSame('المالية', $department->name);
        $this->assertSame('شئون مالية', $department->description);
        $this->assertSame($department->id, $response->json('data.id'));

        $this->actingAs($admin)
            ->postJson('/departments/'.$department->id.'/update', ['name' => 'الإدارة المالية', 'desc' => 'وصف محدث'])
            ->assertOk();

        $department->refresh();
        $this->assertSame('الإدارة المالية', $department->name);
        $this->assertSame('وصف محدث', $department->description);

        $page = $this->actingAs($admin)->get('/departments');
        $page->assertOk();
        $data = $this->itqanData($page->getContent());
        $this->assertSame('الإدارة المالية', $data['departments'][0]['name'] ?? null);
    }

    public function test_department_name_uniqueness_is_case_insensitive(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/departments', ['name' => 'HR Dept'])->assertOk();

        $this->actingAs($admin)
            ->postJson('/departments', ['name' => '  hr dept  '])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->assertSame(1, Department::count());
    }

    public function test_employee_cannot_access_department_management(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/departments')->assertStatus(403);
        $this->actingAs($employee)->get('/departments/form')->assertStatus(403);
        $this->actingAs($employee)
            ->postJson('/departments', ['name' => 'قسم'])
            ->assertStatus(403);

        $this->assertSame(0, Department::count());
    }

    public function test_department_with_transactions_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();

        $this->actingAs($admin)
            ->postJson('/transactions', [
                'type' => 'شراء أجهزة',
                'subject' => 'معاملة تمنع الحذف',
                'from' => 'الجهاز المركزي',
                'to' => 'المورد',
                'amount' => '99.00',
                'date' => date('Y/m/d'),
                'desc' => '',
                'priority' => 'normal',
                'status' => 'pending',
            ])
            ->assertOk();

        // Route the transaction into the department (as forward does).
        $transaction = \App\Models\Transaction::latest('id')->firstOrFail();
        $transaction->update(['dept_id' => $department->id]);

        $this->actingAs($admin)
            ->postJson('/departments/'.$department->id.'/delete')
            ->assertStatus(409);

        $this->assertDatabaseHas('departments', ['id' => $department->id]);
    }

    public function test_empty_department_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $department = Department::factory()->create();

        $this->actingAs($admin)
            ->postJson('/departments/'.$department->id.'/delete')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('departments', ['id' => $department->id]);
    }

    /* --------------------------------------------------------- role gates */

    public function test_employee_is_blocked_from_admin_pages(): void
    {
        $employee = User::factory()->create();

        foreach (['/dashboard/users', '/departments', '/reports', '/activity-log'] as $uri) {
            $this->actingAs($employee)->get($uri)->assertStatus(403);
        }
    }

    public function test_reviewer_can_view_reports_and_urgent_only(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->get('/reports')->assertOk();
        $this->actingAs($reviewer)->get('/transactions/urgent')->assertOk();

        foreach (['/dashboard/users', '/departments', '/activity-log', '/transactions/new'] as $uri) {
            $this->actingAs($reviewer)->get($uri)->assertStatus(403);
        }
    }

    public function test_manager_can_access_everything(): void
    {
        $manager = User::factory()->create(['role' => 'system_admin']);

        $this->actingAs($manager)->get('/dashboard/users')->assertOk();
        $this->actingAs($manager)->get('/departments')->assertOk();
        $this->actingAs($manager)->get('/reports')->assertOk();
        $this->actingAs($manager)->get('/activity-log')->assertOk();
    }

    public function test_urgent_page_is_gated_server_side_not_only_by_the_ui(): void
    {
        // Regression: /transactions/urgent had no server-side ability check.
        // Every current role holds urgent.view, so an unknown/future role
        // (no abilities) must be refused with 403.
        $noAbilities = User::factory()->create(['role' => 'intern']);

        $this->actingAs($noAbilities)->get('/transactions/urgent')->assertStatus(403);

        // Positive controls: reviewer 200 above, admin 200 in PageRoutesTest.
        $this->actingAs(User::factory()->admin()->create())->get('/transactions/urgent')->assertOk();
    }

    public function test_forward_page_requires_forward_ability_even_for_visible_transactions(): void
    {
        // A reviewer CAN view a transaction they created (view() boundary)
        // but holds no trx.forward ability — TransactionPolicy::forwardPage
        // must refuse with 403 (ability + visibility are separate checks).
        $reviewer = User::factory()->reviewer()->create();
        $visible = \App\Models\Transaction::factory()->create(['created_by' => $reviewer->id]);

        $this->actingAs($reviewer)
            ->get('/transactions/forward?id='.$visible->transaction_number)
            ->assertStatus(403);

        // Positive control: an employee (trx.forward holder) opens the page
        // for their own transaction.
        $employee = User::factory()->create();
        $own = \App\Models\Transaction::factory()->create(['created_by' => $employee->id]);

        $this->actingAs($employee)
            ->get('/transactions/forward?id='.$own->transaction_number)
            ->assertOk();
    }
}
