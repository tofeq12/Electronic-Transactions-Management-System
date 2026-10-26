<?php

namespace App\Http\Requests\Role;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء دور جديد عبر شاشة الأدوار (/roles).
 *
 * المعرّف (name = slug) يُثبَّت عند الإنشاء ولا يُسمح بتغييره بعد ذلك:
 * عمود users.role وخطوات سير الموافقات (target_role) يخزّنانه حرفيًا،
 * فأي إعادة تسمية تُوحِّد السجلات القديمة. التحقق من الحارس يمر عبر
 * بوابة can:roles.manage والسياسة داخل المتحكّم، لذا authorize() صريح.
 */
class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title'));

        $this->merge([
            'name' => strtolower(trim((string) $this->input('name'))),
            'title' => $title === '' ? null : $title,
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
                'min:2',
                'max:50',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', 'web'),
            ],
            // تسمية الدور العربية للعرض (اختيارية — بدونها يُشتق العرض من
            // roles.name عبر Roles::label).
            'title' => ['nullable', 'string', 'min:2', 'max:100'],
            // الصلاحيات المحدَّدة (أسماء من جدول permissions) — قد تكون فارغة
            // عند الإنشاء ثم تُسنَد لاحقًا من نموذج التعديل.
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'web')],
        ];
    }

    public function messages(): array
    {
        return [
            'name.regex' => __('معرّف الدور: حروف إنجليزية صغيرة وأرقام و ( _ ) فقط ويبدأ بحرف'),
            'name.unique' => __('معرّف الدور مستخدم مسبقاً'),
            'permissions.*' => __('صلاحية غير موجودة في النظام'),
        ];
    }
}
