@include('exports.pdf._header', [
    'eyebrow'  => 'تصدير تفصيلي للتقييمات',
    'title'    => $criteria->name ?? $criteria->defaultName(),
    'subtitle' => 'عدد السجلات: ' . number_format($total),
    'filters'  => $filters,
])
