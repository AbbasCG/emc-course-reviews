<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تقرير تقييمات — {{ $campaign->course_name }}</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: cairo, sans-serif;
    direction: rtl;
    text-align: right;
    color: #1e293b;
    font-size: 11px;
    line-height: 1.6;
    background: #ffffff;
}

/* ─── PAGE HEADER ─── */
.page-header {
    background-color: #22334A;
    padding: 20px 24px 16px;
    margin-bottom: 0;
}
.header-table {
    width: 100%;
    border-collapse: collapse;
}
.header-left {
    width: 65%;
    vertical-align: middle;
}
.header-right {
    width: 35%;
    text-align: left;
    vertical-align: middle;
    border-right: 1px solid rgba(255,255,255,0.15);
    padding-right: 20px;
}
.campaign-eyebrow {
    font-size: 9px;
    color: #94a3b8;
    display: block;
    margin-bottom: 5px;
}
.campaign-title {
    font-size: 18px;
    font-weight: bold;
    color: #ffffff;
    display: block;
    margin-bottom: 4px;
}
.campaign-meta {
    font-size: 10px;
    color: #94a3b8;
    display: block;
}
.brand-emc {
    font-size: 26px;
    font-weight: bold;
    color: #EC943C;
    letter-spacing: 4px;
    display: block;
}
.brand-sub {
    font-size: 8px;
    color: #64748b;
    display: block;
    margin-top: 3px;
}
.brand-label {
    font-size: 8px;
    color: #EC943C;
    display: block;
    margin-top: 6px;
    font-weight: bold;
}

/* ─── ACCENT BAR ─── */
.accent-bar { height: 5px; background-color: #EC943C; }

/* ─── META ROW ─── */
.meta-row {
    background-color: #f8fafc;
    padding: 7px 24px;
    border-bottom: 1px solid #e2e8f0;
}
.meta-table { width: 100%; border-collapse: collapse; }
.meta-table td { font-size: 9px; color: #64748b; padding: 0 10px 0 0; vertical-align: middle; }
.meta-val { font-weight: bold; color: #374151; }

/* ─── BODY ─── */
.body-wrap { padding: 20px 24px 16px; }

/* ─── SECTION HEADING ─── */
.sec-head {
    display: block;
    font-size: 10px;
    font-weight: bold;
    color: #ffffff;
    background-color: #22334A;
    padding: 5px 12px;
    margin-bottom: 12px;
    border-right: 4px solid #EC943C;
}

/* ─── STAT BOXES ─── */
.stats-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 5px 0;
    margin-bottom: 20px;
}
.stats-table td {
    text-align: center;
    background-color: #f8fafc;
    border: 1px solid #e2e8f0;
    border-top: 3px solid #2691C2;
    padding: 10px 6px 8px;
    border-radius: 4px;
}
.stat-orange { border-top-color: #EC943C !important; }
.stat-green  { border-top-color: #22c55e !important; }
.stat-num    { font-size: 22px; font-weight: bold; color: #22334A; display: block; line-height: 1.15; }
.stat-star   { font-family: emcstars; color: #EC943C; font-size: 14px; font-weight: bold; }
.stat-pct    { color: #22c55e; font-size: 15px; font-weight: bold; }
.stat-cap    { font-size: 8.5px; color: #64748b; display: block; margin-top: 3px; }

/* ─── CAMPAIGN INFO TABLE ─── */
.camp-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
    font-size: 10.5px;
}
.camp-table td { padding: 6px 10px; border: 1px solid #e2e8f0; vertical-align: top; }
.lbl-cell {
    background-color: #f1f5f9;
    color: #475569;
    font-weight: bold;
    width: 14%;
    white-space: nowrap;
}
.val-cell  { background-color: #ffffff; color: #1e293b; width: 36%; }
.s-active  { background-color: #dcfce7; color: #15803d; padding: 2px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; }
.s-expired { background-color: #fee2e2; color: #dc2626; padding: 2px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; }
.s-stopped { background-color: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 8px; font-size: 9px; font-weight: bold; }

/* ─── RATING ROWS ─── */
.rating-section { margin-bottom: 20px; }
.rt-row { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.rt-row td { vertical-align: middle; padding: 2px 4px; }
.rt-name  { width: 26%; font-size: 10.5px; color: #374151; font-weight: bold; }
.rt-stars { width: 15%; font-family: emcstars; font-size: 13px; color: #EC943C; white-space: nowrap; letter-spacing: -1px; }
.rt-bar   { }
.rt-bg    { background-color: #e8ecf0; height: 8px; border-radius: 4px; }
.rt-fill  { height: 8px; border-radius: 4px; display: block; }
.rt-score { width: 9%; text-align: left; font-size: 10px; font-weight: bold; color: #22334A; white-space: nowrap; }

/* ─── RECOMMENDATION ─── */
.rec-section { margin-bottom: 20px; }
.rec-table { width: 100%; border-collapse: collapse; }
.rec-table td { vertical-align: middle; padding: 4px; }
.rec-bar-bg   { background-color: #e2e8f0; height: 12px; border-radius: 6px; }
.rec-bar-fill { background-color: #22c55e; height: 12px; border-radius: 6px; display: block; }
.badge-yes { background-color: #dcfce7; color: #15803d; padding: 3px 9px; border-radius: 10px; font-size: 10px; font-weight: bold; }
.badge-no  { background-color: #fee2e2; color: #dc2626; padding: 3px 9px; border-radius: 10px; font-size: 10px; font-weight: bold; }

/* ─── DIFFICULTY TABLE ─── */
.diff-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10.5px;
    margin-bottom: 20px;
}
.diff-table th {
    background-color: #22334A;
    color: #ffffff;
    padding: 6px 12px;
    text-align: right;
    font-weight: bold;
}
.diff-table td {
    padding: 6px 12px;
    border-bottom: 1px solid #f1f5f9;
}
.diff-table tr:nth-child(even) td { background-color: #f8fafc; }

/* ─── REVIEWS TABLE ─── */
.reviews-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5px;
}
.reviews-table th {
    background-color: #22334A;
    color: #ffffff;
    padding: 7px 8px;
    text-align: right;
    font-weight: bold;
}
.reviews-table td {
    padding: 6px 8px;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
    color: #374151;
}
.reviews-table tr:nth-child(even) td { background-color: #f8fafc; }
.rv-stars  { font-family: emcstars; color: #EC943C; font-size: 11px; letter-spacing: -1px; }
.rv-badge-y { background-color: #dcfce7; color: #15803d; padding: 1px 5px; border-radius: 7px; font-size: 8.5px; font-weight: bold; }
.rv-badge-n { background-color: #fee2e2; color: #dc2626; padding: 1px 5px; border-radius: 7px; font-size: 8.5px; font-weight: bold; }

/* ─── FOOTER ─── */
.page-footer {
    background-color: #22334A;
    padding: 8px 24px;
    margin-top: 24px;
}
.footer-table { width: 100%; border-collapse: collapse; }
.footer-table td { font-size: 8.5px; color: #94a3b8; padding: 0; vertical-align: middle; }
.footer-brand { text-align: left; color: #EC943C; font-weight: bold; font-size: 10px; letter-spacing: 2px; }

/* ─── PAGE BREAK ─── */
.page-break { page-break-before: always; }
</style>
</head>
<body>

{{-- ════ HEADER ════ --}}
<div class="page-header">
    <table class="header-table">
        <tr>
            <td class="header-left">
                <span class="campaign-eyebrow">تقرير تقييمات الدورة التدريبية</span>
                <span class="campaign-title">{{ $campaign->course_name }}</span>
                @if($campaign->instructor_name)
                <span class="campaign-meta">المدرب: {{ $campaign->instructor_name }}</span>
                @endif
            </td>
            <td class="header-right">
                @php $logoPath = \App\Exports\Writers\PdfFactory::logoPath(); @endphp
                @if($logoPath)
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPath)) }}"
                     style="width:55px;height:auto;display:block;margin-bottom:6px;margin-left:auto;">
                @else
                <span class="brand-emc">EMC</span>
                @endif
                <span class="brand-sub">Education Management Center</span>
                <span class="brand-label">تقرير تقييمات رسمي</span>
            </td>
        </tr>
    </table>
</div>
<div class="accent-bar"></div>

{{-- ════ META ROW ════ --}}
<div class="meta-row">
    <table class="meta-table">
        <tr>
            <td>تاريخ التقرير:&nbsp;<span class="meta-val">{!! \App\Exports\Writers\PdfFactory::ltr(now()->format('d/m/Y')) !!}</span></td>
            @if($campaign->course_date)
            <td>تاريخ الدورة:&nbsp;<span class="meta-val">{!! \App\Exports\Writers\PdfFactory::ltr($campaign->course_date->format('d/m/Y')) !!}</span></td>
            @endif
            <td>إجمالي التقييمات:&nbsp;<span class="meta-val">{{ $stats['total_reviews'] }}</span></td>
            @if($campaign->expires_at)
            <td>الصلاحية:&nbsp;<span class="meta-val">{!! \App\Exports\Writers\PdfFactory::ltr($campaign->expires_at->format('d/m/Y')) !!}</span></td>
            @endif
        </tr>
    </table>
</div>

<div class="body-wrap">

{{-- ════ STATS ════ --}}
<span class="sec-head">ملخص النتائج</span>
<table class="stats-table">
    <tr>
        <td>
            <span class="stat-num">{{ $stats['total_reviews'] }}</span>
            <span class="stat-cap">إجمالي التقييمات</span>
        </td>
        <td class="stat-orange">
            <span class="stat-num">{{ $stats['overall_avg'] }}&nbsp;<span class="stat-star">★</span></span>
            <span class="stat-cap">التقييم العام</span>
        </td>
        <td>
            <span class="stat-num">{{ $stats['content_avg'] }}&nbsp;<span class="stat-star">★</span></span>
            <span class="stat-cap">محتوى الدورة</span>
        </td>
        <td>
            <span class="stat-num">{{ $stats['instructor_avg'] }}&nbsp;<span class="stat-star">★</span></span>
            <span class="stat-cap">أداء المدرب</span>
        </td>
        <td>
            <span class="stat-num">{{ $stats['organization_avg'] }}&nbsp;<span class="stat-star">★</span></span>
            <span class="stat-cap">التنظيم</span>
        </td>
        <td class="stat-green">
            <span class="stat-num"><span class="stat-pct">{!! \App\Exports\Writers\PdfFactory::ltr($stats['recommend_percentage'] . '%') !!}</span></span>
            <span class="stat-cap">نسبة التوصية</span>
        </td>
    </tr>
</table>

{{-- ════ CAMPAIGN INFO ════ --}}
<span class="sec-head">معلومات الحملة</span>
<table class="camp-table">
    <tr>
        <td class="lbl-cell">اسم الدورة</td>
        <td class="val-cell">{{ $campaign->course_name }}</td>
        <td class="lbl-cell">المدرب</td>
        <td class="val-cell">{{ $campaign->instructor_name ?: '—' }}</td>
    </tr>
    <tr>
        <td class="lbl-cell">تاريخ الدورة</td>
        <td class="val-cell">{!! $campaign->course_date ? \App\Exports\Writers\PdfFactory::ltr($campaign->course_date->format('d/m/Y')) : '—' !!}</td>
        <td class="lbl-cell">تاريخ الإنشاء</td>
        <td class="val-cell">{!! \App\Exports\Writers\PdfFactory::ltr($campaign->created_at->format('d/m/Y')) !!}</td>
    </tr>
    <tr>
        <td class="lbl-cell">انتهاء الصلاحية</td>
        <td class="val-cell">{!! $campaign->expires_at ? \App\Exports\Writers\PdfFactory::ltr($campaign->expires_at->format('d/m/Y')) : 'بدون انتهاء' !!}</td>
        <td class="lbl-cell">الحالة</td>
        <td class="val-cell">
            @if($campaign->isAvailable())
            <span class="s-active">نشطة</span>
            @elseif($campaign->isExpired())
            <span class="s-expired">منتهية</span>
            @else
            <span class="s-stopped">متوقفة</span>
            @endif
        </td>
    </tr>
    @if($campaign->description)
    <tr>
        <td class="lbl-cell">الوصف</td>
        <td class="val-cell" colspan="3">{{ $campaign->description }}</td>
    </tr>
    @endif
</table>

@if($stats['total_reviews'] > 0)

{{-- ════ RATING AVERAGES ════ --}}
<span class="sec-head">متوسطات التقييم</span>
<div class="rating-section">
@php
$ratings = [
    ['التقييم العام',     $stats['overall_avg'],     '#EC943C'],
    ['محتوى الدورة',     $stats['content_avg'],     '#2691C2'],
    ['أداء المدرب',       $stats['instructor_avg'],  '#2691C2'],
    ['التنظيم والإدارة', $stats['organization_avg'], '#64748b'],
];
@endphp
@foreach($ratings as [$lbl, $val, $clr])
@php
    $filled = (int) floor($val);
    $stars  = str_repeat('★', $filled) . str_repeat('☆', 5 - $filled);
@endphp
<table class="rt-row">
    <tr>
        <td class="rt-name">{{ $lbl }}</td>
        <td class="rt-stars">{{ $stars }}</td>
        <td class="rt-bar">
            <div class="rt-bg">
                <span class="rt-fill" style="width:{{ ($val / 5) * 100 }}%; background-color:{{ $clr }};"></span>
            </div>
        </td>
        <td class="rt-score">{!! \App\Exports\Writers\PdfFactory::ltr($val . '/5') !!}</td>
    </tr>
</table>
@endforeach
</div>

{{-- ════ RECOMMENDATION ════ --}}
<span class="sec-head">نسبة التوصية بالدورة</span>
<div class="rec-section">
    <table class="rec-table">
        <tr>
            <td style="width:145px;">
                <span class="badge-yes">يوصون: {!! \App\Exports\Writers\PdfFactory::ltr($stats['recommend_percentage'] . '%') !!}</span>
            </td>
            <td>
                <div class="rec-bar-bg">
                    <span class="rec-bar-fill" style="width:{!! \App\Exports\Writers\PdfFactory::ltr($stats['recommend_percentage'] . '%') !!};"></span>
                </div>
            </td>
            <td style="width:145px; text-align:left; padding-right:8px;">
                <span class="badge-no">لا يوصون: {!! \App\Exports\Writers\PdfFactory::ltr((100 - $stats['recommend_percentage']) . '%') !!}</span>
            </td>
        </tr>
    </table>
</div>

{{-- ════ DIFFICULTY ════ --}}
@if($stats['difficulty_data']->isNotEmpty())
<span class="sec-head">توزيع مستوى الصعوبة</span>
<table class="diff-table">
    <tr>
        <th>مستوى الصعوبة</th>
        <th style="width:135px;">عدد المشاركين</th>
        <th style="width:95px;">النسبة المئوية</th>
    </tr>
    @foreach($stats['difficulty_data'] as $level => $cnt)
    <tr>
        <td>{{ $level }}</td>
        <td>{{ $cnt }}</td>
        <td>{!! \App\Exports\Writers\PdfFactory::ltr(round($cnt / $stats['total_reviews'] * 100) . '%') !!}</td>
    </tr>
    @endforeach
</table>
@endif

{{-- ════ STUDENT COMMENTS ════ --}}
@if($reviews->count() > 0)
<div class="page-break"></div>
<span class="sec-head">تعليقات المشاركين — {{ $reviews->count() }} تقييم</span>
<table class="reviews-table">
    <tr>
        <th style="width:14%;">الطالب</th>
        <th style="width:12%;">التقييم</th>
        <th style="width:8%;">يوصي</th>
        <th style="width:9%;">الصعوبة</th>
        <th style="width:28.5%;">ما أعجبه في الدورة</th>
        <th style="width:28.5%;">مقترحات التحسين</th>
    </tr>
    @foreach($reviews as $review)
    @php
        $f = (int) $review->overall_rating;
        $rStars = str_repeat('★', $f) . str_repeat('☆', 5 - $f);
    @endphp
    <tr>
        <td>
            @if($showPii ?? true)
            <strong>{{ $review->student_name }}</strong>
            @if($review->email)
            <br><span style="font-size:8px;color:#94a3b8;">{!! \App\Exports\Writers\PdfFactory::ltr($review->email) !!}</span>
            @endif
            @else
            <strong>مشارك #{{ $review->id }}</strong>
            @endif
            <br><span style="font-size:8px;color:#94a3b8;">{!! \App\Exports\Writers\PdfFactory::ltr($review->created_at->format('d/m/Y')) !!}</span>
        </td>
        <td style="text-align:center;">
            <span class="rv-stars">{{ $rStars }}</span><br>
            <span style="font-size:9.5px;font-weight:bold;color:#22334A;">{!! \App\Exports\Writers\PdfFactory::ltr($review->overall_rating . '/5') !!}</span>
        </td>
        <td style="text-align:center;">
            @if($review->would_recommend)
            <span class="rv-badge-y">نعم</span>
            @else
            <span class="rv-badge-n">لا</span>
            @endif
        </td>
        <td>{{ $review->difficulty_level ?: '—' }}</td>
        <td>{{ \Illuminate\Support\Str::limit($review->what_did_you_like ?? '—', 110) }}</td>
        <td>{{ \Illuminate\Support\Str::limit($review->what_can_be_improved ?? '—', 110) }}</td>
    </tr>
    @endforeach
</table>
@endif

@else
<div style="text-align:center; padding:30px; color:#94a3b8; font-size:11px;">
    لا توجد تقييمات مسجلة لهذه الحملة حتى الآن.
</div>
@endif

</div>

{{-- ════ FOOTER ════ --}}
<div class="page-footer">
    <table class="footer-table">
        <tr>
            <td>تقرير صادر بواسطة نظام تقييمات EMC &nbsp;|&nbsp; {!! \App\Exports\Writers\PdfFactory::ltr(now()->format('d/m/Y H:i')) !!}</td>
            <td class="footer-brand">EMC</td>
        </tr>
    </table>
</div>

</body>
</html>
