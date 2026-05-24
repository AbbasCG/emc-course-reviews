<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'course_name'       => 'required|string|max:255',
            'instructor_name'   => 'nullable|string|max:255',
            'course_date'       => 'nullable|date',
            'description'       => 'nullable|string',
            'theme'             => 'nullable|string|in:default,ai,business,data',
            'expires_type'      => 'nullable|string|in:none,7,30,custom',
            'expires_custom_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'course_name.required'        => 'اسم الدورة مطلوب',
            'course_name.string'          => 'اسم الدورة يجب أن يكون نصاً',
            'course_name.max'             => 'اسم الدورة يجب ألا يتجاوز 255 حرف',
            'instructor_name.string'      => 'اسم المدرب يجب أن يكون نصاً',
            'instructor_name.max'         => 'اسم المدرب يجب ألا يتجاوز 255 حرف',
            'course_date.date'            => 'تاريخ الدورة يجب أن يكون تاريخاً صحيحاً',
            'description.string'          => 'الوصف يجب أن يكون نصاً',
            'expires_custom_date.date'    => 'تاريخ الانتهاء يجب أن يكون تاريخاً صحيحاً',
        ];
    }
}
