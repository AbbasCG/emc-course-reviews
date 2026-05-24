<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>تقرير: {{ $campaign->course_name }}</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        direction: rtl;
        text-align: right;
        color: #1e293b;
        font-size: 12px;
        line-height: 1.6;
    }

    .header {
        background: linear-gradient(135deg, #0F172A, #22334A);
        color: white;
        padding: 24px 28px;
        margin-bottom: 20px;
    }
    .header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
    }
    .header h1 {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 4px;
    }
    .header .sub {
        font-size: 11px;
        color: #94a3b8;
    }
    .header .meta {
        font-size: 10px;
        color: #cbd5e1;
        text-align: left;
    }

    .accent-bar {
        height: 4px;
        background: linear-gradient(90deg, #2691C2, #EC943C);
        margin-bottom: 20px;
    }

    .section {
        margin: 0 20px 20px;
    }
    .section-title {
        font-size: 13px;
        font-weight: bold;
        color: #0F172A;
        border-bottom: 2px solid #e2e8f0;
        padding-bottom: 6px;
        margin-bottom: 12px;
    }

    .stats-grid {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 8px;
    }
    .stats-grid td {
        width: 25%;
        padding: 12px 10px;
        text-align: center;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
    }
    .stat-value {
        font-size: 22px;
        font-weight: bold;
        color: #2691C2;
    }
    .stat-label {
        font-size: 10px;
        color: #64748b;
        margin-top: 2px;
    }

    .rating-row {
        display: flex;
        align-items: center;
        margin-bottom: 8px;
        gap: 10px;
    }
    .rating-label {
        width: 140px;
        font-size: 11px;
        color: #374151;
        flex-shrink: 0;
    }
    .rating-bar-bg {
        flex: 1;
        height: 10px;
        background: #e2e8f0;
        border-radius: 5px;
        overflow: hidden;
    }
    .rating-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #2691C2, #EC943C);
        border-radius: 5px;
    }
    .rating-value {
        width: 36px;
        font-size: 11px;
        font-weight: bold;
        color: #1e293b;
        text-align: left;
    }

    .difficulty-table {
        width: 100%;
        border-collapse: collapse;
    }
    .difficulty-table th {
        background: #1e293b;
        color: white;
        padding: 7px 10px;
        font-size: 11px;
        font-weight: bold;
    }
    .difficulty-table td {
        padding: 7px 10px;
        font-size: 11px;
        border-bottom: 1px solid #f1f5f9;
    }
    .difficulty-table tr:nth-child(even) td {
        background: #f8fafc;
    }

    .recommend-row {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 8px;
    }
    .recommend-yes {
        background: #dcfce7;
        color: #166534;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    .recommend-no {
        background: #fee2e2;
        color: #991b1b;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    .pct-bar-bg {
        flex: 1;
        height: 12px;
        background: #e2e8f0;
        border-radius: 6px;
        overflow: hidden;
    }
    .pct-bar-fill {
        height: 100%;
        background: #22c55e;
        border-radius: 6px;
    }

    .reviews-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    .reviews-table th {
        background: #0F172A;
        color: white;
        padding: 7px 8px;
        font-weight: bold;
        text-align: right;
    }
    .reviews-table td {
        padding: 6px 8px;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: top;
    }
    .reviews-table tr:nth-child(even) td {
        background: #f8fafc;
    }
    .star { color: #f59e0b; }
    .badge-yes { background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 10px; font-size: 9px; }
    .badge-no  { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 10px; font-size: 9px; }

    .footer {
        margin-top: 24px;
        padding: 14px 20px;
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

{{-- Header --}}
<div class="header">
    <div class="header-top">
        <div>
            <div class="sub">EMC Education Platform — تقرير التقييمات</div>
            <h1>{{ $campaign->course_name }}</h1>
            @if($campaign->instructor_name)
            <div class="sub">المدرب: {{ $campaign->instructor_name }}</div>
            @endif
        </div>
        <div class="meta">
            <div>تاريخ التقرير: {{ now()->format('d/m/Y') }}</div>
            @if($campaign->course_date)
            <div>تاريخ الدورة: {{ $campaign->course_date->format('d/m/Y') }}</div>
            @endif
            <div>إجمالي التقييمات: {{ $reviewCount }}</div>
        </div>
    </div>
</div>

<div class="accent-bar"></div>

{{-- Summary Stats --}}
<div class="section">
    <div class="section-title">ملخص التقييمات</div>
    <table class="stats-grid">
        <tr>
            <td>
                <div class="stat-value">{{ $averageRating }}</div>
                <div class="stat-label">التقييم العام</div>
            </td>
            <td>
                <div class="stat-value">{{ $averageContentRating }}</div>
                <div class="stat-label">المحتوى</div>
            </td>
            <td>
                <div class="stat-value">{{ $averageInstructorRating }}</div>
                <div class="stat-label">المدرب</div>
            </td>
            <td>
                <div class="stat-value">{{ $averageOrganizationRating }}</div>
                <div class="stat-label">التنظيم</div>
            </td>
        </tr>
    </table>
</div>

{{-- Ratings breakdown --}}
<div class="section">
    <div class="section-title">تفاصيل التقييمات</div>

    @php
    $ratingRows = [
        ['label' => 'التقييم العام', 'value' => $averageRating],
        ['label' => 'محتوى الدورة', 'value' => $averageContentRating],
        ['label' => 'المدرب', 'value' => $averageInstructorRating],
        ['label' => 'التنظيم والإدارة', 'value' => $averageOrganizationRating],
    ];
    @endphp

    @foreach($ratingRows as $r)
    <div class="rating-row">
        <div class="rating-label">{{ $r['label'] }}</div>
        <div class="rating-bar-bg">
            <div class="rating-bar-fill" style="width: {{ ($r['value'] / 5) * 100 }}%;"></div>
        </div>
        <div class="rating-value">{{ $r['value'] }} / 5</div>
    </div>
    @endforeach
</div>

{{-- Recommendation --}}
<div class="section">
    <div class="section-title">التوصية بالدورة</div>
    <div class="recommend-row">
        <span class="recommend-yes">يوصون بها: {{ $recommendPct }}%</span>
        <div class="pct-bar-bg">
            <div class="pct-bar-fill" style="width: {{ $recommendPct }}%;"></div>
        </div>
        <span class="recommend-no">لا يوصون: {{ 100 - $recommendPct }}%</span>
    </div>
</div>

{{-- Difficulty Distribution --}}
@if($difficultyData->isNotEmpty())
<div class="section">
    <div class="section-title">توزيع مستوى الصعوبة</div>
    @php
    $difficultyLabels = [
        'very_easy'  => 'سهلة جداً',
        'easy'       => 'سهلة',
        'medium'     => 'متوسطة',
        'hard'       => 'صعبة',
        'very_hard'  => 'صعبة جداً',
    ];
    @endphp
    <table class="difficulty-table">
        <tr>
            <th>مستوى الصعوبة</th>
            <th>عدد المشاركين</th>
            <th>النسبة</th>
        </tr>
        @foreach($difficultyLabels as $key => $label)
        @if(isset($difficultyData[$key]) && $difficultyData[$key] > 0)
        <tr>
            <td>{{ $label }}</td>
            <td>{{ $difficultyData[$key] }}</td>
            <td>{{ $reviewCount > 0 ? round($difficultyData[$key] / $reviewCount * 100) : 0 }}%</td>
        </tr>
        @endif
        @endforeach
    </table>
</div>
@endif

{{-- Reviews List --}}
@if($reviews->count() > 0)
<div class="page-break"></div>
<div class="section">
    <div class="section-title">أحدث التقييمات ({{ $reviews->count() }})</div>
    <table class="reviews-table">
        <tr>
            <th style="width:16%">الطالب</th>
            <th style="width:8%">التقييم</th>
            <th style="width:8%">يوصي</th>
            <th style="width:34%">ما أعجبه</th>
            <th style="width:34%">مقترحات التحسين</th>
        </tr>
        @foreach($reviews as $review)
        <tr>
            <td>{{ $review->student_name }}</td>
            <td style="text-align:center">
                <span class="star">★</span> {{ $review->overall_rating }}
            </td>
            <td style="text-align:center">
                @if($review->would_recommend)
                <span class="badge-yes">نعم</span>
                @else
                <span class="badge-no">لا</span>
                @endif
            </td>
            <td>{{ $review->what_did_you_like ? \Illuminate\Support\Str::limit($review->what_did_you_like, 120) : '—' }}</td>
            <td>{{ $review->what_can_be_improved ? \Illuminate\Support\Str::limit($review->what_can_be_improved, 120) : '—' }}</td>
        </tr>
        @endforeach
    </table>
</div>
@endif

<div class="footer">
    تقرير مُصدَّر من منصة EMC التعليمية — {{ now()->format('d/m/Y H:i') }}
</div>

</body>
</html>
