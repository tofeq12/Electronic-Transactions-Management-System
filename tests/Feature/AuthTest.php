<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_accepts_username_and_redirects_to_dashboard(): void
    {
        User::factory()->create(['username' => 'sara', 'password' => Hash::make('secret123')]);

        $response = $this->post('/login', [
            'email' => 'sara',
            'password' => 'secret123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_login_accepts_email_address(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertRedirect('/dashboard');

        $this->assertAuthenticated();
    }

    public function test_login_with_wrong_password_shows_validation_error(): void
    {
        User::factory()->create(['username' => 'sara', 'password' => Hash::make('secret123')]);

        $this->from('/login')
            ->post('/login', [
                'email' => 'sara',
                'password' => 'wrong-password',
            ])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_json_login_returns_contract_shape_on_failure(): void
    {
        $this->postJson('/login', [
            'email' => 'ghost',
            'password' => 'nope',
        ])->assertStatus(422)
            ->assertJson(['success' => false])
            ->assertJsonStructure(['success', 'message', 'data', 'errors']);
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'username' => 'faisal',
            'password' => Hash::make('secret123'),
        ]);

        $this->post('/login', [
            'email' => 'faisal',
            'password' => 'secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::factory()->create();

        // Phase 12 — logout is POST-only.
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();

        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        User::factory()->create(['username' => 'throttled', 'password' => Hash::make('secret123')]);

        foreach (range(1, 5) as $i) {
            $this->post('/login', ['email' => 'throttled', 'password' => 'bad-password']);
        }

        $this->post('/login', ['email' => 'throttled', 'password' => 'bad-password'])
            ->assertStatus(429);
    }
}
