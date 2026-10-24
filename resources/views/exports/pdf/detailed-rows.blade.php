@php
    use App\Exports\ReviewExportColumns;
    use App\Exports\Writers\PdfFactory;
    $ltrTypes = ['date', 'datetime'];
@endphp
<div class="wrap" style="padding-top:0;padding-bottom:0;">
<table class="data" repeat_header="1">
    <thead>
        <tr>
            @foreach($labels as $label)
            <th>{{ $label }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $i => $row)
        <tr class="{{ $i % 2 ? 'alt' : '' }}">
            @foreach($row as $j => $value)
            @php $key = $columns[$j]; @endphp
            <td>
                @if($value === null || $value === '')
                <span class="muted">—</span>
                @elseif(in_array(ReviewExportColumns::type($key), $ltrTypes, true) || $key === 'email')
                {!! PdfFactory::ltr($value) !!}
                @elseif(str_ends_with($key, '_rating'))
                {!! PdfFactory::stars($value) !!} {{ $value }}
                @else
                {!! PdfFactory::text($value) !!}
                @endif
            </td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>
</div>
