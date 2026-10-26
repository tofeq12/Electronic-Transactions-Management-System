<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*

         * 1. ربط سياسات الأدوار والصلاحيات
         */
        Gate::policy(\Spatie\Permission\Models\Role::class, \App\Policies\RolePolicy::class);
        Gate::policy(\Spatie\Permission\Models\Permission::class, \App\Policies\PermissionPolicy::class);

        /*
         * Login throttling: 5 attempts per minute for the submitted
         * identifier + IP combination.
         *
         * 2. حماية تسجيل الدخول من المحاولات المتكررة
         */
        RateLimiter::for('login', function (Request $request) {
            $identifier = Str::lower((string) $request->input('email', ''));

            return Limit::perMinute(5)->by($identifier.'|'.$request->ip());
        });
    }
}
