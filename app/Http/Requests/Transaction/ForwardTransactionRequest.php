<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ForwardTransactionRequest extends FormRequest
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
            'dept_id' => ['required', 'integer', 'exists:departments,id'],
            // Nobody may forward a transaction to themselves.
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::notIn([$this->user()?->id])],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.not_in' => __('لا يمكن إحالة المعاملة إلى نفسك'),
        ];
    }
}
