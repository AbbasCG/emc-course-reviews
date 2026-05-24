<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>تقرير تقييمات — {{ $campaign->course_name }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'DejaVu Sans', Arial, sans-serif;
        direction: rtl;
        text-align: right;
        color: #1e293b;
        font-size: 11.5px;
        line-height: 1.55;
        background: #fff;
    }

    .header {
        background: #0F172A;
        color: #fff;
        padding: 22px 28px 18px;
    }
    .header-inner {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .header-logo { width: 70px; height: auto; }
    .header-text h1 { font-size: 17px; font-weight: bold; margin-bottom: 3px; color: #fff; }
    .header-text .sub { font-size: 10px; color: #94a3b8; }
    .header-meta { font-size: 10px; color: #cbd5e1; text-align: left; line-height: 1.7; }
    .accent-bar { height: 4px; background: linear-gradient(to left, #0F172A, #EC943C, #2691C2); }

    .section { margin: 16px 22px; }
    .section-title {
        font-size: 12px;
        font-weight: bold;
        color: #0F172A;
        border-bottom: 1.5px solid #e2e8f0;
        padding-bottom: 5px;
        margin-bottom: 10px;
    }

    .info-grid { width: 100%; border-collapse: collapse; }
    .info-grid td { padding: 6px 10px; font-size: 11px; border: 1px solid #f1f5f9; background: #f8fafc; }
    .info-grid .lbl { font-weight: bold; color: #64748b; width: 120px; }

    .stats-table { width: 100%; border-collapse: separate; border-spacing: 5px; }
    .stats-table td { padding: 12px 8px; text-align: center; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 5px; }
    .stat-value { font-size: 21px; font-weight: bold; color: #2691C2; display: block; }
    .stat-label { font-size: 10px; color: #64748b; margin-top: 2px; }

    .rating-row { margin-bottom: 7px; }
    .rating-row-inner { display: flex; align-items: center; gap: 8px; }
    .rating-lbl { width: 130px; font-size: 11px; color: #374151; }
    .bar-bg { flex: 1; height: 9px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
    .bar-fill { height: 100%; background: #2691C2; border-radius: 4px; }
    .bar-val { width: 38px; font-size: 10.5px; font-weight: bold; color: #1e293b; text-align: left; }

    .recommend-row { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    .badge-yes { background: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; }
    .badge-no  { background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; }
    .pct-bar-bg  { flex: 1; height: 11px; background: #e2e8f0; border-radius: 5px; overflow: hidden; }
    .pct-bar-fill { height: 100%; background: #22c55e; border-radius: 5px; }

    .diff-table { width: 100%; border-collapse: collapse; font-size: 11px; }
    .diff-table th { background: #1e293b; color: #fff; padding: 6px 10px; font-weight: bold; }
    .diff-table td { padding: 6px 10px; border-bottom: 1px solid #f1f5f9; }
    .diff-table tr:nth-child(even) td { background: #f8fafc; }

    .reviews-table { width: 100%; border-collapse: collapse; font-size: 10.5px; }
    .reviews-table th { background: #0F172A; color: #fff; padding: 6px 8px; font-weight: bold; text-align: right; }
    .reviews-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    .reviews-table tr:nth-child(even) td { background: #f8fafc; }
    .star { color: #f59e0b; }
    .rb-yes { background: #dcfce7; color: #166534; padding: 1px 6px; border-radius: 8px; font-size: 9px; }
    .rb-no  { background: #fee2e2; color: #991b1b; padding: 1px 6px; border-radius: 8px; font-size: 9px; }

    .footer {
        margin-top: 22px;
        padding: 11px 22px;
        background: #f8fafc;
        border-top: 1px solid #e2e8f0;
        text-align: center;
        font-size: 10px;
        color: #94a3b8;
    }
    .page-break { page-break-before: always; }
</style>
</head>
<body>

<div class="header">
    <div class="header-inner">
        <div class="header-text">
            <div class="sub" style="margin-bottom:4px;">EMC Education Platform — تقرير التقييمات</div>
            <h1>{{ $campaign->course_name }}</h1>
            @if($campaign->instructor_name)
            <div class="sub">المدرب: {{ $campaign->instructor_name }}</div>
            @endif
        </div>
        <div>
            <img src="{{ public_path('assets/logo/emclogo.png') }}" alt="EMC" class="header-logo">
            <div class="header-meta" style="margin-top:6px;">
                <div>تاريخ التقرير: {{ now()->format('d/m/Y') }}</div>
                @if($campaign->course_date)
                <div>تاريخ الدورة: {{ $campaign->course_date->format('d/m/Y') }}</div>
                @endif
                <div>إجمالي التقييمات: {{ $stats['reviewCount'] }}</div>
            </div>
        </div>
    </div>
</div>
<div class="accent-bar"></div>

{{-- Campaign Info --}}
<div class="section">
    <div class="section-title">معلومات الحملة</div>
    <table class="info-grid">
        <tr>
            <td class="lbl">اسم الدورة</td>
            <td>{{ $campaign->course_name }}</td>
            <td class="lbl">الحالة</td>
            <td>{{ $campaign->isAvailable() ? 'نشطة' : ($campaign->isExpired() ? 'منتهية الصلاحية' : 'متوقفة') }}</td>
        </tr>
        <tr>
            <td class="lbl">المدرب</td>
            <td>{{ $campaign->instructor_name ?? '—' }}</td>
            <td class="lbl">تاريخ الإنشاء</td>
            <td>{{ $campaign->created_at->format('d/m/Y') }}</td>
        </tr>
        @if($campaign->course_date || $campaign->expires_at)
        <tr>
            <td class="lbl">تاريخ الدورة</td>
            <td>{{ $campaign->course_date ? $campaign->course_date->format('d/m/Y') : '—' }}</td>
            <td class="lbl">انتهاء الصلاحية</td>
            <td>{{ $campaign->expires_at ? $campaign->expires_at->format('d/m/Y') : 'بدون انتهاء' }}</td>
        </tr>
        @endif
    </table>
</div>

{{-- Summary Stats --}}
<div class="section">
    <div class="section-title">ملخص التقييمات</div>
    <table class="stats-table">
        <tr>
            <td>
                <span class="stat-value">{{ $stats['reviewCount'] }}</span>
                <span class="stat-label">إجمالي التقييمات</span>
            </td>
            <td>
                <span class="stat-value">{{ $stats['averageRating'] }}</span>
                <span class="stat-label">التقييم العام</span>
            </td>
            <td>
                <span class="stat-value">{{ $stats['averageContentRating'] }}</span>
                <span class="stat-label">محتوى الدورة</span>
            </td>
            <td>
                <span class="stat-value">{{ $stats['averageInstructorRating'] }}</span>
                <span class="stat-label">المدرب</span>
            </td>
            <td>
                <span class="stat-value">{{ $stats['averageOrganizationRating'] }}</span>
                <span class="stat-label">التنظيم</span>
            </td>
        </tr>
    </table>
</div>

{{-- Rating Bars --}}
<div class="section">
    <div class="section-title">تفاصيل متوسطات التقييم</div>
    @php
    $ratingRows = [
        ['التقييم العام',     $stats['averageRating']],
        ['محتوى الدورة',     $stats['averageContentRating']],
        ['المدرب',           $stats['averageInstructorRating']],
        ['التنظيم والإدارة', $stats['averageOrganizationRating']],
    ];
    @endphp
    @foreach($ratingRows as [$lbl, $val])
    <div class="rating-row">
        <div class="rating-row-inner">
            <div class="rating-lbl">{{ $lbl }}</div>
            <div class="bar-bg"><div class="bar-fill" style="width:{{ ($val / 5) * 100 }}%;"></div></div>
            <div class="bar-val">{{ $val }} / 5</div>
        </div>
    </div>
    @endforeach
</div>

{{-- Recommendation --}}
<div class="section">
    <div class="section-title">نسبة التوصية بالدورة</div>
    <div class="recommend-row">
        <span class="badge-yes">يوصون بها: {{ $stats['recommendPct'] }}%</span>
        <div class="pct-bar-bg"><div class="pct-bar-fill" style="width:{{ $stats['recommendPct'] }}%;"></div></div>
        <span class="badge-no">لا يوصون: {{ 100 - $stats['recommendPct'] }}%</span>
    </div>
</div>

{{-- Difficulty Distribution --}}
@if($stats['difficultyData']->isNotEmpty())
<div class="section">
    <div class="section-title">توزيع مستوى الصعوبة</div>
    <table class="diff-table">
        <tr>
            <th>مستوى الصعوبة</th>
            <th>عدد المشاركين</th>
            <th>النسبة</th>
        </tr>
        @foreach($stats['difficultyData'] as $level => $cnt)
        <tr>
            <td>{{ $level }}</td>
            <td>{{ $cnt }}</td>
            <td>{{ $stats['reviewCount'] > 0 ? round($cnt / $stats['reviewCount'] * 100) : 0 }}%</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

{{-- Reviews List --}}
@if($reviews->count() > 0)
<div class="page-break"></div>
<div class="section">
    <div class="section-title">تعليقات الطلاب ({{ $reviews->count() }})</div>
    <table class="reviews-table">
        <tr>
            <th style="width:15%">الطالب</th>
            <th style="width:8%">التقييم</th>
            <th style="width:7%">يوصي</th>
            <th style="width:8%">الصعوبة</th>
            <th style="width:31%">ما أعجبه</th>
            <th style="width:31%">مقترحات التحسين</th>
        </tr>
        @foreach($reviews as $review)
        <tr>
            <td>{{ $review->student_name }}</td>
            <td style="text-align:center"><span class="star">★</span> {{ $review->overall_rating }}</td>
            <td style="text-align:center">
                @if($review->would_recommend)
                <span class="rb-yes">نعم</span>
                @else
                <span class="rb-no">لا</span>
                @endif
            </td>
            <td>{{ $review->difficulty_level }}</td>
            <td>{{ $review->what_did_you_like ? \Illuminate\Support\Str::limit($review->what_did_you_like, 100) : '—' }}</td>
            <td>{{ $review->what_can_be_improved ? \Illuminate\Support\Str::limit($review->what_can_be_improved, 100) : '—' }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

<div class="footer">
    تقرير مُصدَّر من منصة EMC التعليمية &nbsp;|&nbsp; {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; emc-review-report-{{ $campaign->id }}.pdf
</div>

</body>
</html>
