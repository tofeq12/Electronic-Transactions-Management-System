<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Login page prefill audit.
 *
 * Diagnosis pinned by these tests: the APPLICATION never prefills the
 * credentials fields — no Blade value attributes, no old()/session data
 * (the controller renders the bare view), and no script referenced by the
 * page writes into them. If a user still sees filled fields, the cause is
 * the browser/OS itself (saved credentials — i.e. the password manager),
 * which the form keeps fully supported through the standard
 * autocomplete tokens asserted below.
 */
class LoginPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_fields_carry_no_application_prefill_but_keep_manager_tokens(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        // Blade/HTML: no visible input carries a server-rendered value.
        // (The CSRF hidden input is excluded on purpose — it is not a
        // form field the user types into.)
        preg_match_all('/<input\b(?![^>]*type="hidden")[^>]*\bvalue\s*=/i', $html, $matches);
        $this->assertSame([], $matches[0], 'no login field is prefilled by Blade/HTML');

        // Password managers stay fully functional: the standard tokens.
        $this->assertStringContainsString('autocomplete="username"', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);

        // The fix: both fields render readonly so the browser can never
        // silently prefill them on load (JS unlocks on first interaction).
        $this->assertMatchesRegularExpression('/<input[^>]*id="email"[^>]*readonly/u', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*id="password"[^>]*readonly/u', $html);
    }

    public function test_no_script_referenced_by_the_login_page_writes_into_the_fields(): void
    {
        $page = (string) file_get_contents(resource_path('views/pages/login.blade.php'));

        // The inline page script never writes into the fields either.
        $inline = preg_match('/(email|password)[^;\n]*\.value\s*=/u', $page);
        $this->assertSame(0, $inline, 'the inline login script never writes into the fields');

        // Every local script the page loads.
        preg_match_all('/asset\(\'(js\/[^\']+)\'\)/', $page, $m);
        $assets = $m[1];
        $this->assertContains('js/auth.js', $assets, 'the page really loads auth.js');

        foreach ($assets as $asset) {
            $path = public_path($asset);
            if (! is_file($path)) {
                continue;
            }
            $js = (string) file_get_contents($path);
            $writes = preg_match(
                '/\[name=["\'](email|password)["\']\]\s*\.value\s*=|getElementById\(\s*["\'](email|password)["\']\s*\)\s*\.value\s*=/u',
                $js
            );
            $this->assertSame(0, $writes, "no script writes into the login fields: {$asset}");
        }
    }
}
