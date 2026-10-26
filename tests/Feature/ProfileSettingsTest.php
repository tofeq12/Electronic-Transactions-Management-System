<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_update_returns_the_refreshed_user_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/profile/update', [
                'name' => 'الاسم الجديد',
                'email' => 'newmail@example.com',
                'phone' => '0555555555',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertSame('الاسم الجديد', $user->name);
        $this->assertSame('newmail@example.com', $user->email);
        $this->assertSame('0555555555', $user->phone);

        // The JSON contract feeds the sidebar back to the frontend.
        $this->assertSame('الاسم الجديد', $response->json('data.user.name'));
    }

    public function test_profile_rejects_duplicate_email(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->postJson('/profile/update', [
                'name' => 'اسم',
                'email' => 'taken@example.com',
                'phone' => '0555555555',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_password_change_requires_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->actingAs($user)
            ->postJson('/settings/password', [
                'current_password' => 'wrong-pass',
                'password' => 'newpass123',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('secret123', $user->fresh()->password));
    }

    public function test_password_change_succeeds_and_replaces_the_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret123')]);

        $this->actingAs($user)
            ->postJson('/settings/password', [
                'current_password' => 'secret123',
                'password' => 'newpass123',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertTrue(Hash::check('newpass123', $user->fresh()->password));

        // The weak new password is rejected by validation.
        $this->actingAs($user->fresh())
            ->postJson('/settings/password', [
                'current_password' => 'newpass123',
                'password' => 'weak',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_guests_are_redirected_from_profile_and_settings(): void
    {
        $this->get('/profile')->assertRedirect('/login');
        $this->get('/settings')->assertRedirect('/login');
        $this->postJson('/profile/update', [])->assertStatus(401);
        $this->postJson('/settings/password', [])->assertStatus(401);
    }
}
