@php use App\Exports\Writers\PdfFactory; @endphp
{{-- KPI boxes + averages + stars + recommendation + difficulty (shared by summary & professional) --}}
<h3 class="sec">المؤشرات الرئيسية</h3>
<table class="kpis">
    <tr>
        <td><div class="kpi-num">{{ number_format($stats['total']) }}</div><div class="kpi-cap">إجمالي التقييمات</div></td>
        <td><div class="kpi-num">{{ $stats['campaigns'] }}</div><div class="kpi-cap">عدد الحملات</div></td>
        <td class="o"><div class="kpi-num">{{ $stats['overall_avg'] }}</div><div class="kpi-cap">التقييم العام من 5</div></td>
        <td class="g"><div class="kpi-num">{!! PdfFactory::ltr($stats['recommend_pct'] . '%') !!}</div><div class="kpi-cap">نسبة التوصية</div></td>
    </tr>
</table>

@if($stats['total'] > 0)
<h3 class="sec">متوسطات التقييم</h3>
@foreach([
    ['التقييم العام', $stats['overall_avg'], '#EC943C'],
    ['محتوى الدورة', $stats['content_avg'], '#2691C2'],
    ['أداء المدرب', $stats['instructor_avg'], '#2691C2'],
    ['التنظيم والإدارة', $stats['organization_avg'], '#64748b'],
] as [$label, $value, $color])
<table class="bars">
    <tr>
        <td class="l">{{ $label }}</td>
        <td class="s">{!! PdfFactory::stars($value) !!}</td>
        <td>{!! PdfFactory::bar($value / 5 * 100, $color) !!}</td>
        <td class="v">{!! PdfFactory::ltr($value . '/5') !!}</td>
    </tr>
</table>
@endforeach

<h3 class="sec">توزيع النجوم</h3>
@foreach($stats['stars'] as $star => $n)
@php $pct = $stats['total'] ? round($n / $stats['total'] * 100) : 0; @endphp
<table class="bars">
    <tr>
        <td class="l">{!! PdfFactory::stars($star) !!}</td>
        <td class="s">{{ number_format($n) }} تقييم</td>
        <td>{!! PdfFactory::bar($pct, '#EC943C') !!}</td>
        <td class="v">{!! PdfFactory::ltr($pct . '%') !!}</td>
    </tr>
</table>
@endforeach

<h3 class="sec">التوصية ومستوى الصعوبة</h3>
<table width="100%" style="border-collapse:collapse;">
    <tr>
        <td style="width:48%;vertical-align:top;">
            <table class="grid">
                <tr><th>التوصية</th><th style="width:22%;">العدد</th><th style="width:22%;">النسبة</th></tr>
                <tr><td class="yes">يوصي</td><td class="n">{{ number_format($stats['recommend_yes']) }}</td><td class="n">{!! PdfFactory::ltr($stats['recommend_pct'] . '%') !!}</td></tr>
                <tr class="alt"><td class="no">لا يوصي</td><td class="n">{{ number_format($stats['recommend_no']) }}</td><td class="n">{!! PdfFactory::ltr((100 - $stats['recommend_pct']) . '%') !!}</td></tr>
            </table>
        </td>
        <td style="width:4%;"></td>
        <td style="width:48%;vertical-align:top;">
            <table class="grid">
                <tr><th>مستوى الصعوبة</th><th style="width:22%;">العدد</th><th style="width:22%;">النسبة</th></tr>
                @foreach($stats['difficulty'] as $level => $n)
                <tr class="{{ $loop->odd ? '' : 'alt' }}">
                    <td>{{ $level }}</td>
                    <td class="n">{{ number_format($n) }}</td>
                    <td class="n">{!! PdfFactory::ltr(round($n / $stats['total'] * 100) . '%') !!}</td>
                </tr>
                @endforeach
            </table>
        </td>
    </tr>
</table>
@endif
