<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تعديل صلاحيات دور موجود (/roles/{id}/update).
 *
 * لا يقبل حقل الاسم إطلاقًا (الاسم غير قابل للتغيير — انظر
 * StoreRoleRequest)، فالمُحدَّث هو مجموعة الصلاحيات فقط عبر
 * syncPermissions (استبدال كامل لا تراكم).
 */
class UpdateRoleRequest extends FormRequest
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
            // تسمية الدور العربية قابلة للتحديث مع الصلاحيات (الاسم/slug ثابت).
            'title' => ['nullable', 'string', 'min:2', 'max:100'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    public function messages(): array
    {
        return [
            'permissions.*' => __('صلاحية غير موجودة في النظام'),
        ];
    }
}
