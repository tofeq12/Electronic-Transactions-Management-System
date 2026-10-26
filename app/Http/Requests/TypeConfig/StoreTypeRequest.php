<?php

namespace App\Http\Requests\TypeConfig;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTypeRequest extends FormRequest
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
        return [
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9_-]+$/', Rule::unique('trx_types', 'slug'),
                Rule::notIn(\App\Support\TransactionTypes::SLUGS)],
            // Custom names must never collide with the four system labels —
            // TransactionTypes::slug() resolves labels to slugs ambiguously.
            'name_ar' => ['required', 'string', 'max:100', Rule::unique('trx_types', 'name_ar'),
                Rule::notIn(array_values(\App\Support\TransactionTypes::LABELS))],
            'name_en' => ['nullable', 'string', 'max:100', Rule::unique('trx_types', 'name_en')],
            'description' => ['nullable', 'string', 'max:1000'],
            'required_documents' => ['nullable', 'array', 'max:20'],
            'required_documents.*' => ['required', 'string', 'max:150'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => __('المعرّف يقبل الحروف الصغيرة والأرقام والشرطات فقط'),
            'slug.unique' => __('هذا المعرّف مستخدم مسبقاً'),
            'slug.not_in' => __('هذا المعرّف محجوز لنوع نظامي'),
            'name_ar.unique' => __('اسم النوع مستخدم مسبقاً'),
            'name_ar.not_in' => __('هذا الاسم محجوز لنوع نظامي'),
            'name_en.unique' => __('اسم النوع الإنجليزي مستخدم مسبقاً'),
        ];
    }
}
