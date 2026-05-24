<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'student_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'overall_rating' => 'required|integer|min:1|max:5',
            'course_content_rating' => 'required|integer|min:1|max:5',
            'instructor_rating' => 'required|integer|min:1|max:5',
            'organization_rating' => 'required|integer|min:1|max:5',
            'difficulty_level' => 'required|string|in:سهل,متوسط,صعب',
            'what_did_you_like' => 'required|string',
            'what_can_be_improved' => 'required|string',
            'would_recommend' => 'required|boolean',
            'additional_notes' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'student_name.required' => 'اسم الطالب مطلوب',
            'student_name.string' => 'اسم الطالب يجب أن يكون نصاً',
            'email.email' => 'البريد الإلكتروني يجب أن يكون صحيحاً',
            'overall_rating.required' => 'التقييم الكلي مطلوب',
            'overall_rating.min' => 'التقييم يجب أن يكون بين 1 و 5',
            'overall_rating.max' => 'التقييم يجب أن يكون بين 1 و 5',
            'course_content_rating.required' => 'تقييم محتوى الدورة مطلوب',
            'course_content_rating.min' => 'التقييم يجب أن يكون بين 1 و 5',
            'course_content_rating.max' => 'التقييم يجب أن يكون بين 1 و 5',
            'instructor_rating.required' => 'تقييم المدرب مطلوب',
            'instructor_rating.min' => 'التقييم يجب أن يكون بين 1 و 5',
            'instructor_rating.max' => 'التقييم يجب أن يكون بين 1 و 5',
            'organization_rating.required' => 'تقييم التنظيم مطلوب',
            'organization_rating.min' => 'التقييم يجب أن يكون بين 1 و 5',
            'organization_rating.max' => 'التقييم يجب أن يكون بين 1 و 5',
            'difficulty_level.required' => 'مستوى الصعوبة مطلوب',
            'difficulty_level.in' => 'مستوى الصعوبة غير صحيح',
            'what_did_you_like.required' => 'ما الذي أعجبك في الدورة مطلوب',
            'what_can_be_improved.required' => 'ما الذي يمكن تحسينه مطلوب',
            'would_recommend.required' => 'هل توصي بالدورة مطلوب',
        ];
    }
}
