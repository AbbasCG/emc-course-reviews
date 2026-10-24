@php
    use App\Exports\Writers\PdfFactory;
    $showName  = $c->hasColumn('student_name');
    $showEmail = $c->hasColumn('email');
    $textLabels = [
        'what_did_you_like'    => 'ما أعجبه',
        'what_can_be_improved' => 'اقتراحات التحسين',
        'additional_notes'     => 'ملاحظات إضافية',
    ];
@endphp
@include('exports.pdf._header', [
    'eyebrow'  => 'التقرير الرسمي لتقييمات EMC',
    'title'    => $c->name ?? $c->defaultName(),
    'subtitle' => 'Education Management Center — تقرير تحليلي لتقييمات الأنشطة التدريبية',
    'filters'  => $filters,
])
<div class="wrap">
    <h3 class="sec">الملخص التنفيذي</h3>
    <div class="exec">
        @foreach($executive as $line)
        <p>{!! $line !!}</p>
        @endforeach
    </div>

    @if($stats['total'] > 0)
    @include('exports.pdf._stats')
    @include('exports.pdf._campaigns')

    @if(count($textColumns) && $comments->count())
    <pagebreak />
    <h3 class="sec">آراء المشاركين النصية</h3>
    @foreach($comments as $review)
    @php $hasText = collect($textColumns)->contains(fn ($k) => filled($review->{$k})); @endphp
    @continue(!$hasText)
    <div class="comment">
        <div class="comment-head">
            @if($showName)<b>{{ $review->student_name }}</b> — @endif
            @if($showEmail && $review->email){!! PdfFactory::ltr($review->email) !!} — @endif
            {{ $review->campaign?->course_name }}
            — {!! PdfFactory::stars($review->overall_rating) !!}
            — {!! PdfFactory::ltr($review->created_at->format('Y-m-d')) !!}
        </div>
        @foreach($textColumns as $key)
        @if(filled($review->{$key}))
        <div class="comment-k">{{ $textLabels[$key] }}</div>
        <div class="comment-v">{!! PdfFactory::text($review->{$key}) !!}</div>
        @endif
        @endforeach
    </div>
    @endforeach
    @if($stats['total'] > $limit)
    <div class="note">تم عرض أحدث {{ $limit }} تقييم نصي من أصل {{ number_format($stats['total']) }}. للاطلاع على جميع التعليقات استخدم التصدير التفصيلي بصيغة Excel.</div>
    @endif
    @endif
    @endif
</div>
