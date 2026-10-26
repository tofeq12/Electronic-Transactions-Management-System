<?php

namespace App\Http\Requests\Department;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(preg_replace('/\s+/', ' ', (string) $this->input('name'))),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'name' => [
                'required', 'string', 'min:2', 'max:255',
                function (string $attribute, mixed $value, \Closure $fail) use ($id): void {
                    $exists = \App\Models\Department::whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                        ->where('id', '!=', $id)
                        ->exists();
                    if ($exists) {
                        $fail('اسم القسم مستخدم مسبقاً');
                    }
                },
            ],
            'desc' => ['nullable', 'string', 'max:2000'],
            // Phase 11 — organization structure: a department may belong to
            // a college (or none); never required, always validated.
            'college_id' => ['nullable', 'integer', 'exists:colleges,id'],
        ];
    }
}
