<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 11 — organization structure: colleges management + the
 * department↔college assignment.
 *
 * - College CRUD lives behind departments.* (same gate family as
 *   departments); every action is audited.
 * - A college is never deleted while departments or users still point at
 *   it (409) — no assignment is silently orphaned.
 * - Departments accept an optional validated college_id, and the page
 *   payload carries the assignment both ways (colleges + collegeId).
 */
class CollegeManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /* ------------------------------------------------------------ gates */

    public function test_employee_cannot_manage_colleges(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $college = College::factory()->create();

        $this->actingAs($employee)->postJson('/colleges', ['name' => 'كلية'])->assertForbidden();
        $this->actingAs($employee)->postJson('/colleges/'.$college->id.'/update', ['name' => 'ك2'])->assertForbidden();
        $this->actingAs($employee)->postJson('/colleges/'.$college->id.'/delete')->assertForbidden();
    }

    /* ------------------------------------------------------------- CRUD */

    public function test_admin_creates_rename_and_deletes_a_college_with_audit(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->postJson('/colleges', ['name' => '  كلية الهندسة '])->assertOk();
        $college = College::where('name', 'كلية الهندسة')->firstOrFail();

        $this->actingAs($admin)
            ->postJson('/colleges/'.$college->id.'/update', ['name' => 'كلية الهندسة والتقنية'])
            ->assertOk();
        $this->assertSame('كلية الهندسة والتقنية', $college->fresh()->name);

        $this->actingAs($admin)->postJson('/colleges/'.$college->id.'/delete')->assertOk();
        $this->assertNull(College::find($college->id));

        $actions = ActivityLog::where('user_id', $admin->id)->pluck('action');
        $this->assertTrue($actions->contains('إنشاء كلية'));
        $this->assertTrue($actions->contains('تعديل كلية'));
        $this->assertTrue($actions->contains('حذف كلية'));
    }

    public function test_duplicate_names_are_rejected_case_insensitively(): void
    {
        $admin = $this->admin();
        $college = College::factory()->create(['name' => 'كلية العلوم']);

        $this->actingAs($admin)->postJson('/colleges', ['name' => 'كلية العلوم'])
            ->assertStatus(422);
        $this->actingAs($admin)->postJson('/colleges', ['name' => 'كلية  العلوم'])
            ->assertStatus(422);

        // Editing itself to the same name is fine; to another's name is not.
        $other = College::factory()->create(['name' => 'كلية الطب']);
        $this->actingAs($admin)->postJson('/colleges/'.$college->id.'/update', ['name' => 'كلية العلوم'])->assertOk();
        $this->actingAs($admin)->postJson('/colleges/'.$college->id.'/update', ['name' => 'كلية الطب'])->assertStatus(422);
    }

    public function test_college_with_references_is_never_deleted(): void
    {
        $admin = $this->admin();

        // A department points at the college…
        $withDept = College::factory()->create(['name' => 'كلية بها أقسام']);
        Department::factory()->create(['college_id' => $withDept->id]);
        $this->actingAs($admin)->postJson('/colleges/'.$withDept->id.'/delete')->assertStatus(409);
        $this->assertNotNull($withDept->fresh(), 'the college stays');

        // …and a user points at another.
        $withUser = College::factory()->create(['name' => 'كلية بها مستخدمون']);
        User::factory()->create(['college_id' => $withUser->id]);
        $this->actingAs($admin)->postJson('/colleges/'.$withUser->id.'/delete')->assertStatus(409);
        $this->assertNotNull($withUser->fresh());
    }

    /* --------------------------------------------- department linkage */

    public function test_department_accepts_and_changes_its_college(): void
    {
        $admin = $this->admin();
        $collegeA = College::factory()->create(['name' => 'كلية أ']);
        $collegeB = College::factory()->create(['name' => 'كلية ب']);

        $this->actingAs($admin)->postJson('/departments', [
            'name' => 'قسم مرتبط',
            'college_id' => $collegeA->id,
        ])->assertOk();
        $dept = Department::where('name', 'قسم مرتبط')->firstOrFail();
        $this->assertSame($collegeA->id, $dept->college_id);

        $this->actingAs($admin)->postJson('/departments/'.$dept->id.'/update', [
            'name' => 'قسم مرتبط',
            'college_id' => $collegeB->id,
        ])->assertOk();
        $this->assertSame($collegeB->id, $dept->fresh()->college_id);

        // Dropping the assignment entirely is allowed (nullable).
        $this->actingAs($admin)->postJson('/departments/'.$dept->id.'/update', [
            'name' => 'قسم مرتبط',
        ])->assertOk();
        $this->assertNull($dept->fresh()->college_id);

        // A bogus college id is a validation error, not a crash.
        $this->actingAs($admin)->postJson('/departments', [
            'name' => 'قسم آخر',
            'college_id' => 99999,
        ])->assertStatus(422);
    }

    public function test_page_payload_carries_both_sides_of_the_link(): void
    {
        $admin = $this->admin();
        $college = College::factory()->create(['name' => 'كلية النقل']);
        $dept = Department::factory()->create(['college_id' => $college->id]);

        $response = $this->actingAs($admin)->get('/departments')->assertOk();
        $itqan = $response->viewData('itqan');

        $payloadCollege = collect($itqan['colleges'])->firstWhere('id', $college->id);
        $this->assertNotNull($payloadCollege, 'the college reaches the page payload');
        $this->assertSame('كلية النقل', $payloadCollege['name']);

        $payloadDept = collect($itqan['departments'])->firstWhere('id', $dept->id);
        $this->assertSame($college->id, $payloadDept['collegeId'], 'the assignment reaches the page payload');
    }
}
