<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 12 — security + language baselines:
 * - Logout accepts ONLY POST (a GET can no longer end a session — the
 *   classic "logout CSRF" vector — and the sidebar renders the POST form).
 * - Every web response carries the baseline hardening headers (nosniff,
 *   clickjacking protection, referrer/permissions policies).
 * - lang/en.json stays valid JSON with no empty values, and every
 *   literal __() used by the blades is covered (English users never see
 *   raw Arabic fallbacks).
 */
class SecurityI18nTest extends TestCase
{
    use RefreshDatabase;

    /* ---------------------------------------------------------- logout */

    public function test_logout_rejects_get_and_works_via_post(): void
    {
        $user = User::factory()->create();

        // A GET can no longer end anyone's session.
        $this->actingAs($user)->get('/logout')->assertStatus(405);

        // The POST form ends it and lands on the login page.
        $this->actingAs($user)->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_sidebar_renders_the_post_logout_form(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('action="/logout"', $html);
        $this->assertStringContainsString('name="_token"', $html, 'the form carries the CSRF field');
        $this->assertStringNotContainsString('href="/logout"', $html, 'the GET link is gone everywhere');
    }

    /* ---------------------------------------------------------- headers */

    public function test_web_responses_carry_the_hardening_headers(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'));
        $this->assertSame("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame('same-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('camera=()', (string) $response->headers->get('Permissions-Policy'));
    }

    /* ------------------------------------------------------------ i18n */

    public function test_english_language_file_is_valid_and_free_of_empty_values(): void
    {
        $raw = (string) file_get_contents(base_path('lang/en.json'));
        $decoded = json_decode($raw, true);

        $this->assertTrue(is_array($decoded), 'en.json must be valid JSON');
        $this->assertGreaterThan(500, count($decoded), 'the map keeps growing with the features');

        $empties = [];
        foreach ($decoded as $key => $value) {
            if (! is_string($value) || trim($value) === '') {
                $empties[] = $key;
            }
        }
        $this->assertSame([], $empties, 'no empty translations');
    }

    public function test_every_blade_translation_literal_has_an_english_key(): void
    {
        $en = json_decode((string) file_get_contents(base_path('lang/en.json')), true);
        $this->assertTrue(is_array($en));

        // A literal counts when its closing quote is followed by , or )
        // (call args) — so calls on one line cannot bleed together.
        $patterns = [
            '/__\(\s*\'((?:[^\']|\\\\.)*)\'\s*(?:,|\))/u',
            '/__\(\s*"((?:[^"]|\\\\.)*)"\s*(?:,|\))/u',
        ];

        $missing = [];
        $scanned = 0;

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = (string) file_get_contents($file->getPathname());
            foreach ($patterns as $re) {
                if (preg_match_all($re, $src, $m)) {
                    foreach ($m[1] as $key) {
                        $scanned++;
                        if (! array_key_exists($key, $en)) {
                            $missing[$key] = ($missing[$key] ?? 0) + 1;
                        }
                    }
                }
            }
        }

        $this->assertGreaterThan(500, $scanned, 'the scan must actually see the blades');
        $this->assertSame([], array_map(fn ($k, $n) => "{$k} (×{$n})", array_keys($missing), $missing),
            'every literal __() in the blades has an en.json key');
    }
}
