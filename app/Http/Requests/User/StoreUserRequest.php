<?php

namespace App\Http\Requests\User;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => preg_replace('/[\s()\-]/', '', (string) $this->input('phone')),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_.]+$/', Rule::unique('users', 'username')],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'regex:/^(?=.*\d)(?=.*\D)/'],
            // Organizational unit assignment (optional).
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            // The UI submits the Arabic label; slugs are accepted too, and
            // any role that actually exists (incl. custom ones from the new
            // roles screen). Whether the actor may GRANT it is a separate
            // business rule enforced in UserController (Roles::canAssign).
            'role' => ['required', Rule::in(array_merge(
                Roles::ALL,
                array_keys(Roles::FROM_LABEL),
                \Spatie\Permission\Models\Role::pluck('name')->all(),
            ))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => __('اسم المستخدم: أحرف إنجليزية صغيرة وأرقام و ( _ . ) فقط'),
            'username.unique' => __('اسم المستخدم مستخدم مسبقاً'),
            'email.unique' => __('البريد الإلكتروني مستخدم مسبقاً'),
            'password.regex' => __('كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف'),
            'phone.regex' => __('أدخل رقم هاتف صحيحاً (9 إلى 15 رقماً)'),
        ];
    }
}
