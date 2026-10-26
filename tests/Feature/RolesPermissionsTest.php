<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * شاشتا الأدوار والصلاحيات + إسناد الأدوار في إدارة المستخدمين.
 *
 * مركز الاختبار (قرار أمني مقصود): 'roles.manage' تُمنح لمدير النظام
 * وحده — بقية الأدوار الإدارية (university_admin / dept_manager) تملك
 * users.view لكنها لا تملك roles.manage، فتُمنع بـ 403. ودرجة الإسناد
 * (canAssign) تمنع غير مدير النظام من منح أدوار إدارية أو مخصّصة.
 */
class RolesPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * فك window.ITQAN_DATA من صفحة مُعرَّضة (نفس أسلوب DepartmentAndAccessTest).
     *
     * @return array<string, mixed>
     */
    private function itqanData(string $content): array
    {
        $this->assertTrue(
            (bool) preg_match('/window\.ITQAN_DATA = (\{.*\});/', $content, $m),
            'ITQAN_DATA payload missing from the page.'
        );
        $data = json_decode($m[1], true);
        $this->assertIsArray($data, 'ITQAN_DATA is not valid JSON.');

        return $data;
    }

    public function test_guests_are_redirected_from_roles_and_permissions(): void
    {
        $this->get('/roles')->assertRedirect('/login');
        $this->get('/roles/form')->assertRedirect('/login');
        $this->get('/permissions')->assertRedirect('/login');
        $this->post('/roles', ['name' => 'x'])->assertRedirect('/login');
    }

    public function test_employee_cannot_access_roles_or_permissions(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/roles')->assertStatus(403);
        $this->actingAs($employee)->get('/roles/form')->assertStatus(403);
        $this->actingAs($employee)->get('/permissions')->assertStatus(403);
        $this->actingAs($employee)->postJson('/roles', ['name' => 'evil'])->assertStatus(403);
        $this->actingAs($employee)->postJson('/permissions', ['name' => 'evil.perm'])->assertStatus(403);

        $this->assertSame(0, Role::where('name', 'evil')->count());
    }

    public function test_reviewer_cannot_access_roles_or_permissions(): void
    {
        $reviewer = User::factory()->reviewer()->create();

        $this->actingAs($reviewer)->get('/roles')->assertStatus(403);
        $this->actingAs($reviewer)->get('/permissions')->assertStatus(403);
        $this->actingAs($reviewer)->postJson('/roles', ['name' => 'x'])->assertStatus(403);
    }

    /**
     * الفجوة التي تُغلقت: مدير الجامعة/القسم يملك users.view (يدير
     * المستخدمين) لكنه لا يملك roles.manage، فيُمنع من شاشتي الأدوار.
     */
    public function test_other_admin_roles_are_denied_role_management(): void
    {
        foreach (['university_admin', 'dept_manager'] as $slug) {
            $admin = User::factory()->create(['role' => $slug, 'status' => 'active']);

            $this->actingAs($admin)->get('/roles')->assertStatus(403);
            $this->actingAs($admin)->get('/permissions')->assertStatus(403);
            $this->actingAs($admin)->postJson('/roles', ['name' => 'sneaky'])->assertStatus(403);
        }

        $this->assertSame(0, Role::where('name', 'sneaky')->count());
    }

    public function test_system_admin_can_view_both_pages_with_payload(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/roles')->assertOk()->assertSee('data-page="roles"', false);
        $this->actingAs($admin)->get('/roles/form')->assertOk();
        $this->actingAs($admin)->get('/permissions')->assertOk();

        $data = $this->itqanData($this->actingAs($admin)->get('/roles')->getContent());
        $this->assertNotEmpty($data['roles'], 'admin must receive roles payload');
        $this->assertNotEmpty($data['permissions'], 'admin must receive permissions payload');
        $this->assertArrayHasKey('usersCount', $data['roles'][0]);
        $this->assertArrayHasKey('group', $data['permissions'][0]);
    }

    /** البيانات لا تُحقن لمن لا يملك roles.manage (حدود البيانات). */
    public function test_role_payload_is_not_injected_for_employees(): void
    {
        $employee = User::factory()->create();
        $data = $this->itqanData($this->actingAs($employee)->get('/dashboard')->getContent());

        $this->assertSame([], $data['roles'] ?? null, 'employee must not receive roles');
        $this->assertSame([], $data['permissions'] ?? null, 'employee must not receive permissions');
    }

    public function test_system_admin_can_create_role_with_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $perm = Permission::findByName('reports.view', 'web');

        $this->actingAs($admin)
            ->postJson('/roles', ['name' => 'auditor', 'permissions' => [$perm->name]])
            ->assertOk()
            ->assertJson(['success' => true]);

        $role = Role::findByName('auditor', 'web');
        $this->assertNotNull($role);
        $this->assertTrue($role->hasPermissionTo('reports.view', 'web'));
    }

    public function test_duplicate_and_invalid_role_names_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/roles', ['name' => 'employee'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->actingAs($admin)
            ->postJson('/roles', ['name' => 'Not Valid!'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_role_name_is_immutable_after_creation(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        $perm = Permission::findByName('activity.view', 'web');

        // محاولة إرسال name مع الصلاحيات لا تُغيّر الاسم (يُتجاهل حقل الاسم).
        $this->actingAs($admin)
            ->postJson('/roles/'.$role->id.'/update', ['name' => 'renamed', 'permissions' => [$perm->name]])
            ->assertOk();

        $this->assertNotNull(Role::where('name', 'auditor')->first());
        $this->assertNull(Role::where('name', 'renamed')->first());
    }

    public function test_permission_revocation_takes_effect_immediately(): void
    {
        $admin = User::factory()->admin()->create();
        $reviewer = User::factory()->reviewer()->create();
        $reviewerRole = Role::findByName('reviewer', 'web');

        // مراجع يملك reports.view → يفتح التقارير.
        $this->actingAs($reviewer->fresh())->get('/reports')->assertOk();

        // سحب الصلاحية من الدور.
        $this->actingAs($admin)
            ->postJson('/roles/'.$reviewerRole->id.'/update', ['permissions' => ['urgent.view']])
            ->assertOk();

        // يُمنع فورًا (403) — دون أي إعادة تسجيل دخول.
        $this->actingAs($reviewer->fresh())->get('/reports')->assertStatus(403);
    }

    public function test_protected_base_roles_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $systemAdminRole = Role::findByName('system_admin', 'web');

        $this->actingAs($admin)
            ->postJson('/roles/'.$systemAdminRole->id.'/delete')
            ->assertStatus(409);

        $this->assertNotNull(Role::findByName('system_admin', 'web'));
    }

    public function test_role_with_assigned_users_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);
        User::factory()->create(['role' => 'auditor', 'status' => 'active']);

        $this->actingAs($admin)
            ->postJson('/roles/'.$role->id.'/delete')
            ->assertStatus(409);

        $this->assertNotNull(Role::findByName('auditor', 'web'));
    }

    public function test_unused_custom_role_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $role = Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->postJson('/roles/'.$role->id.'/delete')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull(Role::where('name', 'auditor')->first());
    }

    /** حارس قفل الوصول: لا سحب roles.manage من آخر دور يحفظها. */
    public function test_cannot_revoke_roles_manage_from_its_last_holder(): void
    {
        $admin = User::factory()->admin()->create();
        $systemAdminRole = Role::findByName('system_admin', 'web');

        // إزالة roles.manage (يمتلكها system_admin وحده) → 422.
        $this->actingAs($admin)
            ->postJson('/roles/'.$systemAdminRole->id.'/update', ['permissions' => RolesAndPermissionsSeeder::PERMISSIONS])
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions');

        // ما زالت محفوظة.
        $this->assertTrue($systemAdminRole->fresh()->hasPermissionTo('roles.manage', 'web'));
    }

    /** حارس قفل الوصول على الحذف: لا حذف آخر دور يحمل users.view. */
    public function test_cannot_delete_last_role_holding_users_manage(): void
    {
        $admin = User::factory()->admin()->create();

        // نصنع دورًا مخصصًا يحمل users.view ثم نُنزل هذه الصلاحية من
        // الأدوار الإدارية الثلاثة مباشرةً (إعداد حالة الاختبار) حتى يصبح
        // الدور المخصص آخر حامل لها في النظام.
        $gatekeeper = Role::create(['name' => 'gatekeeper', 'guard_name' => 'web']);
        $gatekeeper->givePermissionTo('users.view');

        foreach (['system_admin', 'university_admin', 'dept_manager'] as $slug) {
            $r = Role::findByName($slug, 'web');
            $r->syncPermissions($r->permissions->where('name', '!=', 'users.view')->pluck('name')->all());
        }

        $this->assertTrue($gatekeeper->fresh()->hasPermissionTo('users.view', 'web'));

        // حذفه يُكافئ سحب آخر users.view → 422 (حارس قفل الوصول).
        $this->actingAs($admin)
            ->postJson('/roles/'.$gatekeeper->id.'/delete')
            ->assertStatus(422)
            ->assertJsonValidationErrors('permissions');

        $this->assertDatabaseHas('roles', ['name' => 'gatekeeper']);
    }

    public function test_core_permission_cannot_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $perm = Permission::findByName('users.view', 'web');

        $this->actingAs($admin)
            ->postJson('/permissions/'.$perm->id.'/delete')
            ->assertStatus(409);

        $this->assertNotNull(Permission::findByName('users.view', 'web'));
    }

    public function test_permission_in_use_and_unused_custom_permission_rules(): void
    {
        $admin = User::factory()->admin()->create();

        // مرتبطة بدور (reviewer تحملها) → ممنوع الحذف.
        $inUse = Permission::findByName('reports.view', 'web');
        $this->actingAs($admin)->postJson('/permissions/'.$inUse->id.'/delete')->assertStatus(409);
        $this->assertNotNull($inUse->fresh());

        // مخصّصة وغير مرتبطة → تُحذف.
        $custom = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
        $this->actingAs($admin)->postJson('/permissions/'.$custom->id.'/delete')->assertOk();
        $this->assertNull(Permission::where('name', 'reports.export')->first());
    }

    public function test_system_admin_can_create_custom_permission(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/permissions', ['name' => 'reports.export'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull(Permission::findByName('reports.export', 'web'));

        // تكرار مرفوض.
        $this->actingAs($admin)
            ->postJson('/permissions', ['name' => 'reports.export'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    /** درجة الإسناد: غير مدير النظام لا يمنح دورًا إداريًا. */
    public function test_non_system_admin_cannot_assign_admin_role(): void
    {
        $univAdmin = User::factory()->create(['role' => 'university_admin', 'status' => 'active']);

        $this->actingAs($univAdmin)
            ->postJson('/users', [
                'name' => 'مستخدم',
                'username' => 'promoted',
                'email' => 'promoted@example.com',
                'phone' => '0512345678',
                'password' => 'pass1234',
                'role' => 'مدير',
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertSame(0, User::where('username', 'promoted')->count());
    }

    /** درجة الإسناد: غير مدير النظام لا يمنح دورًا مخصّصًا. */
    public function test_non_system_admin_cannot_assign_custom_role(): void
    {
        $univAdmin = User::factory()->create(['role' => 'university_admin', 'status' => 'active']);
        Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        $this->actingAs($univAdmin)
            ->postJson('/users', [
                'name' => 'مستخدم',
                'username' => 'customrole',
                'email' => 'customrole@example.com',
                'phone' => '0512345678',
                'password' => 'pass1234',
                'role' => 'auditor',
                'status' => 'active',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertSame(0, User::where('username', 'customrole')->count());
    }

    /** مدير النظام يُسند أدوارًا إدارية ومخصّصة بحرية. */
    public function test_system_admin_can_assign_admin_and_custom_roles(): void
    {
        $admin = User::factory()->admin()->create();
        Role::create(['name' => 'auditor', 'guard_name' => 'web']);

        $this->actingAs($admin)
            ->postJson('/users', [
                'name' => 'موظف جديد',
                'username' => 'emp1',
                'email' => 'emp1@example.com',
                'phone' => '0512345678',
                'password' => 'pass1234',
                'role' => 'موظف',
                'status' => 'active',
            ])
            ->assertOk();
        $this->assertSame('employee', User::where('username', 'emp1')->first()->role);

        $this->actingAs($admin)
            ->postJson('/users', [
                'name' => 'مراجع جديد',
                'username' => 'aud1',
                'email' => 'aud1@example.com',
                'phone' => '0512345678',
                'password' => 'pass1234',
                'role' => 'auditor',
                'status' => 'active',
            ])
            ->assertOk();
        $this->assertSame('auditor', User::where('username', 'aud1')->first()->role);
    }
}
