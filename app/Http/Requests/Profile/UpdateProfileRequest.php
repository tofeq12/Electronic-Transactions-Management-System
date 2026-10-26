<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => preg_replace('/[\s()\-]/', '', (string) $this->input('phone')),
        ]);
    }

    /**
     * Username is immutable from the profile screen (matches the existing UI).
     * Full Name was removed from this form — `name` is optional here and, when
     * absent, the stored name is left untouched.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'min:3', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'phone' => ['required', 'string', 'regex:/^\+?\d{9,15}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => __('البريد الإلكتروني مستخدم مسبقاً'),
            'phone.regex' => __('أدخل رقم هاتف صحيحاً (9 إلى 15 رقماً)'),
        ];
    }
}
