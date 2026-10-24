<?php

namespace App\Http\Requests;

use App\Exports\ExportCriteria;
use App\Exports\ReviewExportColumns;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('export-reviews');
    }

    public function rules(): array
    {
        return [
            'report_type'      => ['required', Rule::in(['detailed', 'summary', 'professional'])],
            'format'           => ['required', Rule::in(['xlsx', 'csv', 'pdf'])],
            'campaign_scope'   => ['required', Rule::in(['all', 'selected'])],
            'campaign_ids'     => ['exclude_unless:campaign_scope,selected', 'required', 'array', 'min:1', 'max:500'],
            'campaign_ids.*'   => ['integer', 'distinct', Rule::exists('review_campaigns', 'id')],
            'period'           => ['required', Rule::in(['all', '7', '30', '90', 'custom'])],
            'date_from'        => ['exclude_unless:period,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:2100-12-31'],
            'date_to'          => ['exclude_unless:period,custom', 'required', 'date_format:Y-m-d', 'after_or_equal:date_from', 'before_or_equal:2100-12-31'],
            'date_basis'       => ['required', Rule::in(['submitted', 'course'])],
            'activity_types'   => ['nullable', 'array', 'max:50'],
            'activity_types.*' => ['string', 'max:100'],
            'instructors'      => ['nullable', 'array', 'max:200'],
            'instructors.*'    => ['string', 'max:255'],
            'difficulty'       => ['nullable', 'array', 'max:20'],
            'difficulty.*'     => ['string', 'max:50'],
            'ratings'          => ['nullable', 'array', 'max:5'],
            'ratings.*'        => ['integer', 'between:1,5'],
            'recommend'        => ['required', Rule::in(['any', 'yes', 'no'])],
            'campaign_status'  => ['required', Rule::in(['any', 'active', 'inactive', 'expired'])],
            'columns'          => [Rule::requiredIf($this->input('report_type') === 'detailed'), 'array'],
            'columns.*'        => ['string', Rule::in(ReviewExportColumns::keys())],
            'name'             => ['nullable', 'string', 'max:120'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('report_type') === 'professional' && $this->input('format') !== 'pdf') {
                    $validator->errors()->add('format', 'التقرير الاحترافي متاح بصيغة PDF فقط.');
                }

                $pii = array_intersect((array) $this->input('columns', []), ReviewExportColumns::piiKeys());
                if ($pii && Gate::denies('export-personal-data')) {
                    $validator->errors()->add('columns', 'ليست لديك صلاحية تصدير البيانات الشخصية (الاسم والبريد الإلكتروني).');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'report_type.*'           => 'اختر نوع التقرير.',
            'format.*'                => 'اختر صيغة ملف صحيحة.',
            'campaign_ids.required'   => 'اختر حملة واحدة على الأقل.',
            'campaign_ids.min'        => 'اختر حملة واحدة على الأقل.',
            'campaign_ids.*.exists'   => 'إحدى الحملات المحددة غير موجودة.',
            'date_from.required'      => 'تاريخ البداية مطلوب للفترة المخصصة.',
            'date_to.required'        => 'تاريخ النهاية مطلوب للفترة المخصصة.',
            'date_from.date_format'   => 'صيغة تاريخ البداية غير صحيحة.',
            'date_to.date_format'     => 'صيغة تاريخ النهاية غير صحيحة.',
            'date_to.after_or_equal'  => 'تاريخ النهاية يجب أن يكون بعد تاريخ البداية أو مساوياً له.',
            'ratings.*.between'       => 'التقييم يجب أن يكون بين 1 و 5.',
            'columns.required'        => 'اختر حقلاً واحداً على الأقل للتصدير.',
            'columns.*.in'            => 'أحد الحقول المحددة غير معروف.',
            'name.max'                => 'اسم التقرير يجب ألا يتجاوز 120 حرفاً.',
        ];
    }

    public function criteria(): ExportCriteria
    {
        return ExportCriteria::fromArray($this->validated());
    }
}
