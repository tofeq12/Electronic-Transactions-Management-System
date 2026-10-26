<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks deactivated accounts: logs them out and returns them to the login
 * screen with an explanation. Applied together with the "auth" middleware.
 *
 * وسيط 'active' (يُسجَّل في bootstrap/app.php): يتحقّق من حالة تفعيل الحساب
 * لكل طلب داخل المجموعة المحميّة 'auth'+'active'. إذا لم يكن status مساوياً
 * لـ 'active' يُنهي الجلسة (logout + invalidate + regenerateToken) ويعيد
 * المستخدم إلى /login برسالة. هكذا لا يبقى حساب معطّل داخل جلسة صالحة،
 * حتى لو كان مسجّلاً دخولاً قبل تعطيله.
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // فحص التفعيل: أي حالة غير 'active' تُخرِج المستخدم فوراً.
        if ($user && $user->status !== 'active') {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login')->withErrors([
                'email' => 'تم تعطيل حسابك — تواصل مع المسؤول',
            ]);
        }

        return $next($request);
    }
}
