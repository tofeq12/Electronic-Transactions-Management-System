<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Profile photo upload. Only the authenticated user's own photo can be
 * changed — the endpoint has no user identifier at all.
 */
class UpdateAvatarRequest extends FormRequest
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
            'avatar' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => __('اختر صورة جديدة'),
            'avatar.image' => __('الملف المرفوع يجب أن يكون صورة'),
            'avatar.mimes' => __('صيغ الصورة المسموحة: JPG أو PNG أو WEBP'),
            'avatar.max' => __('حجم الصورة لا يتجاوز 2 ميغابايت'),
        ];
    }
}
