<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public self-registration. The account is always created with the
 * restricted employee role and status=inactive (pending activation by an
 * administrator) — role/status are never read from the request, so a
 * submitted "role=مدير" field cannot escalate privileges.
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => preg_replace('/[\s()\-]/', '', (string) $this->input('phone')),
        ]);
    }

    /**
     * Full Name was removed from the registration form: `name` is optional
     * and, when absent, the account stores the chosen username as its
     * display name (see AuthController::register).
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:3', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_.]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'regex:/^(?=.*\d)(?=.*\D)/', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => __('اسم المستخدم: أحرف إنجليزية صغيرة وأرقام و ( _ . ) فقط'),
            'username.unique' => __('اسم المستخدم مستخدم مسبقاً'),
            'email.unique' => __('البريد الإلكتروني مستخدم مسبقاً'),
            'password.regex' => __('كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف'),
            'password.confirmed' => __('كلمتا المرور غير متطابقتين'),
            'phone.regex' => __('أدخل رقم هاتف صحيحاً (9 إلى 15 رقماً)'),
        ];
    }
}
