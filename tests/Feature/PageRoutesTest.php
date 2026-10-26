<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PageRoutesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every migrated page: [uri, expected body data-page, requiresLogin].
     *
     * @return array<int, array{0: string, 1: string, 2: bool}>
     */
    public static function pageProvider(): array
    {
        return [
            // Public entry / auth / error pages
            ['/', 'splash1', false],
            ['/splash2', 'splash2', false],
            ['/auth-choice', 'auth-choice', false],
            ['/onboarding', 'onboarding', false],
            ['/login', 'login', false],
            ['/register', 'register', false],
            ['/forgot-password', 'forgot', false],
            ['/reset-password', 'reset', false],
            ['/403', '403', false],
            ['/404', '404', false],
            ['/500', '500', false],
            // Authenticated application pages
            ['/dashboard', 'home', true],
            ['/dashboard/users', 'users', true],
            ['/users/add', 'users', true],
            ['/users/edit', 'users', true],
            ['/profile', 'profile', true],
            ['/departments', 'departments', true],
            ['/departments/form', 'departments', true],
            ['/roles', 'roles', true],
            ['/roles/form', 'roles', true],
            ['/permissions', 'roles', true],
            ['/transactions', 'transactions', true],
            ['/transactions/new', 'transactions', true],
            ['/transactions/edit', 'transactions', true],
            ['/transactions/forward', 'transactions', true],
            ['/transactions/urgent', 'urgent', true],
            ['/transactions/details', 'transactions', true],
            ['/reports', 'reports', true],
            ['/notifications', 'notifications', true],
            ['/activity-log', 'activity', true],
            ['/settings', 'settings', true],
        ];
    }

    /**
     * @dataProvider pageProvider
     */
    public function test_page_renders_rtl_and_has_no_legacy_html_links(string $uri, string $page, bool $requiresLogin): void
    {
        if ($requiresLogin) {
            $this->actingAs(User::factory()->admin()->create());
        }

        $response = $this->get($uri);

        $response->assertStatus(200);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('data-page="' . $page . '"', false);

        // Migration check: no leftover ".html" links or bundled assets paths.
        $response->assertDontSee('.html', false);
        $response->assertDontSee('../css/', false);
        $response->assertDontSee('../js/', false);
        $response->assertDontSee('assets/images/', false);
    }

    /**
     * Guests are pushed to the login screen for every application page.
     *
     * @dataProvider pageProvider
     */
    public function test_protected_pages_redirect_guests_to_login(string $uri, string $page, bool $requiresLogin): void
    {
        if (! $requiresLogin) {
            $this->markTestSkipped('Public page.');
        }

        $this->get($uri)->assertRedirect('/login');
    }
}
