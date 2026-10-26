<?php

namespace App\Http\Requests\TypeConfig;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('types.manage') ?? false;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            // System types: controller strips slug/name_ar/name_en before
            // update, so uniqueness here only protects custom types.
            'slug' => ['sometimes', 'required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('trx_types', 'slug')->ignore($id)],
            'name_ar' => ['sometimes', 'required', 'string', 'max:100', Rule::unique('trx_types', 'name_ar')->ignore($id)],
            'name_en' => ['nullable', 'string', 'max:100', Rule::unique('trx_types', 'name_en')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'required_documents' => ['nullable', 'array', 'max:20'],
            'required_documents.*' => ['required', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('المعرّف يقبل الحروف الصغيرة والأرقام والشرطات فقط'),
            'slug.unique' => __('هذا المعرّف مستخدم مسبقاً'),
            'name_ar.unique' => __('اسم النوع مستخدم مسبقاً'),
            'name_en.unique' => __('اسم النوع الإنجليزي مستخدم مسبقاً'),
        ];
    }
}
