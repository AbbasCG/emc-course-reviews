@extends('layouts.admin')

@section('title', 'لوحة التحكم')
@section('breadcrumbs')@endsection

{{-- Dark theme override injected into <head> --}}
@push('head')
<style>
    body { background: var(--pg-bg) !important; color: var(--pg-text); }

    /* Card system */
    .d-card {
        background: var(--pg-card);
        border: 1px solid var(--pg-border);
        border-radius: 1.25rem;
        transition: border-color 0.2s, background 0.2s;
    }
    .d-card:hover { border-color: var(--pg-border-2); }

    /* Input system */
    .d-input {
        background: var(--pg-input-bg);
        border: 1px solid var(--pg-input-border);
        color: var(--pg-text);
        border-radius: 0.625rem;
        padding: 0.55rem 0.875rem;
        font-size: 0.8125rem;
        width: 100%;
        outline: none;
        transition: border-color 0.2s, background 0.2s;
        font-family: 'Cairo', sans-serif;
    }
    .d-input:focus { border-color: #2691C2; background: rgba(38,145,194,0.06); }
    .d-input::placeholder { color: var(--pg-text-4); }
    option { background: var(--pg-card); color: var(--pg-text); }

    /* Labels */
    .d-label {
        font-size: 0.65rem;
        font-weight: 800;
        color: var(--pg-text-2);
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 0.35rem;
        display: block;
    }

    /* Section divider accent */
    .d-accent {
        width: 3px;
        border-radius: 999px;
        flex-shrink: 0;
    }

    /* Staggered page-load animations */
    @keyframes riseIn {
        from { opacity: 0; transform: translateY(20px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .rise { animation: riseIn 0.55s cubic-bezier(0.16, 1, 0.3, 1) both; }
    .rise-1 { animation-delay: 0.05s; }
    .rise-2 { animation-delay: 0.12s; }
    .rise-3 { animation-delay: 0.19s; }
    .rise-4 { animation-delay: 0.26s; }
    .rise-5 { animation-delay: 0.33s; }

    /* Active-campaign pulsing dot */
    @keyframes pulseGlow {
        0%,100% { box-shadow: 0 0 0 0 rgba(52,211,153,0.6); }
        50%      { box-shadow: 0 0 0 5px rgba(52,211,153,0); }
    }
    .dot-live {
        width: 8px; height: 8px; border-radius: 50%;
        background: #34d399;
        animation: pulseGlow 2s ease-in-out infinite;
        flex-shrink: 0;
    }
    .dot-off {
        width: 8px; height: 8px; border-radius: 50%;
        background: var(--pg-text-4); flex-shrink: 0;
    }
    .dot-exp {
        width: 8px; height: 8px; border-radius: 50%;
        background: #ef4444; opacity: 0.5; flex-shrink: 0;
    }

    /* Table row hover / dividers */
    .d-tr { transition: background 0.15s; cursor: pointer; border-bottom: 1px solid var(--pg-border) !important; }
    .d-tr:hover { background: rgba(38,145,194,0.05) !important; }

    /* Fix: .d-label has display:block which breaks <th> table layout */
    th.d-label { display: table-cell; margin-bottom: 0; }

    /* Flash messages */
    .flash-wrap { background: var(--pg-bg); }

    /* Hero — dark default / light variant */
    .d-hero { background: linear-gradient(135deg, #050c1b 0%, #0b192e 55%, #060f1e 100%); position: relative; overflow: hidden; }
    html:not(.dark) .d-hero { background: linear-gradient(135deg, #f0f9ff 0%, #ddf1fd 55%, #f5fbff 100%); }

    /* Hero grid pattern */
    .hero-grid {
        position: absolute; inset: 0; pointer-events: none;
        background-image:
            linear-gradient(rgba(38,145,194,0.04) 1px, transparent 1px),
            linear-gradient(90deg, rgba(38,145,194,0.04) 1px, transparent 1px);
        background-size: 44px 44px;
    }
    html:not(.dark) .hero-grid {
        background-image:
            linear-gradient(rgba(38,145,194,0.09) 1px, transparent 1px),
            linear-gradient(90deg, rgba(38,145,194,0.09) 1px, transparent 1px);
    }

    /* KPI card animated border — dark mode only */
    @keyframes borderPulse {
        0%,100% { opacity: 0.2; }
        50%      { opacity: 0.5; }
    }
    .kpi-card { position: relative; border-radius: 1rem; overflow: hidden; }
    html.dark .kpi-card::before {
        content: '';
        position: absolute; inset: 0;
        border-radius: inherit;
        padding: 1px;
        background: linear-gradient(135deg, rgba(38,145,194,0.5), rgba(236,148,60,0.3));
        -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
        -webkit-mask-composite: destination-out;
        mask-composite: exclude;
        animation: borderPulse 3s ease-in-out infinite;
    }
    html:not(.dark) .kpi-card {
        border: 1px solid rgba(38,145,194,0.18);
        box-shadow: 0 2px 14px rgba(38,145,194,0.09);
    }

    /* Heading / number adaptation for light mode */
    html:not(.dark) .d-heading { color: var(--pg-text) !important; }
    html:not(.dark) .d-kpi-num { color: var(--pg-text) !important; }
    html:not(.dark) .d-card .text-white { color: var(--pg-text) !important; }
    html:not(.dark) .d-card .text-slate-200 { color: var(--pg-text) !important; }
    html:not(.dark) .d-card .text-slate-300 { color: var(--pg-text-2) !important; }
    html:not(.dark) .d-hero .text-white { color: #0f172a !important; }
    html:not(.dark) .d-hero .text-slate-500 { color: #475569 !important; }

    /* ─── Icon action buttons (table) ─── */
    .icon-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 2rem; height: 2rem; border-radius: 0.5rem; border: 1px solid;
        transition: background 0.12s, border-color 0.12s, transform 0.12s;
        cursor: pointer; flex-shrink: 0; background: transparent;
        text-decoration: none; position: relative;
    }
    .icon-btn:hover { transform: translateY(-1px); }
    .icon-btn-blue  { color:#2691C2; border-color:rgba(38,145,194,0.22); }
    .icon-btn-blue:hover  { background:rgba(38,145,194,0.12); border-color:rgba(38,145,194,0.38); }
    .icon-btn-slate { color:var(--pg-text-3); border-color:var(--pg-border-2); }
    .icon-btn-slate:hover { background:var(--pg-border); }
    .icon-btn-amber { color:#f59e0b; border-color:rgba(245,158,11,0.22); }
    .icon-btn-amber:hover { background:rgba(245,158,11,0.1); }
    .icon-btn-green { color:#34d399; border-color:rgba(52,211,153,0.22); }
    .icon-btn-green:hover { background:rgba(52,211,153,0.1); }
    .icon-btn-red   { color:#f87171; border-color:rgba(239,68,68,0.18); }
    .icon-btn-red:hover   { background:rgba(239,68,68,0.1); }

    /* ─── Status chip cards ─── */
    .status-chip {
        background: var(--pg-card);
        border: 1px solid var(--pg-border);
        border-radius: 1rem;
        padding: 1rem 1.25rem;
        transition: border-color 0.2s;
    }
    .status-chip:hover { border-color: var(--pg-border-2); }

    /* ─── Widget section header ─── */
    .w-section-title {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--pg-text);
        letter-spacing: 0.03em;
        display: flex;
        align-items: center;
        gap: 0.625rem;
    }

    /* ─── Rating bar ─── */
    .r-bar-bg { height: 6px; border-radius: 999px; background: var(--pg-border); overflow: hidden; }
    .r-bar-fill { height: 100%; border-radius: 999px; background: #EC943C; transition: width 0.8s cubic-bezier(0.16,1,0.3,1); }

    /* ─── Campaign status badges (light + dark) ─── */
    .s-badge {
        display: inline-flex; align-items: center; gap: 0.375rem;
        padding: 0.25rem 0.625rem; border-radius: 999px;
        font-size: 0.6875rem; font-weight: 600; border: 1px solid;
        white-space: nowrap; line-height: 1.4;
    }
    .s-badge-active   { background: rgba(22,163,74,0.08);  color: #15803d; border-color: rgba(22,163,74,0.2); }
    .s-badge-inactive { background: rgba(100,116,139,0.07); color: #475569;  border-color: rgba(100,116,139,0.15); }
    .s-badge-expired  { background: rgba(220,38,38,0.07);  color: #b91c1c; border-color: rgba(220,38,38,0.15); }
    .s-badge-expiring { background: rgba(234,88,12,0.08);  color: #c2410c; border-color: rgba(234,88,12,0.2); }

    html.dark .s-badge-active   { background: rgba(52,211,153,0.1);  color: #34d399; border-color: rgba(52,211,153,0.2); }
    html.dark .s-badge-inactive { background: rgba(100,116,139,0.12);color: #94a3b8; border-color: rgba(100,116,139,0.25); }
    html.dark .s-badge-expired  { background: rgba(248,113,113,0.1); color: #f87171; border-color: rgba(248,113,113,0.2); }
    html.dark .s-badge-expiring { background: rgba(251,146,60,0.1);  color: #fb923c; border-color: rgba(251,146,60,0.2); }

    /* ─── Quick action rows (modern list style) ─── */
    .qa-row {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.625rem 0.875rem; border-radius: 0.875rem;
        border: 1px solid var(--pg-border); background: var(--pg-card);
        transition: all 0.14s ease; text-decoration: none;
        cursor: pointer; width: 100%; min-height: 3rem;
        text-align: right;
    }
    .qa-row:hover {
        background: var(--pg-border);
        border-color: var(--pg-border-2);
        transform: translateX(-2px);
    }
    .qa-icon {
        width: 2.1rem; height: 2.1rem; border-radius: 0.625rem;
        border: 1px solid; display: flex; align-items: center;
        justify-content: center; flex-shrink: 0;
    }

    /* ─── Activity item ─── */
    .act-item {
        display: flex; align-items: flex-start; gap: 0.75rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--pg-border);
    }
    .act-item:last-child { border-bottom: none; padding-bottom: 0; }

    /* ─── Insight row ─── */
    .insight-row {
        display: flex; align-items: center; gap: 0.75rem;
        padding: 0.75rem 0;
        border-bottom: 1px solid var(--pg-border);
    }
    .insight-row:last-child { border-bottom: none; padding-bottom: 0; }
</style>
@endpush

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
@can('export-reviews')
<a href="{{ route('admin.exports.index') }}" class="nav-btn" title="تصدير البيانات">
    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
    <span class="hidden sm:inline">تصدير البيانات</span>
</a>
@endcan
<a href="{{ route('admin.campaigns.create') }}" class="nav-btn nav-btn-primary">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
    </svg>
    <span class="hidden sm:inline">حملة جديدة</span>
    <span class="sm:hidden">جديد</span>
</a>
@endsection

{{-- ═══════════════════════════ HERO ═══════════════════════════ --}}
@section('hero')
<div class="d-hero">

    {{-- Grid texture --}}
    <div class="hero-grid"></div>

    {{-- Blue radial glow (top-right) --}}
    <div style="position:absolute;top:-100px;right:-60px;width:480px;height:480px;background:radial-gradient(circle,rgba(38,145,194,0.11) 0%,transparent 68%);pointer-events:none;"></div>

    {{-- Orange accent glow (bottom-left) --}}
    <div style="position:absolute;bottom:-120px;left:5%;width:360px;height:360px;background:radial-gradient(circle,rgba(236,148,60,0.06) 0%,transparent 70%);pointer-events:none;"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-12 sm:pt-16 pb-12 sm:pb-12 relative">
        <div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-8 lg:gap-12">

            {{-- Left: Title block --}}
            <div class="rise rise-1">
                <div class="flex items-center gap-2 mb-3">
                    <span class="dot-live"></span>
                    <span class="text-[10px] font-extrabold text-slate-500 tracking-[0.2em] uppercase">نظام إدارة التقييمات</span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-black text-white mb-2 leading-tight tracking-tight">
                    تقييمات دورات
                    <span style="background:linear-gradient(90deg,#2691C2,#EC943C);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">EMC</span>
                </h1>
                <p class="text-slate-500 text-sm">إدارة حملات جمع التقييمات ومتابعة أداء الدورات</p>
            </div>

            {{-- Right: KPI Stats --}}
            <div class="grid grid-cols-3 gap-3 sm:gap-4 w-full lg:w-auto rise rise-2">

                {{-- Campaigns --}}
                <div class="kpi-card text-center px-4 sm:px-6 py-5" style="background:var(--pg-card);">
                    <div class="d-label text-center mb-2">الحملات</div>
                    <div class="text-3xl sm:text-4xl font-black text-white d-kpi-num tabular-nums"
                         x-data="{ v: 0 }"
                         x-init="
                             const t={{ $totalCampaigns }}, d=1100, s=performance.now();
                             const f=(n)=>{ const p=Math.min((n-s)/d,1); v=Math.round(t*(1-Math.pow(1-p,3))); if(p<1) requestAnimationFrame(f); };
                             requestAnimationFrame(f);
                         "
                         x-text="v">{{ $totalCampaigns }}</div>
                    <div class="mt-2 mx-auto h-0.5 w-8 rounded-full" style="background:linear-gradient(90deg,#2691C2,#1a5f8a);"></div>
                </div>

                {{-- Reviews --}}
                <div class="kpi-card text-center px-4 sm:px-6 py-5" style="background:var(--pg-card);">
                    <div class="d-label text-center mb-2">التقييمات</div>
                    <div class="text-3xl sm:text-4xl font-black text-white d-kpi-num tabular-nums"
                         x-data="{ v: 0 }"
                         x-init="
                             const t={{ $totalReviews }}, d=1400, s=performance.now();
                             const f=(n)=>{ const p=Math.min((n-s)/d,1); v=Math.round(t*(1-Math.pow(1-p,3))); if(p<1) requestAnimationFrame(f); };
                             requestAnimationFrame(f);
                         "
                         x-text="v">{{ $totalReviews }}</div>
                    <div class="mt-2 mx-auto h-0.5 w-8 rounded-full" style="background:linear-gradient(90deg,#EC943C,#9a5012);"></div>
                </div>

                {{-- Avg Rating --}}
                <div class="kpi-card text-center px-4 sm:px-6 py-5" style="background:var(--pg-card);">
                    <div class="d-label text-center mb-2">متوسط التقييم</div>
                    <div class="flex items-baseline justify-center gap-1">
                        <span class="text-3xl sm:text-4xl font-black text-white d-kpi-num">{{ $averageRating }}</span>
                        <span style="color:#EC943C;" class="text-xl font-black leading-none">★</span>
                    </div>
                    <div class="mt-2 mx-auto h-0.5 w-8 rounded-full" style="background:linear-gradient(90deg,#f59e0b,#92400e);"></div>
                </div>

            </div>
        </div>
    </div>
</div>
@endsection

{{-- ═══════════════════════════ CONTENT ═══════════════════════════ --}}
@section('content')
<main class="flex-1 overflow-x-hidden">

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">

        {{-- ── Collapsible Campaign Filter ── --}}
        @php $hasFilters = array_filter($filters); @endphp
        <div x-data="{ open: {{ $hasFilters ? 'true' : 'false' }} }"
             class="d-card mb-6 overflow-hidden rise rise-3">

            <button type="button" @click="open = !open"
                    class="w-full flex items-center justify-between gap-3 px-5 sm:px-6 py-4 text-right transition-colors"
                    style="hover:background:rgba(255,255,255,0.02);">
                <div class="flex items-center gap-2.5">
                    <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                         style="background:rgba(38,145,194,0.15);border:1px solid rgba(38,145,194,0.25);">
                        <svg class="w-3.5 h-3.5" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-bold" style="color:var(--pg-text-2);">تصفية الحملات</span>
                    @if($hasFilters)
                    <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full"
                          style="background:rgba(38,145,194,0.15);color:#7dd3fc;border:1px solid rgba(38,145,194,0.25);">مُفعّل</span>
                    @endif
                </div>
                <svg class="w-4 h-4 text-slate-600 transition-transform duration-200 shrink-0"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <form method="GET" action="{{ route('admin.dashboard') }}"
                  x-show="open"
                  x-transition:enter="transition ease-out duration-200"
                  x-transition:enter-start="opacity-0 -translate-y-2"
                  x-transition:enter-end="opacity-100 translate-y-0"
                  x-transition:leave="transition ease-in duration-150"
                  x-transition:leave-start="opacity-100 translate-y-0"
                  x-transition:leave-end="opacity-0 -translate-y-2"
                  class="px-5 sm:px-6 pb-5 pt-4"
                  style="display:none;border-top:1px solid rgba(38,145,194,0.1);">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div>
                        <label class="d-label">بحث عن دورة</label>
                        <input type="text" name="course_name" value="{{ $filters['course_name'] ?? '' }}"
                               placeholder="اسم الدورة..." class="d-input">
                    </div>
                    <div>
                        <label class="d-label">اسم المدرب</label>
                        <input type="text" name="instructor_name" value="{{ $filters['instructor_name'] ?? '' }}"
                               placeholder="اسم المدرب..." class="d-input">
                    </div>
                    <div>
                        <label class="d-label">الحالة</label>
                        <select name="status" class="d-input">
                            <option value="all"      {{ ($filters['status'] ?? 'all') === 'all'      ? 'selected' : '' }}>جميع الحالات</option>
                            <option value="active"   {{ ($filters['status'] ?? '') === 'active'      ? 'selected' : '' }}>نشطة</option>
                            <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive'    ? 'selected' : '' }}>متوقفة</option>
                            <option value="expired"  {{ ($filters['status'] ?? '') === 'expired'     ? 'selected' : '' }}>منتهية</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="d-label">من</label>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="d-input">
                        </div>
                        <div>
                            <label class="d-label">إلى</label>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="d-input">
                        </div>
                    </div>
                </div>
                <div class="flex gap-2 mb-4 mt-4 pt-4" style="border-top:1px solid rgba(38,145,194,0.08);">
                    <button type="submit"
                            class="flex items-center gap-1.5 text-white text-sm font-bold px-5 py-2.5 rounded-xl transition-all hover:opacity-90"
                            style="background:linear-gradient(135deg,#2691C2,#1a4f6e);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        بحث
                    </button>
                    @if($hasFilters)
                    <a href="{{ route('admin.dashboard') }}"
                       class="flex items-center gap-1.5 text-sm font-bold px-5 py-2.5 rounded-xl transition-all"
                       style="color:#2691C2;border:1px solid rgba(38,145,194,0.22);background:rgba(38,145,194,0.05);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        إعادة تعيين
                    </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- ── Campaign Status Summary ── --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6 rise rise-3" style="animation-delay:0.15s;">
            {{-- Active --}}
            <div class="status-chip">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#34d399;box-shadow:0 0 0 3px rgba(52,211,153,0.2);"></span>
                    <span class="text-[11px] font-bold" style="color:var(--pg-text-3);">نشطة</span>
                </div>
                <p class="text-2xl font-black" style="color:var(--pg-text);">{{ $activeCampaigns }}</p>
            </div>
            {{-- Inactive --}}
            <div class="status-chip">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#64748b;"></span>
                    <span class="text-[11px] font-bold" style="color:var(--pg-text-3);">متوقفة</span>
                </div>
                <p class="text-2xl font-black" style="color:var(--pg-text);">{{ $inactiveCampaigns }}</p>
            </div>
            {{-- Expiring Soon --}}
            <div class="status-chip">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#EC943C;"></span>
                    <span class="text-[11px] font-bold" style="color:var(--pg-text-3);">تنتهي قريباً</span>
                </div>
                <p class="text-2xl font-black" style="color:var(--pg-text);">{{ $expiringSoonCount }}</p>
            </div>
            {{-- Expired --}}
            <div class="status-chip">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:#f87171;opacity:0.7;"></span>
                    <span class="text-[11px] font-bold" style="color:var(--pg-text-3);">منتهية</span>
                </div>
                <p class="text-2xl font-black" style="color:var(--pg-text);">{{ $expiredCampaigns }}</p>
            </div>
        </div>

        {{-- ── Main Grid: Campaigns + Latest Reviews ── --}}
        <div class="grid grid-cols-1 xl:grid-cols-3 gap-5 sm:gap-6 mb-8">

            {{-- Campaigns Table --}}
            <div class="xl:col-span-2 rise rise-4">
                <div class="d-card overflow-hidden h-full">
                    <div class="px-5 sm:px-7 py-4 flex items-center justify-between gap-3"
                         style="border-bottom:1px solid rgba(38,145,194,0.1);">
                        <div class="flex items-center gap-3">
                            <div class="d-accent h-5" style="background:linear-gradient(to bottom,#2691C2,#0a3d5c);"></div>
                            <h2 class="text-sm font-extrabold text-white d-heading tracking-wide">الحملات</h2>
                        </div>
                        @if($campaigns->isNotEmpty())
                        <span class="text-[10px] font-medium px-2.5 py-1 rounded-full"
                              style="background:var(--pg-border);color:var(--pg-text-3);border:1px solid var(--pg-border-2);">
                            {{ $campaigns->count() }} {{ $campaigns->count() === 1 ? 'حملة' : 'حملات' }}
                        </span>
                        @endif
                    </div>

                    @if($campaigns->isEmpty())
                    <div class="px-6 py-16 text-center">
                        <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                             style="background:rgba(38,145,194,0.08);border:1px solid rgba(38,145,194,0.2);">
                            <svg class="w-7 h-7" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                      d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                        </div>
                        <p class="text-slate-300 font-bold mb-1">لا توجد حملات بعد</p>
                        <p class="text-slate-600 text-sm mb-6">ابدأ بإنشاء حملة تقييم لدورتك الأولى</p>
                        <a href="{{ route('admin.campaigns.create') }}"
                           class="inline-flex items-center gap-2 text-white text-sm font-bold px-5 py-2.5 rounded-xl hover:opacity-90"
                           style="background:linear-gradient(135deg,#2691C2,#1a4f6e);">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                            </svg>
                            إنشاء حملة
                        </a>
                    </div>
                    @else
                    <div class="overflow-x-auto">
                        <table class="w-full" style="min-width:500px;">
                            <thead>
                                <tr style="border-bottom:1px solid rgba(38,145,194,0.08);">
                                    <th class="px-5 sm:px-7 py-3 text-right d-label">الدورة</th>
                                    <th class="px-4 py-3 text-center d-label">التقييمات</th>
                                    <th class="px-4 py-3 text-center d-label">المتوسط</th>
                                    <th class="px-4 py-3 text-center d-label">الحالة</th>
                                    <th class="px-4 py-3 text-center d-label">إجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($campaigns as $campaign)
                                @php
                                    $badge          = $campaign->statusBadge();
                                    $isExpired      = $campaign->isExpired();
                                    $isActive       = $campaign->is_active && !$isExpired;
                                    $isExpiringSoon = $isActive && $campaign->expires_at && $campaign->expires_at->isBefore(now()->addDays(3));
                                @endphp
                                <tr class="d-tr"
                                    style="border-bottom:1px solid rgba(255,255,255,0.025);"
                                    onclick="window.location='{{ route('admin.campaigns.show', $campaign) }}'">
                                    <td class="px-5 sm:px-7 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="{{ $isActive ? 'dot-live' : ($isExpired ? 'dot-exp' : 'dot-off') }}"></div>
                                            <div>
                                                <p class="font-bold text-sm leading-tight" style="color:var(--pg-text);">{{ $campaign->course_name }}</p>
                                                @if($campaign->instructor_name)
                                                <p class="text-xs mt-0.5" style="color:var(--pg-text-3);">{{ $campaign->instructor_name }}</p>
                                                @endif
                                                @if($campaign->expires_at)
                                                <p class="text-xs mt-0.5" style="color:var(--pg-text-4);">
                                                    {{ $campaign->isExpired() ? 'انتهت: ' : 'تنتهي: ' }}{{ $campaign->expires_at->format('d/m/Y') }}
                                                </p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-center">
                                        <span class="text-sm font-bold" style="color:var(--pg-text-2);">{{ $campaign->reviews_count }}</span>
                                    </td>
                                                    <td class="px-4 py-4 text-center">
                                        <x-star-rating :rating="number_format($campaign->reviews_avg_overall_rating ?? 0, 1)" size="sm" />
                                    </td>
                                    <td class="px-4 py-4 text-center" onclick="event.stopPropagation()">
                                        @php
                                            if ($isExpired)       { $bClass = 's-badge-expired';  $dot = '#ef4444'; }
                                            elseif ($isExpiringSoon) { $bClass = 's-badge-expiring'; $dot = '#f97316'; }
                                            elseif ($isActive)    { $bClass = 's-badge-active';   $dot = '#22c55e'; }
                                            else                  { $bClass = 's-badge-inactive'; $dot = '#94a3b8'; }
                                        @endphp
                                        <span class="s-badge {{ $bClass }}">
                                            <span class="w-1.5 h-1.5 rounded-full shrink-0" style="background:{{ $dot }};"></span>
                                            {{ $badge['label'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3" onclick="event.stopPropagation()">
                                        <div class="flex items-center justify-center gap-1.5">
                                            {{-- عرض --}}
                                            <a href="{{ route('admin.campaigns.show', $campaign) }}"
                                               class="icon-btn icon-btn-blue"
                                               title="عرض" aria-label="عرض الحملة">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                </svg>
                                            </a>
                                            {{-- تعديل --}}
                                            <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                                               class="icon-btn icon-btn-slate"
                                               title="تعديل" aria-label="تعديل الحملة">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            </a>
                                            {{-- إيقاف / تفعيل --}}
                                            <form method="POST" action="{{ route('admin.campaigns.toggle', $campaign) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit"
                                                        class="icon-btn {{ $campaign->is_active ? 'icon-btn-amber' : 'icon-btn-green' }}"
                                                        title="{{ $campaign->is_active ? 'إيقاف الحملة' : 'تفعيل الحملة' }}"
                                                        aria-label="{{ $campaign->is_active ? 'إيقاف' : 'تفعيل' }}">
                                                    @if($campaign->is_active)
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    @else
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664zM21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    @endif
                                                </button>
                                            </form>
                                            {{-- حذف --}}
                                            <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}"
                                                  x-data
                                                  @submit.prevent="if(confirm('هل أنت متأكد من حذف هذه الحملة وجميع تقييماتها؟')) $el.submit()">
                                                @csrf @method('DELETE')
                                                <button type="submit"
                                                        class="icon-btn icon-btn-red"
                                                        title="حذف الحملة" aria-label="حذف الحملة">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Latest Reviews --}}
            <div class="xl:col-span-1 rise rise-5">
                <div class="d-card overflow-hidden h-full">
                    <div class="px-5 py-4 flex items-center gap-3"
                         style="border-bottom:1px solid rgba(38,145,194,0.1);">
                        <div class="d-accent h-5" style="background:linear-gradient(to bottom,#EC943C,#92400e);"></div>
                        <h2 class="text-sm font-extrabold text-white d-heading tracking-wide">آخر التقييمات</h2>
                    </div>
                    @if($latestReviews->isEmpty())
                    <div class="px-5 py-14 text-center">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center mx-auto mb-3"
                             style="background:rgba(236,148,60,0.08);border:1px solid rgba(236,148,60,0.15);">
                            <svg class="w-5 h-5" style="color:#EC943C;" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        </div>
                        <p class="text-sm font-bold" style="color:var(--pg-text-3);">لا توجد تقييمات حديثة</p>
                    </div>
                    @else
                    <div>
                        @foreach($latestReviews->take(5) as $review)
                        <a href="{{ route('admin.campaigns.show', $review->review_campaign_id) }}"
                           class="notif-item flex items-center gap-3 px-5 py-3.5"
                           style="border-bottom:1px solid var(--pg-border);">
                            {{-- Avatar --}}
                            <div class="w-9 h-9 rounded-xl flex items-center justify-center shrink-0 text-xs font-extrabold text-white"
                                 style="background:linear-gradient(135deg,#2691C2,#22334A);">
                                {{ mb_strtoupper(mb_substr($review->student_name, 0, 1)) }}
                            </div>
                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold leading-tight truncate" style="color:var(--pg-text);">{{ $review->student_name }}</p>
                                <p class="text-[11px] truncate mt-0.5 leading-tight" style="color:var(--pg-text-3);">{{ $review->campaign->course_name }}</p>
                            </div>
                            {{-- Rating + Time --}}
                            <div class="shrink-0 text-end flex flex-col items-end gap-0.5">
                                <x-star-rating :rating="$review->overall_rating" size="xs" :show-number="false" />
                                <p class="text-[10px]" style="color:var(--pg-text-4);">{{ $review->created_at->diffForHumans() }}</p>
                            </div>
                        </a>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- ── Insights + Rating + Actions + Activity ── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 sm:gap-6 mb-8" style="animation:riseIn 0.55s cubic-bezier(0.16,1,0.3,1) 0.36s both;">

            {{-- Quick Insights --}}
            <div class="d-card p-5">
                <div class="w-section-title mb-4">
                    <div class="d-accent h-4" style="background:linear-gradient(to bottom,#2691C2,#EC943C);"></div>
                    <span>رؤى سريعة</span>
                </div>

                @if($topRatedCampaign)
                <div class="insight-row">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                         style="background:rgba(52,211,153,0.1);border:1px solid rgba(52,211,153,0.2);">
                        <svg class="w-3.5 h-3.5" style="color:#34d399;" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-extrabold" style="color:var(--pg-text-3);">أعلى تقييماً</p>
                        <p class="text-xs font-bold truncate" style="color:var(--pg-text);">{{ $topRatedCampaign->course_name }}</p>
                    </div>
                    <span class="text-xs font-extrabold shrink-0" style="color:#EC943C;">{{ number_format($topRatedCampaign->reviews_avg_overall_rating, 1) }} ★</span>
                </div>
                @endif

                @if($lowestRatedCampaign && $lowestRatedCampaign->id !== ($topRatedCampaign?->id))
                <div class="insight-row">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                         style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.15);">
                        <svg class="w-3.5 h-3.5" style="color:#f87171;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-extrabold" style="color:var(--pg-text-3);">أقل تقييماً</p>
                        <p class="text-xs font-bold truncate" style="color:var(--pg-text);">{{ $lowestRatedCampaign->course_name }}</p>
                    </div>
                    <span class="text-xs font-extrabold shrink-0" style="color:#f87171;">{{ number_format($lowestRatedCampaign->reviews_avg_overall_rating, 1) }} ★</span>
                </div>
                @endif

                @if($mostReviewedCampaign)
                <div class="insight-row">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                         style="background:rgba(38,145,194,0.1);border:1px solid rgba(38,145,194,0.18);">
                        <svg class="w-3.5 h-3.5" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-extrabold" style="color:var(--pg-text-3);">أكثر تقييمات</p>
                        <p class="text-xs font-bold truncate" style="color:var(--pg-text);">{{ $mostReviewedCampaign->course_name }}</p>
                    </div>
                    <span class="text-xs font-extrabold shrink-0" style="color:#2691C2;">{{ $mostReviewedCampaign->reviews_count }}</span>
                </div>
                @endif

                <div class="insight-row">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                         style="background:rgba(52,211,153,0.08);border:1px solid rgba(52,211,153,0.15);">
                        <svg class="w-3.5 h-3.5" style="color:#34d399;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-[11px] font-extrabold" style="color:var(--pg-text-3);">نسبة التوصية العامة</p>
                        <div class="flex items-center gap-2 mt-0.5">
                            <div class="r-bar-bg flex-1" style="background:var(--pg-border);">
                                <div class="r-bar-fill" style="width:{{ $globalRecommendPct }}%;background:#34d399;"></div>
                            </div>
                        </div>
                    </div>
                    <span class="text-xs font-extrabold shrink-0" style="color:#34d399;">{{ $globalRecommendPct }}%</span>
                </div>

                @if(!$topRatedCampaign)
                <p class="text-xs text-center py-6" style="color:var(--pg-text-4);">لا توجد بيانات كافية</p>
                @endif
            </div>

            {{-- Rating Breakdown --}}
            <div class="d-card p-5">
                <div class="w-section-title mb-4">
                    <div class="d-accent h-4" style="background:linear-gradient(to bottom,#EC943C,#c97a20);"></div>
                    <span>توزيع التقييمات</span>
                </div>
                @if($totalReviews > 0)
                <div class="space-y-3">
                    @foreach([5, 4, 3, 2, 1] as $star)
                    @php $cnt = $ratingBreakdown[$star] ?? 0; $pct = $totalReviews > 0 ? round($cnt / $totalReviews * 100) : 0; @endphp
                    <div class="flex items-center gap-3">
                        <span class="text-[11px] font-extrabold shrink-0 w-3 text-end" style="color:#EC943C;">{{ $star }}</span>
                        <span class="text-[10px] shrink-0" style="color:#EC943C;">★</span>
                        <div class="r-bar-bg flex-1">
                            <div class="r-bar-fill" style="width:{{ $pct }}%;"></div>
                        </div>
                        <span class="text-[11px] font-bold shrink-0 w-6 text-end tabular-nums" style="color:var(--pg-text-3);">{{ $cnt }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-center py-6" style="color:var(--pg-text-4);">لا توجد تقييمات بعد</p>
                @endif
            </div>

            {{-- Quick Actions + Recent Activity --}}
            <div class="flex flex-col gap-5">

                {{-- Quick Actions --}}
                <div class="d-card p-5">
                    <div class="w-section-title mb-4">
                        <div class="d-accent h-4" style="background:linear-gradient(to bottom,#22334A,#2691C2);"></div>
                        <span>إجراءات سريعة</span>
                    </div>
                    <div class="space-y-2.5">

                        {{-- Create Campaign --}}
                        <a href="{{ route('admin.campaigns.create') }}" class="qa-row">
                            <div class="qa-icon" style="background:rgba(38,145,194,0.1);border-color:rgba(38,145,194,0.22);">
                                <svg class="w-4 h-4" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <span class="flex-1 text-sm font-semibold" style="color:var(--pg-text);">إنشاء حملة جديدة</span>
                            <svg class="w-3.5 h-3.5 shrink-0" style="color:var(--pg-text-4);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>

                        {{-- View All --}}
                        <a href="{{ route('admin.dashboard') }}" class="qa-row">
                            <div class="qa-icon" style="background:var(--pg-border);border-color:var(--pg-border-2);">
                                <svg class="w-4 h-4" style="color:var(--pg-text-3);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                                </svg>
                            </div>
                            <span class="flex-1 text-sm font-semibold" style="color:var(--pg-text);">عرض جميع الحملات</span>
                            <svg class="w-3.5 h-3.5 shrink-0" style="color:var(--pg-text-4);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>

                        {{-- Notifications --}}
                        <a href="{{ route('admin.notifications.index') }}" class="qa-row">
                            <div class="qa-icon" style="background:rgba(236,148,60,0.08);border-color:rgba(236,148,60,0.18);">
                                <svg class="w-4 h-4" style="color:#EC943C;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                </svg>
                            </div>
                            <span class="flex-1 text-sm font-semibold" style="color:var(--pg-text);">الإشعارات</span>
                            <svg class="w-3.5 h-3.5 shrink-0" style="color:var(--pg-text-4);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                            </svg>
                        </a>

                        {{-- Sync --}}
                        <div x-data="{ syncing: false }">
                            <button type="button"
                                    @click="syncing = true; const u = new URL(window.location.href); u.searchParams.set('synced', '1'); window.location.href = u.toString();"
                                    class="qa-row"
                                    :disabled="syncing">
                                <div class="qa-icon" style="background:rgba(38,145,194,0.06);border-color:rgba(38,145,194,0.14);">
                                    <svg x-show="syncing" style="display:none;" class="w-4 h-4 animate-spin" style="color:#2691C2;" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                    </svg>
                                    <svg x-show="!syncing" class="w-4 h-4" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                    </svg>
                                </div>
                                <span class="flex-1 text-sm font-semibold text-right" style="color:var(--pg-text);"
                                      x-text="syncing ? 'جارٍ التحديث...' : 'تحديث البيانات'">تحديث البيانات</span>
                                <svg class="w-3.5 h-3.5 shrink-0" style="color:var(--pg-text-4);" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-show="!syncing">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </button>
                        </div>

                    </div>
                </div>

                {{-- Recent Activity --}}
                <div class="d-card p-5 flex-1">
                    <div class="w-section-title mb-3">
                        <div class="d-accent h-4" style="background:linear-gradient(to bottom,#EC943C,#22334A);"></div>
                        <span>النشاط الأخير</span>
                    </div>
                    <div>
                        @if($latestCampaign)
                        <div class="act-item">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                                 style="background:rgba(38,145,194,0.1);border:1px solid rgba(38,145,194,0.18);">
                                <svg class="w-3.5 h-3.5" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-extrabold" style="color:var(--pg-text-3);">آخر حملة أُضيفت</p>
                                <p class="text-xs font-semibold truncate" style="color:var(--pg-text);">{{ $latestCampaign->course_name }}</p>
                                <p class="text-[10px]" style="color:var(--pg-text-4);">{{ $latestCampaign->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        @endif
                        @if($latestReviews->isNotEmpty())
                        @php $lr = $latestReviews->first(); @endphp
                        <div class="act-item">
                            <div class="w-7 h-7 rounded-lg flex items-center justify-center shrink-0"
                                 style="background:rgba(236,148,60,0.1);border:1px solid rgba(236,148,60,0.18);">
                                <svg class="w-3.5 h-3.5" style="color:#EC943C;" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-[11px] font-extrabold" style="color:var(--pg-text-3);">آخر تقييم وصل</p>
                                <p class="text-xs font-semibold truncate" style="color:var(--pg-text);">{{ $lr->student_name }}</p>
                                <p class="text-[10px] truncate" style="color:var(--pg-text-4);">{{ $lr->campaign->course_name ?? '—' }} · {{ $lr->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        @endif
                        @if(!$latestCampaign && $latestReviews->isEmpty())
                        <p class="text-xs text-center py-4" style="color:var(--pg-text-4);">لا يوجد نشاط حتى الآن</p>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        {{-- ── Analytics Charts ── --}}
        @if($totalReviews > 0)
        <div style="animation:riseIn 0.55s cubic-bezier(0.16,1,0.3,1) 0.4s both;">

            <div class="flex items-center gap-3 mb-5">
                <div class="d-accent h-5" style="background:linear-gradient(to bottom,#2691C2,#EC943C);"></div>
                <h2 class="text-sm font-extrabold text-white d-heading tracking-wide">التحليلات</h2>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

                {{-- Monthly Bar Chart --}}
                <div class="lg:col-span-2 d-card p-6">
                    <p class="d-label mb-4">التقييمات شهرياً</p>
                    <div style="height:210px; position:relative;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>

                {{-- Recommendation Donut --}}
                <div class="d-card p-6 flex flex-col">
                    <p class="d-label mb-4">نسبة التوصية</p>
                    <div style="height:160px; position:relative; flex:1;">
                        <canvas id="recommendChart"></canvas>
                    </div>
                    <div class="flex justify-center gap-5 mt-4">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full" style="background:#34d399;"></span>
                            <span class="text-[11px] text-slate-600">يوصي ({{ $recommendYes }})</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full" style="background:#f87171;"></span>
                            <span class="text-[11px] text-slate-600">لا يوصي ({{ $recommendNo }})</span>
                        </div>
                    </div>
                </div>

                {{-- Difficulty Donut --}}
                <div class="d-card p-6">
                    <p class="d-label mb-4">توزيع مستوى الصعوبة</p>
                    <div style="height:190px; position:relative;">
                        <canvas id="difficultyChart"></canvas>
                    </div>
                </div>

            </div>
        </div>

        <script type="application/json" id="dashboard-chart-data">
        {
            "monthlyLabels": {!! json_encode($monthlyLabels) !!},
            "monthlyValues": {!! json_encode($monthlyValues) !!},
            "recommendYes": {{ $recommendYes }},
            "recommendNo": {{ $recommendNo }},
            "difficultyData": {!! json_encode($difficultyRaw) !!}
        }
        </script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
        <script>
        (function () {
            var cd = JSON.parse(document.getElementById('dashboard-chart-data').textContent);

            Chart.defaults.font.family = 'Cairo, sans-serif';
            Chart.defaults.color = '#475569';

            new Chart(document.getElementById('monthlyChart'), {
                type: 'bar',
                data: {
                    labels: cd.monthlyLabels,
                    datasets: [{
                        label: 'التقييمات',
                        data: cd.monthlyValues,
                        backgroundColor: 'rgba(38,145,194,0.22)',
                        borderColor: '#2691C2',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        hoverBackgroundColor: 'rgba(38,145,194,0.38)',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, color: '#334155' },
                            grid: { color: 'rgba(38,145,194,0.07)' },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#334155' },
                            border: { display: false }
                        }
                    }
                }
            });

            new Chart(document.getElementById('recommendChart'), {
                type: 'doughnut',
                data: {
                    labels: ['يوصي', 'لا يوصي'],
                    datasets: [{
                        data: [cd.recommendYes, cd.recommendNo],
                        backgroundColor: ['#34d399', '#f87171'],
                        borderWidth: 0,
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: { legend: { display: false } }
                }
            });

            var dL = Object.keys(cd.difficultyData);
            var dV = Object.values(cd.difficultyData);
            var dC = dL.map(function(l) {
                return l === 'سهل' ? '#34d399' : l === 'متوسط' ? '#fbbf24' : '#f87171';
            });
            new Chart(document.getElementById('difficultyChart'), {
                type: 'doughnut',
                data: {
                    labels: dL,
                    datasets: [{ data: dV, backgroundColor: dC, borderWidth: 0, hoverOffset: 6 }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '50%',
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 14, boxWidth: 10, color: '#475569', font: { size: 11 } }
                        }
                    }
                }
            });
        })();
        </script>
        @endif

    </div>{{-- /max-w-7xl --}}
</main>
@endsection
