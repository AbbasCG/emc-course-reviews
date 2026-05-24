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
<a href="{{ route('admin.campaigns.edit', $campaign) }}"
   class="flex items-center gap-1.5 text-white text-xs font-bold px-3 py-2 rounded-xl transition-all whitespace-nowrap"
   style="background: rgba(255,255,255,0.1);">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
    </svg>
    <span class="hidden sm:inline">تعديل</span>
</a>
<form method="POST" action="{{ route('admin.campaigns.toggle', $campaign) }}">
    @csrf @method('PATCH')
    <button type="submit"
        class="flex items-center gap-1.5 text-xs font-bold px-3 py-2 rounded-xl transition-all whitespace-nowrap {{ $campaign->is_active ? 'text-yellow-300' : 'text-emerald-300' }}"
        style="background: rgba(255,255,255,0.1);">
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
   class="flex items-center gap-1.5 text-white text-xs font-bold px-3 py-2 rounded-xl transition-all hover:opacity-90 whitespace-nowrap"
   style="background: linear-gradient(135deg, #7c3aed, #5b21b6);">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
    </svg>
    <span class="hidden sm:inline">PDF</span>
</a>
<a href="{{ route('admin.campaigns.export-csv', $campaign) }}"
   class="flex items-center gap-1.5 text-white text-xs font-bold px-3 py-2 rounded-xl transition-all hover:opacity-90 whitespace-nowrap"
   style="background: linear-gradient(135deg, #EC943C, #d4782a);">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
    <span class="hidden sm:inline">CSV</span>
</a>
@endsection

@section('hero')
@php $badge = $campaign->statusBadge(); @endphp
<div class="px-4 sm:px-6 py-8 sm:py-10" style="background: linear-gradient(135deg, #22334A 0%, #1a2a3e 100%);">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('admin.dashboard') }}"
           class="inline-flex items-center gap-1.5 text-white/60 hover:text-white text-sm font-semibold mb-4 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            رجوع
        </a>
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-1 wrap-break-word">{{ $campaign->course_name }}</h1>
                @if($campaign->instructor_name)
                <p class="text-slate-400 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    {{ $campaign->instructor_name }}
                </p>
                @endif
                @if($campaign->expires_at)
                <p class="text-slate-400 text-xs mt-1 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    {{ $campaign->isExpired() ? 'انتهت الصلاحية: ' : 'تنتهي: ' }}{{ $campaign->expires_at->format('d/m/Y') }}
                </p>
                @endif
            </div>
            <span class="shrink-0 px-4 py-2 rounded-full text-xs font-bold border
                {{ $campaign->is_active && !$campaign->isExpired() ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-red-500/20 text-red-300 border-red-500/30' }}">
                ● {{ $badge['label'] }}
            </span>
        </div>
    </div>
</div>
@endsection

@section('content')
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4 mb-8">
        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 col-span-2 sm:col-span-1">
            <p class="text-xs text-slate-500 font-bold mb-1">إجمالي التقييمات</p>
            <p class="text-3xl font-extrabold text-slate-900">{{ $reviewCount }}</p>
        </div>
        @foreach([
            ['label' => 'التقييم الكلي',   'value' => $averageRating],
            ['label' => 'المحتوى',          'value' => $averageContentRating],
            ['label' => 'المدرب',           'value' => $averageInstructorRating],
            ['label' => 'التنظيم',          'value' => $averageOrganizationRating],
        ] as $stat)
        <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100">
            <p class="text-xs text-slate-500 font-bold mb-1">{{ $stat['label'] }}</p>
            <div class="flex items-baseline gap-1">
                <p class="text-3xl font-extrabold text-slate-900">{{ $stat['value'] }}</p>
                <span class="text-yellow-400">★</span>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Recommend bar --}}
    @if($reviewCount > 0)
    <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 mb-6 flex items-center gap-4">
        <p class="text-sm font-bold text-slate-700 shrink-0">نسبة التوصية</p>
        <div class="flex-1 bg-slate-100 rounded-full h-3 overflow-hidden">
            <div class="h-full rounded-full bg-emerald-400 transition-all" style="width: {{ $recommendPct }}%"></div>
        </div>
        <span class="text-sm font-extrabold text-emerald-600 shrink-0">{{ $recommendPct }}%</span>
    </div>
    @endif

    {{-- Info + Link --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-8">

        {{-- Campaign Info --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-100 h-full">
                <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
                    <h2 class="text-base font-bold text-slate-900 flex items-center gap-2">
                        <svg class="w-5 h-5" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        معلومات الحملة
                    </h2>
                    <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}"
                          x-data
                          @submit.prevent="if(confirm('هل أنت متأكد من حذف هذه الحملة وجميع تقييماتها؟ لا يمكن التراجع.')) $el.submit()">
                        @csrf @method('DELETE')
                        <button type="submit"
                            class="flex items-center gap-1.5 text-xs font-bold px-4 py-2 rounded-xl bg-red-50 text-red-600 border border-red-200 hover:bg-red-100 transition-all whitespace-nowrap">
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
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-2">الوصف</p>
                        <p class="text-slate-700 text-sm leading-relaxed">{{ $campaign->description }}</p>
                    </div>
                    @endif
                    @if($campaign->course_date)
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5">تاريخ الدورة</p>
                        <p class="text-slate-900 font-semibold text-sm">{{ $campaign->course_date->format('d / m / Y') }}</p>
                    </div>
                    @endif
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5">تاريخ الإنشاء</p>
                        <p class="text-slate-900 font-semibold text-sm">{{ $campaign->created_at->format('d / m / Y') }}</p>
                    </div>
                    @if($campaign->expires_at)
                    <div>
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-wider mb-1.5">انتهاء الصلاحية</p>
                        <p class="font-semibold text-sm {{ $campaign->isExpired() ? 'text-red-600' : 'text-slate-900' }}">
                            {{ $campaign->expires_at->format('d / m / Y') }}
                        </p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Review Link --}}
        <div x-data="{ copied: false }">
            <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border-2 border-dashed h-full" style="border-color: rgba(236,148,60,0.25);">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #EC943C, #d4782a);">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">رابط التقييم</h3>
                </div>
                <div class="bg-slate-50 rounded-2xl p-4 mb-4">
                    <p class="text-xs text-slate-500 break-all leading-relaxed">{{ route('review.show', $campaign->token) }}</p>
                </div>
                <button
                    @click="navigator.clipboard.writeText('{{ route('review.show', $campaign->token) }}').then(() => { copied = true; setTimeout(() => copied = false, 2500) })"
                    class="w-full flex items-center justify-center gap-2 font-bold text-sm py-3 rounded-2xl transition-all"
                    :class="copied ? 'bg-emerald-100 text-emerald-700' : 'text-white hover:opacity-90'"
                    :style="copied ? '' : 'background: linear-gradient(135deg, #EC943C, #d4782a);'">
                    <template x-if="!copied">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                            </svg>
                            نسخ الرابط
                        </span>
                    </template>
                    <template x-if="copied">
                        <span class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            تم النسخ!
                        </span>
                    </template>
                </button>
            </div>
        </div>
    </div>

    {{-- Reviews Section --}}
    <div>
        {{-- Section header + export buttons --}}
        <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
            <h2 class="text-xl font-extrabold text-slate-900">التقييمات ({{ $reviewCount }})</h2>
            @if($reviewCount > 0)
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('admin.campaigns.exportPdf', $campaign) }}"
                   class="flex items-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all hover:opacity-90 whitespace-nowrap"
                   style="background: #7c3aed;">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    تصدير PDF
                </a>
                <a href="{{ route('admin.campaigns.export-csv', $campaign) }}"
                   class="flex items-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl text-white transition-all hover:opacity-90 whitespace-nowrap"
                   style="background: linear-gradient(135deg, #EC943C, #d4782a);">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    تصدير CSV
                </a>
            </div>
            @endif
        </div>

        {{-- Reviews Filters --}}
        @if($reviewCount > 0)
        <form method="GET" action="{{ route('admin.campaigns.show', $campaign) }}"
              class="bg-white rounded-3xl shadow-sm border border-slate-100 p-5 sm:p-6 mb-5">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                </svg>
                <span class="text-sm font-bold text-slate-700">تصفية التقييمات</span>
                @if(array_filter($reviewFilters))
                <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">مُفعّل</span>
                @endif
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">اسم الطالب</label>
                    <input type="text" name="student_name" value="{{ $reviewFilters['student_name'] ?? '' }}"
                        placeholder="ابحث بالاسم..."
                        class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 placeholder-slate-400 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">البريد الإلكتروني</label>
                    <input type="text" name="email" value="{{ $reviewFilters['email'] ?? '' }}"
                        placeholder="ابحث بالبريد..."
                        class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 placeholder-slate-400 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">التقييم</label>
                    <select name="rating"
                        class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 bg-white transition-colors">
                        <option value="">جميع التقييمات</option>
                        @for($i = 5; $i >= 1; $i--)
                        <option value="{{ $i }}" {{ ($reviewFilters['rating'] ?? '') == $i ? 'selected' : '' }}>{{ $i }} نجوم</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">يوصي بالدورة</label>
                    <select name="recommend"
                        class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 bg-white transition-colors">
                        <option value="">الكل</option>
                        <option value="yes" {{ ($reviewFilters['recommend'] ?? '') === 'yes' ? 'selected' : '' }}>يوصي</option>
                        <option value="no"  {{ ($reviewFilters['recommend'] ?? '') === 'no'  ? 'selected' : '' }}>لا يوصي</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">مستوى الصعوبة</label>
                    <select name="difficulty"
                        class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 bg-white transition-colors">
                        <option value="">جميع المستويات</option>
                        <option value="سهل"   {{ ($reviewFilters['difficulty'] ?? '') === 'سهل'   ? 'selected' : '' }}>سهل</option>
                        <option value="متوسط" {{ ($reviewFilters['difficulty'] ?? '') === 'متوسط' ? 'selected' : '' }}>متوسط</option>
                        <option value="صعب"   {{ ($reviewFilters['difficulty'] ?? '') === 'صعب'   ? 'selected' : '' }}>صعب</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ $reviewFilters['date_from'] ?? '' }}"
                        class="w-full px-3 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ $reviewFilters['date_to'] ?? '' }}"
                        class="w-full px-3 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 transition-colors">
                </div>
            </div>
            <div class="flex gap-2 mt-4 pt-4 border-t border-slate-100">
                <button type="submit"
                    class="flex items-center gap-1.5 text-white text-sm font-bold px-5 py-2.5 rounded-2xl transition-all hover:opacity-90"
                    style="background: linear-gradient(135deg, #2691C2, #22334A);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    بحث
                </button>
                @if(array_filter($reviewFilters))
                <a href="{{ route('admin.campaigns.show', $campaign) }}"
                   class="flex items-center gap-1.5 bg-white border-2 border-slate-200 text-slate-600 text-sm font-bold px-5 py-2.5 rounded-2xl transition-all hover:border-slate-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    إعادة تعيين
                </a>
                @endif
            </div>
        </form>
        @endif

        {{-- Reviews list --}}
        @if($reviews->isEmpty())
            @if(array_filter($reviewFilters))
            <div class="bg-white rounded-3xl p-14 shadow-sm border border-slate-100 text-center">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                    <svg class="w-8 h-8" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                </div>
                <p class="text-slate-700 font-bold mb-2">لا توجد نتائج مطابقة</p>
                <p class="text-slate-400 text-sm mb-5">جرب تعديل معايير البحث أو</p>
                <a href="{{ route('admin.campaigns.show', $campaign) }}"
                   class="inline-flex items-center gap-2 text-white text-sm font-bold px-6 py-3 rounded-2xl hover:opacity-90"
                   style="background: linear-gradient(135deg, #2691C2, #22334A);">
                    إعادة تعيين الفلاتر
                </a>
            </div>
            @else
            <div class="bg-white rounded-3xl p-14 shadow-sm border border-slate-100 text-center">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background: linear-gradient(135deg, #ffedd5, #fed7aa);">
                    <svg class="w-8 h-8" style="color:#EC943C;" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                </div>
                <p class="text-slate-600 font-bold mb-1">لا توجد تقييمات بعد</p>
                <p class="text-slate-400 text-sm">شارك رابط الحملة مع الطلاب لبدء استقبال التقييمات</p>
            </div>
            @endif
        @else
        <div class="space-y-4">
            @foreach($reviews as $review)
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-5 sm:px-7 py-5 border-b border-slate-50 flex items-center justify-between flex-wrap gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 font-bold text-white text-sm"
                             style="background: linear-gradient(135deg, #2691C2, #22334A);">
                            {{ mb_substr($review->student_name, 0, 1) }}
                        </div>
                        <div>
                            <p class="font-bold text-slate-900 text-sm">{{ $review->student_name }}</p>
                            <p class="text-xs text-slate-400 break-all">{{ $review->email ?? 'بدون بريد إلكتروني' }} · {{ $review->created_at->format('d/m/Y') }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <div class="flex gap-0.5">
                            @for($i = 0; $i < 5; $i++)
                            <span class="text-lg {{ $i < $review->overall_rating ? 'text-yellow-400' : 'text-slate-200' }}">★</span>
                            @endfor
                        </div>
                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold
                            {{ $review->difficulty_level === 'سهل' ? 'bg-emerald-100 text-emerald-700' : ($review->difficulty_level === 'متوسط' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700') }}">
                            {{ $review->difficulty_level }}
                        </span>
                        <span class="px-3 py-1.5 rounded-xl text-xs font-bold {{ $review->would_recommend ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                            {{ $review->would_recommend ? '👍 يوصي' : '👎 لا يوصي' }}
                        </span>
                    </div>
                </div>
                <div class="p-5 sm:p-7">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
                        @foreach([
                            ['label' => 'الكلي',   'val' => $review->overall_rating,       'bg' => 'bg-sky-50'],
                            ['label' => 'المحتوى', 'val' => $review->course_content_rating, 'bg' => 'bg-orange-50'],
                            ['label' => 'المدرب',  'val' => $review->instructor_rating,     'bg' => 'bg-green-50'],
                            ['label' => 'التنظيم', 'val' => $review->organization_rating,   'bg' => 'bg-violet-50'],
                        ] as $r)
                        <div class="rounded-2xl p-4 {{ $r['bg'] }}">
                            <p class="text-xs font-bold text-slate-500 mb-1.5">{{ $r['label'] }}</p>
                            <div class="flex gap-0.5">
                                @for($i = 0; $i < 5; $i++)
                                <span class="text-sm {{ $i < $r['val'] ? 'text-yellow-400' : 'text-slate-200' }}">★</span>
                                @endfor
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-bold text-slate-400 mb-2">ما أعجبه</p>
                            <div class="bg-slate-50 rounded-2xl p-4">
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $review->what_did_you_like }}</p>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-400 mb-2">اقتراحات التحسين</p>
                            <div class="bg-slate-50 rounded-2xl p-4">
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $review->what_can_be_improved }}</p>
                            </div>
                        </div>
                        @if($review->additional_notes)
                        <div class="lg:col-span-2">
                            <p class="text-xs font-bold text-slate-400 mb-2">ملاحظات إضافية</p>
                            <div class="bg-slate-50 rounded-2xl p-4">
                                <p class="text-sm text-slate-700 leading-relaxed">{{ $review->additional_notes }}</p>
                            </div>
                        </div>
                        @endif
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
