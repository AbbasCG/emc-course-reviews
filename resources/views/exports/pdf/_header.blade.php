@php
    use App\Exports\Writers\PdfFactory;
    $logoPath = PdfFactory::logoPath();
@endphp
<div class="hdr">
    <table class="hdr-table">
        <tr>
            <td>
                <div class="hdr-eyebrow">{{ $eyebrow }}</div>
                <div class="hdr-title">{!! PdfFactory::text($title) !!}</div>
                @if(!empty($subtitle))<div class="hdr-sub">{!! PdfFactory::text($subtitle) !!}</div>@endif
            </td>
            <td class="hdr-brand">
                @if($logoPath)
                <img src="{{ $logoPath }}" style="height:44px;width:auto;">
                @else
                <div class="hdr-brand-name">EMC</div>
                @endif
                <div class="hdr-brand-sub">Education Management Center</div>
            </td>
        </tr>
    </table>
</div>
<div class="accent"></div>
@if(!empty($filters))
<div class="wrap" style="padding-bottom:0;">
<table class="meta">
    @foreach($filters as [$label, $value])
    <tr>
        <td class="k">{{ $label }}</td>
        <td>{!! PdfFactory::text($value) !!}</td>
    </tr>
    @endforeach
</table>
</div>
@endif
