<?php

namespace App\Http\Requests\Transaction;

use App\Models\TrxType;
use App\Support\TransactionTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('date')) {
            $this->merge([
                'date' => str_replace(['-', '.'], '/', (string) $this->input('date')),
            ]);
        }
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The UI submits the Arabic label (option text has no value attr);
            // admin-defined types add their slug/ar/en names (active only).
            'type' => ['required', Rule::in(array_merge(
                TransactionTypes::SLUGS,
                TransactionTypes::LABELS,
                TrxType::activeValidationKeys(),
            ))],
            'subject' => ['required', 'string', 'max:255'],
            'from' => ['nullable', 'string', 'max:255'],
            'to' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999.99'],
            'date' => ['required', 'date_format:Y/m/d'],
            'desc' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(['normal', 'urgent'])],
            // Creation can only produce draft or pending — never done/rejected.
            'status' => ['required', Rule::in(['draft', 'pending'])],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,png,jpg,jpeg,gif,webp,doc,docx,xls,xlsx,zip'],
            // Phase 4 dynamic fields: JSON string {f<id>: value} — full
            // shape validation happens server-side in TypeConfigService.
            'form_data' => ['nullable', 'string', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.max' => __('حجم كل ملف يجب ألا يتجاوز 5 ميجابايت'),
            'files.*.mimes' => __('نوع الملف غير مسموح — الأنواع المسموحة: PDF، صور، Word، Excel، ZIP'),
            'date.date_format' => __('صيغة التاريخ: YYYY/MM/DD'),
        ];
    }
}
