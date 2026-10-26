<?php

namespace App\Services;

use App\Models\TrxType;
use App\Models\TrxTypeField;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Phase 4 — loads configured field definitions and validates dynamic form
 * data ON THE SERVER. JavaScript validation is only a convenience; nothing
 * reaches TransactionService without passing through here first.
 */
class TypeConfigService
{
    /** Active types (with active fields) for building the submission form. */
    public function activeTypes()
    {
        return TrxType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['activeFields'])
            ->get();
    }

    /** Resolve a submitted `type` value (slug or Arabic label) to a TrxType. */
    public function resolve(string $value): ?TrxType
    {
        return TrxType::query()
            ->where('slug', $value)
            ->orWhere('name_ar', $value)
            ->first();
    }

    /**
     * Validate dynamic field values for a type.
     *
     * - Required fields must be present (checkbox → must be checked,
     *   file → at least one uploaded file for this field or an existing
     *   attachment on update).
     * - Value shapes follow field_type (number, date, select options,
     *   text/textarea max length).
     * - Unknown keys are rejected so clients cannot inject extra data.
     *
     * @param  array<string, mixed>  $data       submitted form_data (already json-decoded)
     * @param  array<int, mixed>  $uploadedFiles original names uploaded in this request
     * @param  array<int, mixed>  $existingFiles original names already attached (update)
     *
     * @throws ValidationException
     */
    public function validateFormData(?TrxType $type, array $data, array $uploadedFiles = [], array $existingFiles = []): void
    {
        if ($type === null) {
            return;
        }

        $fields = $type->activeFields()->get();

        $known = $fields->pluck('id')->map(fn($id) => 'f' . $id)->all();
        $unknown = array_diff(array_keys($data), $known);
        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'form_data' => __('حقل غير معروف في نموذج المعاملة'),
            ]);
        }

        foreach ($fields as $field) {
            $key = 'f' . $field->id;
            $raw = $data[$key] ?? null;
            $present = $this->isPresent($field, $raw, $uploadedFiles, $existingFiles);

            if ($field->is_required && ! $present) {
                throw ValidationException::withMessages([
                    "form_data.{$key}" => __('الحقل مطلوب'),
                ]);
            }

            if (! $present && $field->field_type !== 'checkbox') {
                continue; // optional and empty — nothing to shape-check
            }

            $this->checkShape($field, $key, $raw, $uploadedFiles, $existingFiles);
        }
    }

    private function isPresent(TrxTypeField $field, mixed $raw, array $uploadedFiles, array $existingFiles): bool
    {
        return match ($field->field_type) {
            'checkbox' => $raw === true || $raw === '1' || $raw === 1,
            'file' => $uploadedFiles !== [] || $existingFiles !== [],
            default => $raw !== null && $raw !== '',
        };
    }

    /**
     * @throws ValidationException
     */
    private function checkShape(TrxTypeField $field, string $key, mixed $raw, array $uploadedFiles, array $existingFiles): void
    {
        $fail = function (string $message) use ($key): void {
            throw ValidationException::withMessages(["form_data.{$key}" => $message]);
        };

        switch ($field->field_type) {
            case 'number':
                if (! is_numeric($raw)) {
                    $fail(__('يجب أن يكون قيمة رقمية'));
                }
                $min = $field->validation['min'] ?? null;
                $max = $field->validation['max'] ?? null;
                if ($min !== null && $min !== '' && (float) $raw < (float) $min) {
                    $fail(__('القيمة أقل من الحد الأدنى'));
                }
                if ($max !== null && $max !== '' && (float) $raw > (float) $max) {
                    $fail(__('القيمة أكبر من الحد الأقصى'));
                }
                break;

            case 'date':
                if (! \Illuminate\Support\Carbon::hasFormat($raw, 'Y/m/d') && ! \Illuminate\Support\Carbon::hasFormat($raw, 'Y-m-d')) {
                    $fail(__('يجب أن يكون تاريخ صحيح'));
                }
                break;

            case 'select':
                $allowed = array_map(fn($o) => is_array($o) ? ($o['ar'] ?? null) : $o, $field->options ?? []);
                if (! in_array($raw, $allowed, true)) {
                    $fail(__('القيمة المختارة غير مسموح بها'));
                }
                break;

            case 'text':
            case 'textarea':
                if (! is_string($raw)) {
                    $fail(__('يجب أن يكون نصاً'));
                }
                $maxLen = $field->validation['max_length'] ?? 5000;
                if (mb_strlen($raw) > (int) $maxLen) {
                    $fail(__('النص أطول من الحد المسموح'));
                }
                break;

            case 'checkbox':
                if (! ($raw === true || $raw === '1' || $raw === 1 || $raw === null || $raw === false || $raw === '0' || $raw === 0)) {
                    $fail(__('قيمة غير صحيحة'));
                }
                break;

            case 'file':
                if ($field->is_required && $uploadedFiles === [] && $existingFiles === []) {
                    $fail(__('الحقل مطلوب'));
                }
                break;
        }
    }

    /**
     * Decode the raw form_data input (JSON string from FormData, or array).
     *
     * @return array<string, mixed>
     */
    public static function decode(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'form_data' => __('بيانات النموذج غير صالحة'),
            ]);
        }

        return $decoded;
    }

    /**
     * Server-side sanity for the field definition itself (used by the admin
     * controller) — options required for select, types must be known.
     *
     * @return array<int, string>
     */
    public function definitionErrors(string $fieldType, ?array $options): array
    {
        $errors = [];

        if (! in_array($fieldType, TrxTypeField::TYPES, true)) {
            $errors[] = __('نوع الحقل غير مدعوم');
        }

        if ($fieldType === 'select' && (empty($options) || ! is_array($options))) {
            $errors[] = __('خانة الاختيار تتطلب خيارات');
        }

        return $errors;
    }

    /**
     * Convenience used by TransactionService: decode + validate in one call.
     *
     * @param  array<int, mixed>  $uploadedFiles
     * @param  array<int, mixed>  $existingFiles
     *
     * @throws ValidationException
     */
    public function validateRaw(?TrxType $type, mixed $raw, array $uploadedFiles = [], array $existingFiles = []): void
    {
        $this->validateFormData($type, self::decode($raw), $uploadedFiles, $existingFiles);
    }

    /** Kept for symmetry with Laravel's validator facade usage in callers. */
    public function validator(array $data, array $rules): \Illuminate\Validation\Validator
    {
        return Validator::make($data, $rules);
    }
}
