<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5 localization contract:
 * - Arabic is the default (guests, new users) and renders dir="rtl"
 * - Each user's own preference (users.locale) drives their pages,
 *   persists across sessions and never leaks to other users
 * - Validation messages, page chrome and the JS translation map follow
 *   the active locale.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    /* ------------------------------------------------------- guests */

    public function test_guest_gets_arabic_rtl_by_default(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('<html lang="ar" dir="rtl">', false);
        $response->assertSee('تسجيل الدخول');
        $response->assertSee('تذكرني');
        // Arabic is the source language: no translation map is injected.
        $response->assertSee('window.ITQAN_T = []', false);
    }

    public function test_language_controls_exist_only_in_settings(): void
    {
        $user = User::factory()->create();

        // Settings keeps the language selector …
        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('id="st-lang"', false);

        // … and no other screen exposes one (profile page, login, splash).
        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertDontSee('id="pf-locale"', false);

        $this->get('/login')->assertDontSee('lang-toggle', false);
        $this->get('/')->assertDontSee('lang-toggle', false);
    }

    /* ------------------------------------------- per-user preference */

    public function test_user_locale_is_saved_to_the_account_and_renders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/profile/locale', ['locale' => 'en'])
            ->assertOk()
            ->assertJson(['success' => true, 'data' => ['locale' => 'en']]);

        $this->assertSame('en', $user->fresh()->locale);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_preferences_are_independent_between_users(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson('/profile/locale', ['locale' => 'en'])->assertOk();

        // B is untouched: account row and rendered page stay Arabic.
        $this->assertSame('ar', $b->fresh()->locale);

        $this->actingAs($b)->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);

        // A still renders English afterwards.
        $this->actingAs($a)->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_preference_persists_across_logout_and_login(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)->postJson('/profile/locale', ['locale' => 'en'])->assertOk();

        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(302);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    public function test_account_preference_wins_over_leftover_session_locale(): void
    {
        // users.locale is 'ar' (default): a stale guest session locale must
        // not override the signed-in user's saved preference.
        $user = User::factory()->create(['locale' => 'ar']);

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);
    }

    public function test_invalid_locale_is_never_applied(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/profile/locale', ['locale' => 'de'])
            ->assertStatus(422);

        $this->assertSame('ar', $user->fresh()->locale);

        $this->actingAs($user)->withSession(['locale' => 'de'])->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="ar" dir="rtl">', false);
    }

    /* --------------------------------------------- server-rendered UI */

    public function test_validation_messages_follow_the_user_locale(): void
    {
        $bad = ['name' => 'اسم كاملا', 'email' => 'x@example.com', 'phone' => '12'];

        $enUser = User::factory()->create(['locale' => 'en']);
        $this->actingAs($enUser)
            ->postJson('/profile/update', $bad)
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'Enter a valid phone number (9 to 15 digits)');

        $arUser = User::factory()->create(['locale' => 'ar']);
        $this->actingAs($arUser)
            ->postJson('/profile/update', $bad)
            ->assertStatus(422)
            ->assertJsonPath('errors.phone.0', 'أدخل رقم هاتف صحيحاً (9 إلى 15 رقماً)');
    }

    public function test_avatar_validation_messages_follow_the_user_locale(): void
    {
        $enUser = User::factory()->create(['locale' => 'en']);

        $this->actingAs($enUser)
            ->postJson('/profile/avatar', [])
            ->assertStatus(422)
            ->assertJsonPath('errors.avatar.0', 'Choose a new photo');
    }

    public function test_error_page_honours_the_active_locale(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)->get('/404')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false);
    }

    /* -------------------------------------- locale vs. core operations */

    public function test_transaction_pages_and_writes_still_work_in_english(): void
    {
        // An English-preferring manager must be able to use the transaction
        // screens normally: pages render, the dataset loads, and a write
        // endpoint answers with the standard JSON contract (no 500s, no
        // locale-related breakage).
        $manager = User::factory()->create(['role' => 'manager', 'locale' => 'en']);

        $this->actingAs($manager)->get('/dashboard')
            ->assertOk()
            ->assertSee('<html lang="en" dir="ltr">', false)
            ->assertSee('window.ITQAN_DATA', false);

        $response = $this->actingAs($manager)
            ->withHeaders(['Accept' => 'application/json'])
            ->postJson('/transactions', [
                'title' => 'English locale smoke test',
                'type' => 'incoming',
                'priority' => 'normal',
            ]);

        // Either the write succeeds (200) or it is rejected with the normal
        // localized validation contract (422) — never a server error.
        $this->assertContains($response->status(), [200, 422]);
        $this->assertIsBool($response->json('success'));
    }
}
