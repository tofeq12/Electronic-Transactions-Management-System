<?php

namespace App\Http\Requests\Transaction;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Returning a transaction to its submitter for completion requires two
 * mandatory written parts: the reason for the return and the missing items
 * the submitter must complete. Both are stored in the transaction log; the
 * reason is also included in the notification sent to the submitter.
 */
class ReturnTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reason' => trim((string) $this->input('reason')),
            'missing' => trim((string) $this->input('missing')),
        ]);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:1000'],
            'missing' => ['required', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => __('سبب الإعادة مطلوب'),
            'reason.min' => __('سبب الإعادة: 3 أحرف على الأقل'),
            'missing.required' => __('المطلوب استكماله مطلوب'),
            'missing.min' => __('المطلوب استكماله: 3 أحرف على الأقل'),
        ];
    }
}
