<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login page.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('pages.login');
    }

    /**
     * Session login: username or email + password, throttled, hashed.
     */
    public function login(LoginRequest $request)
    {
        $identifier = (string) $request->input('email');
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $credentials = [
            $field => $identifier,
            'password' => (string) $request->input('password'),
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'بيانات الدخول غير صحيحة',
            ]);
        }

        $user = Auth::user();

        // فحص التفعيل: الحساب غير المفعّل يُرفض فوراً (logout + تجديد الجلسة)
        // ولا يُسمح له بدخول النظام ولو كانت كلمة المرور صحيحة.
        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => 'الحساب غير مفعل — تواصل مع المسؤول',
            ]);
        }

        $request->session()->regenerate();

        if ($request->expectsJson()) {
            return $this->json(true, 'تم تسجيل الدخول بنجاح', ['redirect' => '/dashboard']);
        }

        return redirect()->intended('/dashboard');
    }

    /**
     * GET logout — preserves the existing sidebar logout link design.
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    /**
     * Registration page (guests only).
     */
    public function showRegister()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('pages.register');
    }

    /**
     * Public self-registration. The account is ALWAYS created with the
     * restricted employee role and status=inactive — it becomes usable only
     * after an administrator activates it from the users screen. No
     * request field can influence role or status (privilege escalation
     * is impossible by construction).
     *
     * التسجيل الذاتي العلني: يفرض الدور (employee) والحالة (inactive) من داخل
     * الشيفرة دائماً، فلا تؤثر أي حقل من الطلب علىهما — مستحيل أن يرفع
     * مسجّلٌ نفسه إلى مدير. الحساب لا يصبح صالحاً للاستخدام إلا بعد تفعيل
     * المدير من شاشة المستخدمين.
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create([
            // The Full Name field no longer exists on the form: the chosen
            // username becomes the account's display name.
            'name' => ($data['name'] ?? '') !== '' ? $data['name'] : $data['username'],
            'username' => $data['username'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password' => $data['password'],
            // الدور مثبّت هنا (لا من الطلب): موظف دائماً + غير مفعّل.
            'role' => Roles::EMPLOYEE,
            'status' => 'inactive',
        ]);

        ActivityLog::create([
            'user_id' => $user->id,
            'action' => 'إنشاء حساب جديد بانتظار التفعيل',
            'target' => $user->username,
        ]);

        return redirect('/login')->with('registered', true);
    }

    /* ------------------------------------------- Phase 9 — recovery */

    /**
     * Forgot-password page (guests only).
     */
    public function showForgotPassword()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('pages.forgot-password');
    }

    /**
     * Phase 9 — request a reset link (real Password broker flow).
     *
     * - Anti-enumeration: the reply is identical whether the address
     *   exists or not; only the throttle can differ.
     * - Honesty gate: without a REAL mail transport ('log' merely writes
     *   the message into storage/logs — that is not delivery) the
     *   endpoint refuses plainly and points at the administrator-assisted
     *   reset instead of faking a "sent" confirmation. The gate runs
     *   BEFORE any user lookup, so it reveals nothing either.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower(trim((string) $request->input('email')));

        if (! $this->mailDeliverable()) {
            $msg = 'استعادة كلمة المرور بالبريد غير مفعّلة حالياً — تواصل مع مسؤول النظام لإعادة تعيين كلمة المرور';

            return $request->expectsJson()
                ? $this->json(false, $msg, null, [], 503)
                : back()->withErrors(['email' => $msg]);
        }

        $status = Password::broker()->sendResetLink(['email' => $email]);

        if ($status === Password::RESET_THROTTLED) {
            $msg = 'انتظر دقيقة قبل طلب رابط إعادة تعيين جديد';

            return $request->expectsJson()
                ? $this->json(false, $msg, null, [], 422)
                : back()->withErrors(['email' => $msg]);
        }

        if ($status === Password::RESET_LINK_SENT) {
            \App\Support\Activity::log(null, 'إرسال رابط استرجاع كلمة مرور', $email);
        }

        // INVALID_USER and RESET_LINK_SENT share one reply — an attacker
        // cannot learn which addresses exist in the system.
        return $request->expectsJson()
            ? $this->json(true, 'إذا كان البريد مسجلاً فسيصلك رابط إعادة تعيين كلمة المرور خلال دقائق')
            : back()->with('reset_sent', true);
    }

    /**
     * Reset page. Always renders (the token/email travel in the query
     * string of the emailed link); the page script bounces visitors who
     * arrive without them back to /forgot-password.
     */
    public function showResetPassword()
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return view('pages.reset-password');
    }

    /**
     * Phase 9 — consume the emailed token and set the new password. The
     * broker validates existence + expiry + one-time use before the
     * callback runs; the password lands hashed through the model cast.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'regex:/^(?=.*\d)(?=.*\D)/', 'confirmed'],
        ], [
            'password.regex' => 'كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف',
            'password.confirmed' => 'كلمتا المرور غير متطابقتين',
        ]);

        $status = Password::broker()->reset(
            [
                'email' => strtolower(trim((string) $request->input('email'))),
                'token' => (string) $request->input('token'),
                'password' => (string) $request->input('password'),
                'password_confirmation' => (string) $request->input('password_confirmation'),
            ],
            function ($user, string $password) {
                $user->forceFill(['password' => $password])->save();

                \App\Support\Activity::log($user, 'إعادة تعيين كلمة المرور', $user->username);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return $request->expectsJson()
                ? $this->json(true, 'تم إعادة تعيين كلمة المرور — يمكنك تسجيل الدخول', ['redirect' => '/login'])
                : redirect('/login')->with('password_reset', true);
        }

        $msg = 'رابط إعادة التعيين غير صالح أو منتهي الصلاحية';

        return $request->expectsJson()
            ? $this->json(false, $msg, null, [], 422)
            : back()->withErrors(['email' => $msg]);
    }

    /**
     * Only REAL transports deliver mail. The 'log' driver writes messages
     * into the app log — presenting that as a sent link would be a lie,
     * so recovery stays honestly closed until SMTP (or another real
     * driver) is configured. 'array' exists for the test suite only,
     * where it captures the notification for assertions.
     */
    private function mailDeliverable(): bool
    {
        $driver = (string) config('mail.default');

        $real = ['smtp', 'mailgun', 'ses', 'postmark', 'resend', 'sendmail'];

        return app()->environment('testing')
            ? in_array($driver, ['array', ...$real], true)
            : in_array($driver, $real, true);
    }
}
