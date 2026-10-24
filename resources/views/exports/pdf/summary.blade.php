@include('exports.pdf._header', [
    'eyebrow'  => 'تقرير ملخص',
    'title'    => $c->name ?? $c->defaultName(),
    'subtitle' => 'ملخص إحصائي لتقييمات المشاركين',
    'filters'  => $filters,
])
<div class="wrap">
    @if($stats['total'] === 0)
    <div class="empty">لا توجد تقييمات مطابقة للفلاتر المحددة.</div>
    @else
    @include('exports.pdf._stats')
    @include('exports.pdf._campaigns')
    @endif
</div>
