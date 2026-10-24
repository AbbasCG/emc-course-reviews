@extends('layouts.admin')

@section('title', $campaign->course_name)

@section('breadcrumbs')
<svg class="w-4 h-4 text-slate-600 mx-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
<a href="{{ route('admin.dashboard') }}" class="text-slate-400 text-sm hover:text-white transition-colors shrink-0">الحملات</a>
<svg class="w-4 h-4 text-slate-600 mx-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
<span class="text-slate-300 text-sm truncate max-w-28 sm:max-w-48">{{ $campaign->course_name }}</span>
@endsection

@section('nav-actions')
<div x-data="{ syncing: false }">
    <button type="button"
            @click="syncing = true; const u = new URL(window.location.href); u.searchParams.set('synced', '1'); window.location.href = u.toString();"
            :disabled="syncing"
            class="nav-btn"
            :class="syncing ? 'opacity-55' : ''">
        <svg x-show="syncing" style="display:none;" class="w-3.5 h-3.5 animate-spin shrink-0" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
        </svg>
        <svg x-show="!syncing" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
        </svg>
        <span class="hidden sm:inline" x-text="syncing ? 'جارٍ...' : 'مزامنة'">مزامنة</span>
    </button>
</div>
<a href="{{ route('admin.campaigns.edit', $campaign) }}" class="nav-btn">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
    </svg>
    <span class="hidden sm:inline">تعديل</span>
</a>
<form method="POST" action="{{ route('admin.campaigns.toggle', $campaign) }}">
    @csrf @method('PATCH')
    <button type="submit" class="nav-btn"
            style="{{ $campaign->is_active
                ? 'color:#fbbf24;border-color:rgba(251,191,36,0.2);background:rgba(251,191,36,0.08);'
                : 'color:#34d399;border-color:rgba(52,211,153,0.2);background:rgba(52,211,153,0.08);' }}">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            @if($campaign->is_active)
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            @else
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            @endif
        </svg>
        <span class="hidden sm:inline">{{ $campaign->is_active ? 'إيقاف' : 'تفعيل' }}</span>
    </button>
</form>
<a href="{{ route('admin.campaigns.exportPdf', $campaign) }}"
   class="nav-btn"
   style="background:linear-gradient(135deg,#22334A,#1a2840);color:#fff;border-color:rgba(34,51,74,0.5);">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
    </svg>
    <span class="hidden sm:inline">PDF</span>
</a>
<a href="{{ route('admin.campaigns.export-csv', $campaign) }}"
   class="nav-btn"
   style="background:linear-gradient(135deg,#EC943C,#d4782a);color:#fff;border-color:rgba(236,148,60,0.4);">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
    <span class="hidden sm:inline">CSV</span>
</a>
@can('export-reviews')
<a href="{{ route('admin.exports.index', ['campaign' => $campaign->id]) }}" class="nav-btn" title="تصدير متقدم">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
    </svg>
    <span class="hidden sm:inline">تصدير متقدم</span>
</a>
@endcan
@endsection

@push('head')
<style>
    /* ── Hero ── */
    .c-hero { background: linear-gradient(135deg, #060f1e 0%, #0d1e35 60%, #060f1e 100%); position: relative; overflow: hidden; }
    html:not(.dark) .c-hero { background: linear-gradient(135deg, #f0f9ff 0%, #dff0fb 60%, #f5fbff 100%); }
    .c-hero-grid {
        position: absolute; inset: 0; pointer-events: none;
        background-image:
            linear-gradient(rgba(38,145,194,0.05) 1px, transparent 1px),
            linear-gradient(90deg, rgba(38,145,194,0.05) 1px, transparent 1px);
        background-size: 40px 40px;
    }
    html:not(.dark) .c-hero-grid {
        background-image:
            linear-gradient(rgba(38,145,194,0.09) 1px, transparent 1px),
            linear-gradient(90deg, rgba(38,145,194,0.09) 1px, transparent 1px);
    }
    html:not(.dark) .c-hero .hero-text-primary { color: #0f172a !important; }
    html:not(.dark) .c-hero .hero-text-muted   { color: #475569 !important; }
    html:not(.dark) .c-hero .hero-link         { color: rgba(15,23,42,0.5) !important; }
    html:not(.dark) .c-hero .hero-link:hover   { color: #0f172a !important; }

    /* ── Stat cards ── */
    .s-card {
        background: var(--pg-card);
        border: 1px solid var(--pg-border);
        border-radius: 1.5rem;
        padding: 1.25rem 1.5rem;
        transition: border-color 0.2s, background 0.2s;
    }
    .s-card:hover { border-color: var(--pg-border-2); }
    .s-label { font-size: 0.65rem; font-weight: 800; color: var(--pg-text-4); text-transform: uppercase; letter-spacing: 0.1em; }
    .s-num { font-size: 2.25rem; font-weight: 900; color: var(--pg-text); line-height: 1; }
    .s-bar-track { height: 4px; border-radius: 999px; background: var(--pg-border); overflow: hidden; margin-top: 0.625rem; }
    .s-bar-fill { height: 100%; border-radius: 999px; width: 0%; transition: width 1s cubic-bezier(0.16,1,0.3,1); }

    /* ── Info / generic cards ── */
    .i-card {
        background: var(--pg-card);
        border: 1px solid var(--pg-border);
        border-radius: 1.5rem;
        transition: background 0.2s, border-color 0.2s;
    }
    .i-field-label { font-size: 0.65rem; font-weight: 800; color: var(--pg-text-4); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.375rem; }
    .i-field-val { font-weight: 600; font-size: 0.875rem; color: var(--pg-text); }
    .i-divider { border-top: 1px solid var(--pg-border); }

    /* ── Filter panel ── */
    .f-card { background: var(--pg-card); border: 1px solid var(--pg-border); border-radius: 1.5rem; overflow: hidden; }
    .f-input {
        width: 100%;
        background: var(--pg-input-bg);
        border: 1px solid var(--pg-input-border);
        color: var(--pg-text);
        border-radius: 0.75rem;
        padding: 0.55rem 0.875rem;
        font-size: 0.8125rem;
        outline: none;
        transition: border-color 0.2s;
        font-family: 'Cairo', sans-serif;
    }
    .f-input:focus { border-color: #2691C2; }
    .f-input::placeholder { color: var(--pg-text-4); }
    .f-input option { background: var(--pg-card); color: var(--pg-text); }
    .f-label { font-size: 0.7rem; font-weight: 700; color: var(--pg-text-3); margin-bottom: 0.375rem; display: block; }

    /* ── Review cards ── */
    .rv-card {
        background: var(--pg-card);
        border: 1px solid var(--pg-border);
        border-radius: 1rem;
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
    }
    html.dark .rv-card:hover {
        border-color: var(--pg-border-2);
        box-shadow: 0 6px 24px rgba(0,0,0,0.25);
        transform: translateY(-1px);
    }
    html:not(.dark) .rv-card:hover {
        border-color: rgba(38,145,194,0.28);
        box-shadow: 0 4px 20px rgba(38,145,194,0.08), 0 1px 6px rgba(0,0,0,0.04);
        transform: translateY(-1px);
    }
    .rv-divider { border-top: 1px solid var(--pg-border); }

    /* Rating rows section */
    .rv-strip-bg { background: var(--pg-bg); }
    html:not(.dark) .rv-strip-bg { background: #f9fafb; }

    /* Rating row — label | stars | score */
    .rv-row {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 0;
        border-bottom: 1px solid var(--pg-border);
    }
    .rv-row:last-child { border-bottom: none; padding-bottom: 0; }
    .rv-row-label {
        width: 6rem;
        flex-shrink: 0;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--pg-text-3);
    }
    .rv-row-stars { display: flex; gap: 2px; flex-shrink: 0; }
    .rv-row-score {
        margin-right: auto;
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--pg-text-3);
    }

    /* Feedback box */
    .fb-box {
        border-radius: 0.625rem;
        padding: 0.625rem 0.875rem;
        background: var(--pg-border);
        border: 1px solid var(--pg-border-2);
    }

    /* Card header bg for light mode */
    html:not(.dark) .rv-header-bg { background: #fafbfc; border-bottom: 1px solid #f1f5f9; }

    /* Staggered entry animations */
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .su-1 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 0.04s both; }
    .su-2 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 0.10s both; }
    .su-3 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 0.16s both; }
    .su-4 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 0.22s both; }
    .su-5 { animation: slideUp 0.45s cubic-bezier(0.16,1,0.3,1) 0.28s both; }
</style>
@endpush

{{-- ═══════════════════ HERO ═══════════════════ --}}
@section('hero')
@php $badge = $campaign->statusBadge(); $isActive = $campaign->is_active && !$campaign->isExpired(); @endphp
<div class="c-hero">
    <div class="c-hero-grid"></div>
    <div style="position:absolute;top:-80px;right:-40px;width:400px;height:400px;background:radial-gradient(circle,rgba(38,145,194,0.1) 0%,transparent 65%);pointer-events:none;"></div>
    <div style="position:absolute;bottom:-100px;left:5%;width:300px;height:300px;background:radial-gradient(circle,rgba(236,148,60,0.06) 0%,transparent 70%);pointer-events:none;"></div>

    <div class="max-w-7xl mx-auto px-4 mt-10 mb-10 sm:px-6 pt-10 sm:pt-14 pb-8 sm:pb-10 relative">
        <a href="{{ route('admin.dashboard') }}"
           class="hero-link inline-flex items-center gap-1.5 text-white/60 hover:text-white text-sm font-semibold mb-6 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            رجوع
        </a>
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0 flex items-start gap-4">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 hero-text-primary font-black text-2xl text-white"
                     style="background: linear-gradient(135deg, #2691C2, #22334A);">
                    {{ mb_substr($campaign->course_name, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <h1 class="hero-text-primary text-2xl sm:text-3xl font-extrabold text-white mb-1 leading-tight">{{ $campaign->course_name }}</h1>
                    <div class="flex items-center flex-wrap gap-x-4 gap-y-1 mt-1.5">
                        @if($campaign->instructor_name)
                        <p class="hero-text-muted text-slate-400 text-sm flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            {{ $campaign->instructor_name }}
                        </p>
                        @endif
                        @if($campaign->expires_at)
                        <p class="hero-text-muted text-slate-400 text-xs flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $campaign->isExpired() ? 'انتهت: ' : 'تنتهي: ' }}{{ $campaign->expires_at->format('d/m/Y') }}
                        </p>
                        @endif
                    </div>
                </div>
            </div>
            <span class="shrink-0 px-4 py-2 rounded-full text-xs font-extrabold tracking-wide flex items-center gap-1.5"
                  style="{{ $isActive
                    ? 'background:rgba(52,211,153,0.15);color:#34d399;border:1px solid rgba(52,211,153,0.3);'
                    : 'background:rgba(239,68,68,0.1);color:#f87171;border:1px solid rgba(239,68,68,0.25);' }}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $isActive ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                {{ $badge['label'] }}
            </span>
        </div>
    </div>
</div>
@endsection

{{-- ═══════════════════ CONTENT ═══════════════════ --}}
@section('content')
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">

    {{-- ── KPI Stats ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-5 su-1">

        {{-- Total reviews --}}
        <div class="s-card col-span-2 sm:col-span-1">
            <p class="s-label mb-2">إجمالي التقييمات</p>
            <p class="s-num">{{ $reviewCount }}</p>
            <div class="s-bar-track">
                <div class="s-bar-fill" style="width:100%;background:linear-gradient(90deg,#2691C2,#EC943C);transition:none;"></div>
            </div>
        </div>

        @foreach([
            ['label' => 'التقييم الكلي',  'value' => $averageRating,             'color' => '#2691C2'],
            ['label' => 'المحتوى',         'value' => $averageContentRating,      'color' => '#EC943C'],
            ['label' => 'المدرب',          'value' => $averageInstructorRating,   'color' => '#2691C2'],
            ['label' => 'التنظيم',         'value' => $averageOrganizationRating, 'color' => '#64748b'],
        ] as $stat)
        <div class="s-card"
             x-data="{ ready: false }"
             x-init="setTimeout(() => ready = true, 200)">
            <p class="s-label mb-2">{{ $stat['label'] }}</p>
            <div class="flex items-baseline gap-1">
                <p class="s-num">{{ $stat['value'] }}</p>
                <span style="color:#EC943C;" class="text-lg font-bold leading-none">★</span>
            </div>
            <div class="s-bar-track">
                <div class="s-bar-fill"
                     :style="'width:' + (ready ? '{{ $stat['value'] / 5 * 100 }}' : '0') + '%;background:{{ $stat['color'] }};'"></div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Recommend bar ── --}}
    @if($reviewCount > 0)
    <div class="s-card mb-5 su-2">
        <div class="flex items-center gap-4 sm:gap-6 flex-wrap">
            <div class="shrink-0">
                <p class="s-label mb-1">نسبة التوصية</p>
                <p class="text-2xl font-black" style="color:#10b981;">{{ $recommendPct }}%</p>
            </div>
            <div class="flex-1 min-w-32">
                <div class="h-3 rounded-full overflow-hidden" style="background:var(--pg-border);">
                    <div class="h-full rounded-full" style="background:linear-gradient(90deg,#10b981,#34d399);width:{{ $recommendPct }}%;transition:width 0.8s cubic-bezier(0.16,1,0.3,1);"></div>
                </div>
                <div class="flex justify-between mt-1.5">
                    <span class="text-xs font-bold" style="color:#10b981;">يوصي</span>
                    <span class="text-xs font-bold" style="color:#f87171;">لا يوصي</span>
                </div>
            </div>
            <div class="shrink-0">
                <p class="s-label mb-1">لا يوصي</p>
                <p class="text-2xl font-black" style="color:#f87171;">{{ 100 - $recommendPct }}%</p>
            </div>
        </div>
    </div>
    @endif

    {{-- ── Campaign Info + Link ── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5 su-3">

        {{-- Campaign Info --}}
        <div class="lg:col-span-2 i-card p-6 sm:p-7">
            <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                <h2 class="text-sm font-extrabold flex items-center gap-2.5" style="color:var(--pg-text);">
                    <span class="w-0.5 h-4 rounded-full shrink-0" style="background:linear-gradient(to bottom,#2691C2,#22334A);"></span>
                    معلومات الحملة
                </h2>
                <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}"
                      x-data
                      @submit.prevent="if(confirm('هل أنت متأكد من حذف هذه الحملة وجميع تقييماتها؟ لا يمكن التراجع.')) $el.submit()">
                    @csrf @method('DELETE')
                    <button type="submit"
                        class="flex items-center gap-1.5 text-xs font-bold px-4 py-2 rounded-xl transition-all whitespace-nowrap"
                        style="background:rgba(239,68,68,0.08);color:#f87171;border:1px solid rgba(239,68,68,0.15);">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        حذف الحملة
                    </button>
                </form>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                @if($campaign->description)
                <div class="col-span-2">
                    <p class="i-field-label">الوصف</p>
                    <p class="text-sm leading-relaxed" style="color:var(--pg-text-2);">{{ $campaign->description }}</p>
                </div>
                @endif
                @if($campaign->course_date)
                <div>
                    <p class="i-field-label">تاريخ الدورة</p>
                    <p class="i-field-val">{{ $campaign->course_date->format('d / m / Y') }}</p>
                </div>
                @endif
                <div>
                    <p class="i-field-label">تاريخ الإنشاء</p>
                    <p class="i-field-val">{{ $campaign->created_at->format('d / m / Y') }}</p>
                </div>
                @if($campaign->expires_at)
                <div>
                    <p class="i-field-label">انتهاء الصلاحية</p>
                    <p class="i-field-val {{ $campaign->isExpired() ? '!text-red-500' : '' }}">
                        {{ $campaign->expires_at->format('d / m / Y') }}
                    </p>
                </div>
                @endif
            </div>
        </div>

        {{-- Review Link — compact card --}}
        <div x-data="{ copied: false }">
            <div class="i-card p-5 h-full flex flex-col gap-4">

                {{-- Title --}}
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                         style="background:rgba(236,148,60,0.12);border:1px solid rgba(236,148,60,0.22);">
                        <svg class="w-3.5 h-3.5" style="color:#EC943C;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </div>
                    <h3 class="text-sm font-extrabold" style="color:var(--pg-text);">رابط التقييم</h3>
                </div>

                {{-- URL row with inline copy --}}
                <div class="flex items-center gap-2 px-3 py-2.5 rounded-xl"
                     style="background:var(--pg-input-bg);border:1px solid var(--pg-input-border);">
                    <svg class="w-3.5 h-3.5 shrink-0" style="color:var(--pg-text-4);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <p class="flex-1 text-xs truncate font-mono" style="color:var(--pg-text-3);"
                       title="{{ route('review.show', $campaign->token) }}">
                        {{ route('review.show', $campaign->token) }}
                    </p>
                    <button
                        @click="navigator.clipboard.writeText('{{ route('review.show', $campaign->token) }}').then(() => { copied = true; setTimeout(() => copied = false, 2500) })"
                        class="shrink-0 flex items-center gap-1 text-[11px] font-bold px-2 py-1 rounded-lg transition-all whitespace-nowrap"
                        :style="copied
                            ? 'color:#10b981;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.2);'
                            : 'color:#2691C2;background:rgba(38,145,194,0.08);border:1px solid rgba(38,145,194,0.18);'">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path x-show="!copied" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            <path x-show="copied" style="display:none;" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span x-text="copied ? 'تم!' : 'نسخ'">نسخ</span>
                    </button>
                </div>

                {{-- Main copy button --}}
                <button
                    @click="navigator.clipboard.writeText('{{ route('review.show', $campaign->token) }}').then(() => { copied = true; setTimeout(() => copied = false, 2500) })"
                    class="mt-auto w-full flex items-center justify-center gap-2 font-bold text-sm py-2.5 rounded-xl transition-all"
                    :class="copied ? '' : 'text-white hover:opacity-90'"
                    :style="copied
                        ? 'background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);'
                        : 'background:linear-gradient(135deg,#EC943C,#d4782a);'">
                    <svg class="w-4 h-4" x-show="!copied" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <svg class="w-4 h-4" x-show="copied" style="display:none;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <span x-text="copied ? 'تم نسخ الرابط بنجاح ✓' : 'نسخ الرابط'">نسخ الرابط</span>
                </button>

            </div>
        </div>
    </div>

    {{-- ── Reviews Section ── --}}
    <div class="su-4">

        {{-- Section header --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <div class="flex items-center gap-2.5">
                <span class="w-0.5 h-5 rounded-full shrink-0" style="background:linear-gradient(to bottom,#2691C2,#EC943C);"></span>
                <h2 class="text-sm font-extrabold" style="color:var(--pg-text);">
                    التقييمات
                    @if($reviewCount > 0)
                    <span class="mr-2 text-[11px] font-bold px-2.5 py-1 rounded-full"
                          style="background:rgba(38,145,194,0.1);color:#7dd3fc;border:1px solid rgba(38,145,194,0.2);">{{ $reviewCount }}</span>
                    @endif
                </h2>
            </div>
            @if($reviewCount > 0)
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('admin.campaigns.exportPdf', $campaign) }}"
                   class="flex items-center gap-1.5 text-xs font-bold px-4 py-2 rounded-xl text-white hover:opacity-90 transition-all whitespace-nowrap"
                   style="background:linear-gradient(135deg,#22334A,#1a2840);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    تصدير PDF
                </a>
                <a href="{{ route('admin.campaigns.export-csv', $campaign) }}"
                   class="flex items-center gap-1.5 text-xs font-bold px-4 py-2 rounded-xl text-white hover:opacity-90 transition-all whitespace-nowrap"
                   style="background:linear-gradient(135deg,#EC943C,#d4782a);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    تصدير CSV
                </a>
            </div>
            @endif
        </div>

        {{-- Collapsible filter panel --}}
        @if($reviewCount > 0)
        @php $activeFilterCount = count(array_filter($reviewFilters)); @endphp
        <div x-data="{ open: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }"
             class="f-card mb-5">
            <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between gap-3 px-5 sm:px-6 py-4 text-right transition-colors">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                         style="background:rgba(38,145,194,0.12);border:1px solid rgba(38,145,194,0.22);">
                        <svg class="w-3.5 h-3.5" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-bold" style="color:var(--pg-text-2);">تصفية التقييمات</span>
                    @if($activeFilterCount > 0)
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full"
                          style="background:rgba(38,145,194,0.12);color:#7dd3fc;border:1px solid rgba(38,145,194,0.22);">{{ $activeFilterCount }} مُفعّل</span>
                    @endif
                </div>
                <svg class="w-4 h-4 transition-transform duration-200 shrink-0" style="color:var(--pg-text-4);"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <form method="GET" action="{{ route('admin.campaigns.show', $campaign) }}"
                  x-show="open"
                  x-transition:enter="transition ease-out duration-200"
                  x-transition:enter-start="opacity-0 -translate-y-2"
                  x-transition:enter-end="opacity-100 translate-y-0"
                  x-transition:leave="transition ease-in duration-150"
                  x-transition:leave-start="opacity-100 translate-y-0"
                  x-transition:leave-end="opacity-0 -translate-y-2"
                  class="px-5 sm:px-6 pb-5 pt-4"
                  style="display:none;border-top:1px solid var(--pg-border);">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="f-label">اسم الطالب</label>
                        <input type="text" name="student_name" value="{{ $reviewFilters['student_name'] ?? '' }}"
                               placeholder="ابحث بالاسم..." class="f-input">
                    </div>
                    <div>
                        <label class="f-label">البريد الإلكتروني</label>
                        <input type="text" name="email" value="{{ $reviewFilters['email'] ?? '' }}"
                               placeholder="ابحث بالبريد..." class="f-input">
                    </div>
                    <div>
                        <label class="f-label">التقييم</label>
                        <select name="rating" class="f-input">
                            <option value="">جميع التقييمات</option>
                            @for($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}" {{ ($reviewFilters['rating'] ?? '') == $i ? 'selected' : '' }}>{{ $i }} نجوم</option>
                            @endfor
                        </select>
                    </div>
                    <div>
                        <label class="f-label">التوصية</label>
                        <select name="recommend" class="f-input">
                            <option value="">الكل</option>
                            <option value="yes" {{ ($reviewFilters['recommend'] ?? '') === 'yes' ? 'selected' : '' }}>يوصي</option>
                            <option value="no"  {{ ($reviewFilters['recommend'] ?? '') === 'no'  ? 'selected' : '' }}>لا يوصي</option>
                        </select>
                    </div>
                    <div>
                        <label class="f-label">مستوى الصعوبة</label>
                        <select name="difficulty" class="f-input">
                            <option value="">جميع المستويات</option>
                            <option value="سهل"   {{ ($reviewFilters['difficulty'] ?? '') === 'سهل'   ? 'selected' : '' }}>سهل</option>
                            <option value="متوسط" {{ ($reviewFilters['difficulty'] ?? '') === 'متوسط' ? 'selected' : '' }}>متوسط</option>
                            <option value="صعب"   {{ ($reviewFilters['difficulty'] ?? '') === 'صعب'   ? 'selected' : '' }}>صعب</option>
                        </select>
                    </div>
                    <div>
                        <label class="f-label">من تاريخ</label>
                        <input type="date" name="date_from" value="{{ $reviewFilters['date_from'] ?? '' }}" class="f-input">
                    </div>
                    <div>
                        <label class="f-label">إلى تاريخ</label>
                        <input type="date" name="date_to" value="{{ $reviewFilters['date_to'] ?? '' }}" class="f-input">
                    </div>
                </div>
                <div class="flex gap-2 mb-4 mt-4 pt-4" style="border-top:1px solid var(--pg-border);">
                    <button type="submit"
                        class="flex items-center gap-1.5 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:opacity-90 transition-all"
                        style="background:linear-gradient(135deg,#2691C2,#1a4f6e);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        بحث
                    </button>
                    <a href="{{ route('admin.campaigns.show', $campaign) }}"
                       class="flex items-center gap-1.5 text-sm font-bold px-5 py-2.5 rounded-xl transition-all"
                       style="border:1px solid var(--pg-border);color:var(--pg-text-3);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        إعادة تعيين
                    </a>
                </div>
            </form>
        </div>
        @endif

        {{-- Empty states --}}
        @if($reviews->isEmpty())
            @if(array_filter($reviewFilters))
            <div class="i-card p-14 text-center">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                     style="background:rgba(38,145,194,0.08);border:1px solid rgba(38,145,194,0.18);">
                    <svg class="w-7 h-7" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <p class="font-bold mb-1" style="color:var(--pg-text);">لا توجد نتائج مطابقة</p>
                <p class="text-sm mb-5" style="color:var(--pg-text-3);">جرب تعديل معايير البحث</p>
                <a href="{{ route('admin.campaigns.show', $campaign) }}"
                   class="inline-flex items-center gap-2 text-white text-sm font-bold px-6 py-3 rounded-xl hover:opacity-90"
                   style="background:linear-gradient(135deg,#2691C2,#22334A);">
                    إعادة تعيين الفلاتر
                </a>
            </div>
            @else
            <div class="i-card p-14 text-center">
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                     style="background:rgba(236,148,60,0.08);border:1px solid rgba(236,148,60,0.18);">
                    <svg class="w-7 h-7" style="color:#EC943C;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
                <p class="font-bold mb-1" style="color:var(--pg-text);">لا توجد تقييمات بعد</p>
                <p class="text-sm" style="color:var(--pg-text-3);">شارك رابط الحملة مع الطلاب لبدء استقبال التقييمات</p>
            </div>
            @endif

        @else
        {{-- ── Review Cards ── --}}
        <div class="space-y-3 su-5">
            @foreach($reviews as $review)
            @php
                $topAccent = $review->overall_rating >= 4 ? '#2691C2'
                           : ($review->overall_rating >= 3 ? '#EC943C' : '#ef4444');
            @endphp
            <div class="rv-card" id="review-{{ $review->id }}"
                 style="border-top: 3px solid {{ $topAccent }};"
                 x-data="{ highlighted: false }"
                 x-init="
                     setTimeout(() => {
                         if (window.location.hash === '#review-{{ $review->id }}') {
                             $el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                             highlighted = true;
                             setTimeout(() => highlighted = false, 3000);
                         }
                     }, 120);
                 "
                 :style="highlighted
                     ? 'border-top:3px solid {{ $topAccent }};outline:2px solid #EC943C;outline-offset:3px;box-shadow:0 0 0 5px rgba(236,148,60,0.12);'
                     : 'border-top:3px solid {{ $topAccent }};outline:2px solid transparent;outline-offset:3px;transition:outline 0.4s,box-shadow 0.4s;'">

                {{-- ── Card Header ── --}}
                <div class="px-5 sm:px-6 py-4 flex items-start justify-between flex-wrap gap-3 rv-header-bg">
                    {{-- Student info --}}
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 font-extrabold text-white text-sm"
                             style="background:linear-gradient(135deg,#2691C2,#22334A);">
                            {{ mb_strtoupper(mb_substr($review->student_name, 0, 1)) }}
                        </div>
                        <div>
                            <p class="font-bold text-sm leading-tight" style="color:var(--pg-text);">{{ $review->student_name }}</p>
                            <p class="text-xs mt-0.5 flex items-center flex-wrap gap-x-1.5" style="color:var(--pg-text-4);">
                                @if($review->email)<span>{{ $review->email }}</span><span style="color:var(--pg-border-2);">·</span>@endif
                                <span>{{ $review->created_at->format('d/m/Y') }}</span>
                                <span style="color:var(--pg-border-2);">·</span>
                                <span>{{ $review->created_at->diffForHumans() }}</span>
                            </p>
                        </div>
                    </div>
                    {{-- Badges row --}}
                    <div class="flex items-center gap-1.5 flex-wrap">
                        {{-- Overall rating --}}
                        <span class="flex items-center gap-1 text-xs font-extrabold px-2.5 py-1 rounded-full"
                              style="background:rgba(236,148,60,0.1);color:#EC943C;border:1px solid rgba(236,148,60,0.22);">
                            <span>★</span>{{ $review->overall_rating }}/5
                        </span>
                        {{-- Recommend --}}
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                              style="{{ $review->would_recommend
                                ? 'background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.2);'
                                : 'background:rgba(239,68,68,0.08);color:#f87171;border:1px solid rgba(239,68,68,0.18);' }}">
                            {{ $review->would_recommend ? 'يوصي' : 'لا يوصي' }}
                        </span>
                        {{-- Difficulty --}}
                        <span class="text-xs font-bold px-2.5 py-1 rounded-full"
                              style="background:var(--pg-border);color:var(--pg-text-3);border:1px solid var(--pg-border-2);">
                            {{ $review->difficulty_level }}
                        </span>
                    </div>
                </div>

                {{-- ── Rating Rows ── --}}
                <div class="px-5 sm:px-6 py-3 rv-divider rv-strip-bg">
                    @foreach([
                        ['التقييم الكلي', $review->overall_rating],
                        ['المحتوى',        $review->course_content_rating],
                        ['المدرب',         $review->instructor_rating],
                        ['التنظيم',        $review->organization_rating],
                    ] as [$lbl, $val])
                    <div class="rv-row">
                        <span class="rv-row-label">{{ $lbl }}</span>
                        <div class="rv-row-stars">
                            @for($i = 1; $i <= 5; $i++)
                            <span class="text-xs leading-none"
                                  style="color:{{ $i <= $val ? '#EC943C' : '#CBD5E1' }};">★</span>
                            @endfor
                        </div>
                        <span class="rv-row-score">{{ $val }}/5</span>
                    </div>
                    @endforeach
                </div>

                {{-- ── Feedback Boxes ── --}}
                <div class="px-5 sm:px-6 pb-5 pt-3 rv-divider">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-2.5">
                        {{-- ما أعجبه --}}
                        <div>
                            <p class="text-[11px] font-extrabold mb-1.5 flex items-center gap-1.5" style="color:var(--pg-text-3);">
                                <svg class="w-3 h-3 shrink-0" style="color:#10b981;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                ما أعجبه
                            </p>
                            <div class="fb-box">
                                <p class="text-xs leading-relaxed {{ !$review->what_did_you_like ? 'italic' : '' }}"
                                   style="color:{{ $review->what_did_you_like ? 'var(--pg-text-2)' : 'var(--pg-text-4)' }};">
                                    {{ $review->what_did_you_like ?: 'لا توجد ملاحظات' }}
                                </p>
                            </div>
                        </div>
                        {{-- اقتراحات التحسين --}}
                        <div>
                            <p class="text-[11px] font-extrabold mb-1.5 flex items-center gap-1.5" style="color:var(--pg-text-3);">
                                <svg class="w-3 h-3 shrink-0" style="color:#EC943C;" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                اقتراحات التحسين
                            </p>
                            <div class="fb-box">
                                <p class="text-xs leading-relaxed {{ !$review->what_can_be_improved ? 'italic' : '' }}"
                                   style="color:{{ $review->what_can_be_improved ? 'var(--pg-text-2)' : 'var(--pg-text-4)' }};">
                                    {{ $review->what_can_be_improved ?: 'لا توجد ملاحظات' }}
                                </p>
                            </div>
                        </div>
                        {{-- ملاحظات إضافية --}}
                        <div>
                            <p class="text-[11px] font-extrabold mb-1.5 flex items-center gap-1.5" style="color:var(--pg-text-3);">
                                <svg class="w-3 h-3 shrink-0" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                </svg>
                                ملاحظات إضافية
                            </p>
                            <div class="fb-box">
                                <p class="text-xs leading-relaxed {{ !$review->additional_notes ? 'italic' : '' }}"
                                   style="color:{{ $review->additional_notes ? 'var(--pg-text-2)' : 'var(--pg-text-4)' }};">
                                    {{ $review->additional_notes ?: 'لا توجد ملاحظات' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            @endforeach
        </div>

        @if($reviews->hasPages())
        <div class="mt-10 flex justify-center">
            {{ $reviews->links('pagination::tailwind') }}
        </div>
        @endif
        @endif

    </div>
</main>
@endsection
