<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 5 profile photo contract:
 * - upload / replace / remove the signed-in user's own photo on the
 *   private `local` disk under a server-chosen name
 * - strict validation: real image, allowed formats, <= 2 MB
 * - serving has no user id in the URL → nobody can request someone
 *   else's photo (falls back to the shared default avatar)
 * - the profile update endpoint ignores protected fields (role, status,
 *   locale, avatar_path, department_id …).
 */
class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    /** Valid 1×1 PNG. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    /** Valid 1×1 JPEG. */
    private const JPEG = '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAHwAAAQUBAQEBAQEAAAAAAAAAAAECAwQFBgcICQoL/8QAtRAAAgEDAwIEAwUFBAQAAAF9AQIDAAQRBRIhMUEGE1FhByJxFDKBkaEII0KxwRVS0fAkM2JyggkKFhcYGRolJicoKSo0NTY3ODk6Q0RFRkdISUpTVFVWV1hZWmNkZWZnaGlqc3R1dnd4eXqDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uHi4+Tl5ufo6erx8vP09fb3+Pn6/8QAHwEAAwEBAQEBAQEBAQAAAAAAAAECAwQFBgcICQoL/8QAtREAAgECBAQDBAcFBAQAAQJ3AAECAxEEBSExBhJBUQdhcRMiMoEIFEKRobHBCSMzUvAVYnLRChYkNOEl8RcYGRomJygpKjU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6goOEhYaHiImKkpOUlZaXmJmaoqOkpaanqKmqsrO0tba3uLm6wsPExcbHyMnK0tPU1dbX2Nna4uPk5ebn6Onq8vP09fb3+Pn6/9oADAMBAAIRAxEAPwD3+iiigD//2Q==';

    /** Valid 1×1 GIF — real image, but a format the policy forbids. */
    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function upload(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)->post('/profile/avatar', ['avatar' => $file], [
            'Accept' => 'application/json',
        ]);
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('avatar.png', base64_decode(self::PNG));
    }

    /* ------------------------------------------------------- uploads */

    public function test_user_can_upload_a_photo_and_it_is_stored_in_mysql(): void
    {
        $user = User::factory()->create();

        $response = $this->upload($user, $this->png());

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertStringContainsString('/profile/avatar', (string) $response->json('data.url'));

        $user->refresh();
        $this->assertSame('avatars/'.$user->id.'.png', $user->avatar_path);
        $this->assertSame('image/png', $user->avatar_mime);
        $this->assertSame(base64_decode(self::PNG), (string) $user->avatar_data);

        // The raw bytes really live in MySQL (not only on disk).
        $raw = \DB::table('users')->where('id', $user->id)->value('avatar_data');
        $this->assertSame(base64_decode(self::PNG), (string) $raw);

        // Served back with the right content type.
        $this->actingAs($user)->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_replacing_the_photo_swaps_the_stored_bytes(): void
    {
        $user = User::factory()->create();

        $this->upload($user, $this->png())->assertOk();
        $this->assertSame(base64_decode(self::PNG), (string) $user->fresh()->avatar_data);

        $jpeg = UploadedFile::fake()->createWithContent('avatar.jpg', base64_decode(self::JPEG));
        $this->upload($user, $jpeg)->assertOk();

        $user->refresh();
        $this->assertSame('image/jpeg', $user->avatar_mime);
        $this->assertSame(base64_decode(self::JPEG), (string) $user->avatar_data);

        // Served back as JPEG now.
        $this->actingAs($user)->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
    }

    public function test_non_image_and_forbidden_formats_are_rejected(): void
    {
        $user = User::factory()->create();

        // Not an image at all.
        $this->upload($user, UploadedFile::fake()->create('note.txt', 5))
            ->assertStatus(422)
            ->assertJsonPath('errors.avatar.0', 'الملف المرفوع يجب أن يكون صورة');

        // Valid image, forbidden format (GIF).
        $gif = UploadedFile::fake()->createWithContent('anim.gif', base64_decode(self::GIF));
        $this->upload($user, $gif)
            ->assertStatus(422)
            ->assertJsonPath('errors.avatar.0', 'صيغ الصورة المسموحة: JPG أو PNG أو WEBP');

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_oversized_photo_is_rejected(): void
    {
        $user = User::factory()->create();

        // Real PNG header + padding → valid image, ~2.1 MB (> 2048 KB limit).
        $big = UploadedFile::fake()->createWithContent(
            'big.png',
            base64_decode(self::PNG).str_repeat('A', 2100 * 1024)
        );

        $this->upload($user, $big)
            ->assertStatus(422)
            ->assertJsonPath('errors.avatar.0', 'حجم الصورة لا يتجاوز 2 ميغابايت');

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertEmpty(Storage::disk('local')->files('avatars'));
    }

    public function test_client_supplied_dangerous_extension_is_rejected(): void
    {
        $user = User::factory()->create();

        // PNG content sent with a .php name: Laravel's upload guard blocks
        // PHP-named uploads outright, regardless of content — nothing is
        // stored and no attacker-chosen name ever reaches disk.
        $sneaky = UploadedFile::fake()->createWithContent('evil.php', base64_decode(self::PNG));
        $this->upload($user, $sneaky)->assertStatus(422);

        $this->assertNull($user->fresh()->avatar_path);
        $this->assertEmpty(Storage::disk('local')->files('avatars'));
    }

    public function test_photo_can_be_removed_and_default_avatar_restored(): void
    {
        $user = User::factory()->create();
        $this->upload($user, $this->png())->assertOk();
        $this->assertSame(base64_decode(self::PNG), (string) $user->fresh()->avatar_data);

        $this->actingAs($user)
            ->postJson('/profile/avatar/delete')
            ->assertOk()
            ->assertJson(['success' => true]);

        // The DB bytes are cleared; the default avatar is served again.
        $user->refresh();
        $this->assertNull($user->avatar_data);
        $this->assertNull($user->avatar_mime);
        $this->assertNull($user->avatar_path);

        $this->actingAs($user)->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_photo_survives_a_fresh_request_and_a_new_login_session(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->upload($user, $this->png())->assertOk();

        // Log out, log back in, and read the photo through a brand-new session.
        $this->post('/logout');
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(302);

        $this->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // And the bytes are still in MySQL after all that.
        $this->assertSame(
            base64_decode(self::PNG),
            (string) \DB::table('users')->where('id', $user->id)->value('avatar_data')
        );
    }

    public function test_legacy_file_only_avatar_still_serves_as_fallback(): void
    {
        // A user with an avatar stored only on the pre-BLOB private disk.
        Storage::disk('local')->put('avatars/legacy.png', base64_decode(self::PNG));
        $user = User::factory()->create(['avatar_path' => 'avatars/legacy.png']);

        $this->actingAs($user)->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    /* ------------------------------------------------------- serving */

    public function test_the_serving_endpoint_only_ever_returns_the_callers_own_photo(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->upload($owner, $this->png())->assertOk();

        // The owner gets their photo …
        $this->actingAs($owner)->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        // … while any other account gets the default avatar — there is no
        // id in the URL, so cross-user access is structurally impossible.
        $this->actingAs($other)->get('/profile/avatar')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_photo_routes_require_authentication(): void
    {
        $this->post('/profile/avatar', ['avatar' => $this->png()])->assertRedirect('/login');
        $this->post('/profile/avatar/delete')->assertRedirect('/login');
        $this->get('/profile/avatar')->assertRedirect('/login');
    }

    /* ------------------------------------------ protected user fields */

    public function test_profile_update_cannot_change_protected_fields(): void
    {
        $user = User::factory()->create([
            'role' => 'employee',
            'status' => 'active',
            'locale' => 'ar',
            'department_id' => null,
            'avatar_path' => null,
        ]);

        $this->actingAs($user)
            ->postJson('/profile/update', [
                'name' => 'اسم جديد كاملا',
                'email' => 'fresh@example.com',
                'phone' => '0551234567',
                // hostile extras — must be ignored:
                'role' => 'system_admin',
                'status' => 'inactive',
                'locale' => 'en',
                'avatar_path' => 'avatars/hack.png',
                // binary-looking scalar that must be ignored (kept short so the
                // JSON body stays valid UTF-8; the BLOB is never mass-assignable)
                'avatar_data' => 'not-a-blob',
                'avatar_mime' => 'image/png',
                'department_id' => 999,
                'id' => 999,
            ])
            ->assertOk();

        $user->refresh();
        $this->assertSame('employee', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertSame('ar', $user->locale);
        $this->assertNull($user->department_id);
        $this->assertNull($user->avatar_path);
        $this->assertNull($user->avatar_data);
        $this->assertNull($user->avatar_mime);
        $this->assertSame('fresh@example.com', $user->email);
    }

    public function test_profile_update_succeeds_without_the_full_name_field(): void
    {
        // Full Name was removed from the profile form: the update must work
        // without it and must never overwrite the stored name.
        $user = User::factory()->create(['name' => 'أحمد علي']);

        $this->actingAs($user)
            ->postJson('/profile/update', [
                'email' => 'newmail@example.com',
                'phone' => '0551234567',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $user->refresh();
        $this->assertSame('أحمد علي', $user->name);
        $this->assertSame('newmail@example.com', $user->email);
        $this->assertSame('0551234567', $user->phone);
    }
}
