<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Phase 9 — REAL password recovery via the Laravel Password broker
 * (hashed one-time token, 60-minute validity, per-email throttle).
 *
 * The flow is honest about delivery: with a real mail transport the link
 * is actually sent; with the 'log' driver (current deployment) the
 * endpoint refuses plainly and points at the administrator-assisted
 * reset instead of pretending an email went out. Anti-enumeration: the
 * reply is identical for existing and unknown addresses.
 */
class PasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Route-level throttle is infrastructure (tested elsewhere); these
        // tests exercise the broker-level per-email throttle deliberately.
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function user(array $over = []): User
    {
        return User::factory()->create($over);
    }

    /* ------------------------------------------------------------ pages */

    public function test_pages_render_for_guests(): void
    {
        $this->get('/forgot-password')->assertOk()->assertSee('forgot-form');
        $this->get('/reset-password')->assertOk()->assertSee('reset-form');
        $this->get('/reset-password?token=abc&email=a@b.c')->assertOk()->assertSee('reset-form');
    }

    public function test_logged_in_users_are_redirected_away(): void
    {
        $user = $this->user();

        $this->actingAs($user)->get('/forgot-password')->assertRedirect('/dashboard');
        $this->actingAs($user)->get('/reset-password')->assertRedirect('/dashboard');
    }

    /* ------------------------------------------------- link requested */

    public function test_valid_request_sends_reset_link_and_stores_token(): void
    {
        Notification::fake();
        $user = $this->user(['email' => 'recover@example.com']);

        $res = $this->postJson('/forgot-password', ['email' => 'recover@example.com'])
            ->assertOk()
            ->assertJson(['success' => true]);

        // Generic anti-enumeration wording; a token row really exists.
        $this->assertStringContainsString('إذا كان البريد مسجلاً', $res->json('message'));
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', 'recover@example.com')->count());
        Notification::assertSentTo($user, ResetPassword::class);

        // The request itself is audited (actor is a guest → user_id null).
        $this->assertTrue(
            ActivityLog::whereNull('user_id')->where('action', 'إرسال رابط استرجاع كلمة مرور')
                ->where('target', 'recover@example.com')->exists()
        );
    }

    public function test_unknown_address_gets_the_same_generic_reply_and_no_token(): void
    {
        Notification::fake();

        $res = $this->postJson('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertStringContainsString('إذا كان البريد مسجلاً', $res->json('message'));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        Notification::assertNothingSent();
    }

    public function test_second_request_within_a_minute_is_throttled(): void
    {
        Notification::fake();
        $this->user(['email' => 'throttle@example.com']);

        $this->postJson('/forgot-password', ['email' => 'throttle@example.com'])->assertOk();
        $res = $this->postJson('/forgot-password', ['email' => 'throttle@example.com'])
            ->assertStatus(422);

        $this->assertStringContainsString('انتظر دقيقة', $res->json('message'));
    }

    /* ------------------------------------------------- honesty gate */

    public function test_without_a_real_mail_transport_the_flow_refuses_honestly(): void
    {
        Notification::fake();
        config(['mail.default' => 'log']); // the current deployment driver
        $this->user(['email' => 'known@example.com']);

        // Known AND unknown addresses get the SAME plain refusal — no
        // "sent" illusion, no enumeration, no token, no notification.
        foreach (['known@example.com', 'unknown@example.com'] as $email) {
            $res = $this->postJson('/forgot-password', ['email' => $email])
                ->assertStatus(503)
                ->assertJson(['success' => false]);
            $this->assertStringContainsString('تواصل مع مسؤول النظام', $res->json('message'));
        }

        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        Notification::assertNothingSent();
    }

    /* ------------------------------------------------- reset step */

    public function test_valid_token_sets_a_new_hashed_password_once(): void
    {
        Notification::fake();
        $user = $this->user(['email' => 'setter@example.com', 'password' => 'oldpass123']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/reset-password', [
            'email' => 'setter@example.com',
            'token' => $token,
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['redirect' => '/login']]);

        $user->refresh();
        $this->assertTrue(Hash::check('newpass1234', $user->password));
        $this->assertFalse(Hash::check('oldpass123', $user->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'setter@example.com')->count(),
            'the token is single-use');

        // Security-relevant event lands in the audit trail (actor = the
        // account itself; target = its username).
        $this->assertTrue(
            ActivityLog::where('action', 'إعادة تعيين كلمة المرور')
                ->where('target', $user->username)->exists()
        );
    }

    public function test_invalid_or_reused_token_is_rejected_and_password_unchanged(): void
    {
        Notification::fake();
        $user = $this->user(['email' => 'guard@example.com', 'password' => 'keepme123']);
        $token = Password::broker()->createToken($user);

        // Garbage token.
        $this->postJson('/reset-password', [
            'email' => 'guard@example.com',
            'token' => 'not-a-real-token',
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ])->assertStatus(422);

        // Real token, then reuse after success.
        $this->postJson('/reset-password', [
            'email' => 'guard@example.com',
            'token' => $token,
            'password' => 'newpass1234',
            'password_confirmation' => 'newpass1234',
        ])->assertOk();
        $this->postJson('/reset-password', [
            'email' => 'guard@example.com',
            'token' => $token,
            'password' => 'againpass12',
            'password_confirmation' => 'againpass12',
        ])->assertStatus(422);

        $this->assertTrue(Hash::check('newpass1234', $user->refresh()->password));
    }

    public function test_weak_password_is_rejected_by_server_rules(): void
    {
        Notification::fake();
        $user = $this->user(['email' => 'weak@example.com']);
        $token = Password::broker()->createToken($user);

        $res = $this->postJson('/reset-password', [
            'email' => 'weak@example.com',
            'token' => $token,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertStatus(422);

        $this->assertArrayHasKey('password', $res->json('errors'));
    }
}
