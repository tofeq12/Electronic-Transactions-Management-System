<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'max:100', 'regex:/^(?=.*\d)(?=.*\D)/', 'different:current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password' => __('كلمة المرور الحالية غير صحيحة'),
            'password.regex' => __('كلمة المرور 8 أحرف على الأقل وتجمع بين الأرقام والحروف'),
            'password.different' => __('كلمة المرور الجديدة يجب أن تختلف عن الحالية'),
        ];
    }
}
