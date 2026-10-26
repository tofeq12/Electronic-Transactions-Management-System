<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo('/login');
        // وسيطات عامة لكل طلب ويب: SetLocale (لغة الواجهة) + SecurityHeaders.
        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);
        // تسمية 'active' ← EnsureUserIsActive: تُطبَّق على المجموعة المحميّة
        // 'auth'+'active' في routes/web.php لمنع الحسابات غير المفعّلة.
        $middleware->alias([
            'active' =>EnsureUserIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isJson = fn (Request $request): bool => $request->expectsJson();

        // Consistent JSON contract: {success, message, data, errors}
        $exceptions->render(function (ValidationException $e, Request $request) use ($isJson) {
            if (! $isJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
                'errors' => $e->errors(),
            ], 422);
        });

        // رفض التفويض (من أي سياسة أو بوابة عبر authorize/abort 403) يُعاد
        // كاستجابة JSON بالعقد الموحّد ورسالة "غير مصرح" — يخدم طلبات AJAX.
        $exceptions->render(function (AuthorizationException $e, Request $request) use ($isJson) {
            if (! $isJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('غير مصرح لك بتنفيذ هذا الإجراء'),
                'data' => null,
                'errors' => null,
            ], 403);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isJson) {
            if (! $isJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => __('العنصر المطلوب غير موجود'),
                'data' => null,
                'errors' => null,
            ], 404);
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isJson) {
            if (! $isJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('محاولات كثيرة — أعد المحاولة بعد قليل'),
                'data' => null,
                'errors' => null,
            ], 429);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isJson) {
            if (! $isJson($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: __('تعذر تنفيذ الطلب'),
                'data' => null,
                'errors' => null,
            ], $e->getStatusCode());
        });
    })->create();
