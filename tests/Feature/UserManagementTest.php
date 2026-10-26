<?php

namespace Tests\Feature;

use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'موظف جديد',
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'phone' => '0512345678',
            'password' => 'pass1234',
            'role' => 'موظف',
            'status' => 'active',
        ], $over);
    }

    public function test_admin_creates_user_with_arabic_role_label_and_hashed_password(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->postJson('/users', $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $user = User::where('username', 'newuser')->first();
        $this->assertNotNull($user);
        $this->assertSame('employee', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertTrue(Hash::check('pass1234', $user->password));
        $this->assertNotSame('pass1234', $user->password);
        $this->assertNotNull($response->json('data.redirect'));
    }

    public function test_admin_role_label_maps_to_system_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/users', $this->payload([
                'username' => 'boss',
                'email' => 'boss@example.com',
                'role' => 'مدير',
            ]))
            ->assertOk();

        $this->assertSame('system_admin', User::where('username', 'boss')->first()->role);
    }

    public function test_admin_assigns_college_and_department_on_create(): void
    {
        $admin = User::factory()->admin()->create();
        $college = College::factory()->create();
        $dept = Department::factory()->create(['college_id' => $college->id]);

        $this->actingAs($admin)
            ->postJson('/users', $this->payload([
                'college_id' => $college->id,
                'department_id' => $dept->id,
            ]))
            ->assertOk()
            ->assertJson(['success' => true]);

        $user = User::where('username', 'newuser')->firstOrFail();
        $this->assertSame($college->id, $user->college_id);
        $this->assertSame($dept->id, $user->department_id);
    }

    public function test_admin_can_update_and_clear_org_units(): void
    {
        $admin = User::factory()->admin()->create();
        $oldDept = Department::factory()->create();
        $user = User::factory()->create(['department_id' => $oldDept->id, 'username' => 'orguser']);
        $newCollege = College::factory()->create();
        $newDept = Department::factory()->create(['college_id' => $newCollege->id]);

        $payload = $this->payload([
            'username' => 'orguser',
            'email' => $user->email,
            'name' => 'مستخدم التنظيم',
            'college_id' => $newCollege->id,
            'department_id' => $newDept->id,
        ]);
        unset($payload['role'], $payload['password'], $payload['status']);

        $this->actingAs($admin)
            ->postJson('/users/'.$user->id.'/update', $payload)
            ->assertOk();

        $user->refresh();
        $this->assertSame($newCollege->id, $user->college_id);
        $this->assertSame($newDept->id, $user->department_id);

        // Clearing sends empty values → stored as null.
        $payload['college_id'] = '';
        $payload['department_id'] = '';
        $this->actingAs($admin)
            ->postJson('/users/'.$user->id.'/update', $payload)
            ->assertOk();

        $user->refresh();
        $this->assertNull($user->college_id);
        $this->assertNull($user->department_id);
    }

    public function test_invalid_college_or_department_ids_fail_validation(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/users', $this->payload([
                'college_id' => 99999,
                'department_id' => 99999,
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['college_id', 'department_id']);
    }

    public function test_validation_rejects_duplicate_username_and_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['username' => 'taken', 'email' => 'taken@example.com']);

        $this->actingAs($admin)
            ->postJson('/users', $this->payload(['username' => 'taken']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('username');

        $this->actingAs($admin)
            ->postJson('/users', $this->payload(['email' => 'taken@example.com']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        // Weak password is rejected too.
        $this->actingAs($admin)
            ->postJson('/users', $this->payload(['password' => 'short']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_update_without_role_keeps_the_current_role(): void
    {
        $admin = User::factory()->admin()->create();
        $manager = User::factory()->create(['role' => 'dept_manager', 'username' => 'manager1']);

        $payload = $this->payload([
            'username' => 'manager1',
            'email' => $manager->email,
            'name' => 'مدير القسم',
        ]);
        unset($payload['role'], $payload['password'], $payload['status']);

        $this->actingAs($admin)
            ->postJson('/users/'.$manager->id.'/update', $payload)
            ->assertOk();

        $manager->refresh();
        $this->assertSame('dept_manager', $manager->role);
        $this->assertSame('مدير القسم', $manager->name);
    }

    public function test_employee_cannot_manage_users(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/dashboard/users')->assertStatus(403);
        $this->actingAs($employee)->get('/users/add')->assertStatus(403);
        $this->actingAs($employee)
            ->postJson('/users', $this->payload())
            ->assertStatus(403);

        $this->assertSame(1, User::count());
    }

    public function test_toggle_flips_status_but_never_for_yourself(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/users/'.$target->id.'/toggle')
            ->assertOk()
            ->assertJson(['data' => ['status' => 'inactive']]);
        $this->assertSame('inactive', $target->refresh()->status);

        $this->actingAs($admin)
            ->postJson('/users/'.$admin->id.'/toggle')
            ->assertStatus(422);
        $this->assertSame('active', $admin->refresh()->status);
    }

    public function test_deactivated_user_cannot_log_in_and_is_logged_out(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['username' => 'victim', 'password' => Hash::make('secret123')]);

        $this->actingAs($admin)->postJson('/users/'.$target->id.'/toggle')->assertOk();

        $this->post('/login', ['email' => 'victim', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');

        // Even with an active session, the middleware signs them out.
        $this->actingAs($target->fresh())->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_cannot_delete_own_account(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson('/users/'.$admin->id.'/delete')
            ->assertStatus(403);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->postJson('/users/'.$target->id.'/delete')
            ->assertOk();

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
    }

    public function test_role_guard_blocks_self_role_escalation_via_update(): void
    {
        // Self status change through update is refused server-side.
        $admin = User::factory()->admin()->create();

        $payload = $this->payload([
            'username' => $admin->username,
            'email' => $admin->email,
            'status' => 'inactive',
        ]);
        unset($payload['role'], $payload['password']);

        $this->actingAs($admin)
            ->postJson('/users/'.$admin->id.'/update', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        $this->assertSame('active', $admin->refresh()->status);
    }

    public function test_users_edit_page_requires_user_management_even_without_an_id(): void
    {
        // Regression: bare /users/edit used to render for ANY authenticated
        // user and injected every user's full record (withFullUsers payload).
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/users/edit')->assertStatus(403);

        // Positive control: an administrator still reaches the page.
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/users/edit')->assertOk();
    }

    public function test_last_admin_cannot_demote_their_own_role(): void
    {
        $admin = User::factory()->admin()->create(); // the only administrator

        $this->actingAs($admin)
            ->postJson('/users/'.$admin->id.'/update', $this->payload([
                'username' => $admin->username,
                'email' => $admin->email,
                'role' => 'موظف',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('role');

        $this->assertSame('system_admin', $admin->refresh()->role);
    }

    public function test_another_admin_can_still_be_demoted_when_two_exist(): void
    {
        $adminA = User::factory()->admin()->create();
        $adminB = User::factory()->admin()->create();

        $this->actingAs($adminA)
            ->postJson('/users/'.$adminB->id.'/update', $this->payload([
                'username' => $adminB->username,
                'email' => $adminB->email,
                'role' => 'موظف',
            ]))
            ->assertOk();

        $this->assertSame('employee', $adminB->refresh()->role);
        $this->assertSame('system_admin', $adminA->refresh()->role);
    }
}
