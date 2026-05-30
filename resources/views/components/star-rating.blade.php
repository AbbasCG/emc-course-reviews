@props(['rating' => 0, 'max' => 5, 'size' => 'sm', 'showNumber' => true])
@php
    $r       = (float) $rating;
    $filled  = (int) floor($r);
    $hasHalf = ($r - $filled) >= 0.3;
    $empty   = $max - $filled - ($hasHalf ? 1 : 0);
    $sizeMap = ['xs' => 'text-[10px]', 'sm' => 'text-xs', 'md' => 'text-sm', 'lg' => 'text-base'];
    $sz      = $sizeMap[$size] ?? 'text-xs';
@endphp
<div class="flex items-center gap-1">
    <div class="flex gap-px leading-none">
        @for($i = 0; $i < $filled; $i++)
        <span class="{{ $sz }}" style="color:#EC943C;">★</span>
        @endfor
        @if($hasHalf)
        <span class="{{ $sz }}" style="color:#EC943C;opacity:0.45;">★</span>
        @endif
        @for($i = 0; $i < $empty; $i++)
        <span class="{{ $sz }}" style="color:var(--pg-text-4);">★</span>
        @endfor
    </div>
    @if($showNumber && $r > 0)
    <span class="text-[11px] font-bold leading-none" style="color:var(--pg-text-3);">
        {{ number_format($r, 1) }}
    </span>
    @endif
</div>
