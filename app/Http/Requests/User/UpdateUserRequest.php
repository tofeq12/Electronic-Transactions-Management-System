<?php

namespace App\Http\Requests\User;

use App\Support\Roles;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $id = $this->route('id');

        return [
            'name' => ['required', 'string', 'min:3', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9_.]+$/', Rule::unique('users', 'username')->ignore($id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'phone' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
            // Optional: omitted when the role select is disabled (roles that
            // the current UI cannot represent — university_admin/dept_manager).
            // Any existing role (incl. custom) is a valid VALUE; whether the
            // actor may GRANT it is enforced in UserController (canAssign).
            'role' => ['nullable', Rule::in(array_merge(
                Roles::ALL,
                array_keys(Roles::FROM_LABEL),
                \Spatie\Permission\Models\Role::pluck('name')->all(),
            ))],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'password' => ['nullable', 'string', 'min:8', 'max:100', 'regex:/^(?=.*\d)(?=.*\D)/'],
            // Organizational unit assignment (optional).
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
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
