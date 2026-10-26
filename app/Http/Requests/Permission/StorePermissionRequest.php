<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء صلاحية مخصصة عبر شاشة الصلاحيات (/permissions).
 *
 * الاسم slug (مثل reports.export) ويُستخدم كما هو في $user->can(...)
 * والبوابات can:...، لذا الشكل صارم والتحقق من التكرار على
 * (name + guard). الصلاحيات الأساسية محمية من الحذف في المتحكّم لا هنا.
 */
class StorePermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => strtolower(trim((string) $this->input('name'))),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z][a-z0-9_.]*$/',
                Rule::unique('permissions', 'name')->where('guard_name', 'web'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('معرّف الصلاحية: حروف إنجليزية صغيرة وأرقام و ( _ . ) فقط ويبدأ بحرف'),
            'name.unique' => __('الصلاحية موجودة مسبقاً'),
        ];
    }
}
