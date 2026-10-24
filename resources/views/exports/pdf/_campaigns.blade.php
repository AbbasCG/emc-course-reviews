@php use App\Exports\Writers\PdfFactory; @endphp
@if(count($campaigns))
<h3 class="sec">أداء الحملات</h3>
<table class="grid" repeat_header="1">
    <thead>
        <tr>
            <th>الدورة / النشاط</th>
            <th style="width:11%;">النوع</th>
            <th style="width:14%;">المدرب</th>
            <th style="width:8%;">العدد</th>
            <th style="width:9%;">العام</th>
            <th style="width:8%;">المحتوى</th>
            <th style="width:8%;">المدرب</th>
            <th style="width:8%;">التنظيم</th>
            <th style="width:9%;">التوصية</th>
        </tr>
    </thead>
    <tbody>
        @foreach($campaigns as $row)
        <tr class="{{ $loop->odd ? '' : 'alt' }}">
            <td><b>{!! PdfFactory::text($row['course_name']) !!}</b><br><span class="muted">{{ $row['status'] }}@if($row['course_date']) — {!! PdfFactory::ltr($row['course_date']) !!}@endif</span></td>
            <td>{{ $row['activity_type'] }}</td>
            <td>{{ $row['instructor_name'] }}</td>
            <td class="n">{{ number_format($row['total']) }}</td>
            <td class="n"><b>{{ $row['overall_avg'] }}</b></td>
            <td class="n">{{ $row['content_avg'] }}</td>
            <td class="n">{{ $row['instructor_avg'] }}</td>
            <td class="n">{{ $row['organization_avg'] }}</td>
            <td class="n">{!! PdfFactory::ltr($row['recommend_pct'] . '%') !!}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif
