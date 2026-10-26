<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(array $over = []): array
    {
        return array_merge([
            'name' => 'طالب جديد',
            'username' => 'newstudent',
            'email' => 'newstudent@example.com',
            'phone' => '0551234567',
            'password' => 'pass1234',
            'password_confirmation' => 'pass1234',
        ], $over);
    }

    public function test_register_page_renders_the_form_for_guests(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('id="register-form"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="username"', false)
            ->assertSee('name="password_confirmation"', false)
            // Full Name field and the Arabic/English top bar were removed.
            ->assertDontSee('id="fullname"', false)
            ->assertDontSee('lang-toggle', false);
    }

    public function test_registration_without_a_full_name_stores_the_username_as_display_name(): void
    {
        $this->post('/register', $this->payload(['name' => null]))
            ->assertRedirect('/login');

        $user = User::where('username', 'newstudent')->first();
        $this->assertNotNull($user);
        $this->assertSame('newstudent', $user->name);
    }

    public function test_authenticated_users_are_redirected_from_the_register_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/register')->assertRedirect('/dashboard');
    }

    public function test_successful_registration_creates_a_pending_inactive_employee(): void
    {
        $this->post('/register', $this->payload())
            ->assertRedirect('/login');

        $user = User::where('username', 'newstudent')->first();
        $this->assertNotNull($user);
        $this->assertSame('employee', $user->role);
        $this->assertSame('inactive', $user->status);
        $this->assertTrue(Hash::check('pass1234', $user->password));
        $this->assertNotSame('pass1234', $user->password);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'إنشاء حساب جديد بانتظار التفعيل',
        ]);
        $this->assertGuest();
    }

    public function test_registration_rejects_duplicate_username_or_email(): void
    {
        User::factory()->create(['username' => 'taken', 'email' => 'taken@example.com']);

        $this->post('/register', $this->payload(['username' => 'taken']))
            ->assertSessionHasErrors('username');

        $this->post('/register', $this->payload(['email' => 'taken@example.com']))
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count());
    }

    public function test_registration_validates_password_confirmation_and_formats(): void
    {
        // Password confirmation mismatch.
        $this->post('/register', $this->payload(['password_confirmation' => 'different1']))
            ->assertSessionHasErrors('password');

        // Weak password (no digit).
        $this->post('/register', $this->payload(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']))
            ->assertSessionHasErrors('password');

        // Invalid username format.
        $this->post('/register', $this->payload(['username' => 'مستخدم']))
            ->assertSessionHasErrors('username');

        $this->assertSame(0, User::count());
    }

    public function test_role_and_status_fields_from_the_request_are_ignored(): void
    {
        $this->post('/register', $this->payload([
            'role' => 'مدير',
            'status' => 'active',
            'college_id' => 1,
            'department_id' => 1,
        ]))->assertRedirect('/login');

        $user = User::where('username', 'newstudent')->firstOrFail();
        $this->assertSame('employee', $user->role);
        $this->assertSame('inactive', $user->status);
        $this->assertNull($user->college_id);
        $this->assertNull($user->department_id);
    }

    public function test_pending_account_cannot_log_in_until_an_admin_activates_it(): void
    {
        $this->post('/register', $this->payload())->assertRedirect('/login');
        $user = User::where('username', 'newstudent')->firstOrFail();

        // Pending activation → login refused.
        $this->post('/login', ['email' => 'newstudent', 'password' => 'pass1234'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        // The administrator activates the account from the users screen …
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->postJson('/users/'.$user->id.'/toggle')->assertOk();
        $this->assertSame('active', $user->refresh()->status);

        // … and the same credentials now work.
        $this->post('/login', ['email' => 'newstudent', 'password' => 'pass1234'])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_is_throttled_per_minute(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/register', $this->payload([
                'username' => 'user'.$i,
                'email' => 'user'.$i.'@example.com',
            ]))->assertRedirect('/login');
        }

        $this->post('/register', $this->payload([
            'username' => 'user7',
            'email' => 'user7@example.com',
        ]))->assertStatus(429);

        $this->assertSame(6, User::count());
    }
}
