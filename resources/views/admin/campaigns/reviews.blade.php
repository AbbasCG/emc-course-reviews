@extends('layouts.admin')

@section('title', 'التقييمات — ' . $campaign->course_name)

@section('breadcrumbs')
<svg class="w-4 h-4 text-slate-600 mx-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
<a href="{{ route('admin.campaigns.show', $campaign) }}" class="text-slate-400 text-sm hover:text-white transition-colors truncate max-w-36">{{ $campaign->course_name }}</a>
<svg class="w-4 h-4 text-slate-600 mx-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
<span class="text-slate-300 text-sm">التقييمات</span>
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
<a href="{{ route('admin.campaigns.exportPdf', $campaign) }}"
   class="nav-btn"
   style="background:linear-gradient(135deg,#22334A,#1a2840);color:#fff;border-color:rgba(34,51,74,0.5);">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
    </svg>
    <span class="hidden sm:inline">تصدير PDF</span>
</a>
<a href="{{ route('admin.campaigns.export-csv', $campaign) }}"
   class="nav-btn"
   style="background:linear-gradient(135deg,#EC943C,#d4782a);color:#fff;border-color:rgba(236,148,60,0.4);">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
    <span class="hidden sm:inline">تصدير CSV</span>
</a>
@endsection

@section('hero')
<div class="px-4 sm:px-6 py-8 sm:py-10" style="background: linear-gradient(135deg, #22334A 0%, #1a2a3e 100%);">
    <div class="max-w-7xl mx-auto">
        <a href="{{ route('admin.campaigns.show', $campaign) }}"
           class="inline-flex items-center gap-1.5 text-white/60 hover:text-white text-sm font-semibold mb-4 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            رجوع للحملة
        </a>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-1">{{ $campaign->course_name }}</h1>
        <p class="text-slate-400 text-sm">{{ $reviews->total() }} تقييم</p>
    </div>
</div>
@endsection

@section('content')
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">

    {{-- Collapsible Filters --}}
    @php $activeFilterCount = count(array_filter($reviewFilters)); @endphp
    <div x-data="{ open: {{ $activeFilterCount > 0 ? 'true' : 'false' }} }"
         class="bg-white rounded-3xl shadow-sm border border-slate-100 mb-6 overflow-hidden">

        {{-- Toggle header --}}
        <button type="button" @click="open = !open"
                class="w-full flex items-center justify-between gap-3 px-5 sm:px-6 py-4 text-right hover:bg-slate-50 transition-colors">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                     style="background: linear-gradient(135deg, #2691C2, #22334A);">
                    <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
                    </svg>
                </div>
                <span class="font-bold text-slate-800 text-sm">تصفية التقييمات</span>
                @if($activeFilterCount > 0)
                <span class="inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold text-white"
                      style="background: #2691C2;">{{ $activeFilterCount }}</span>
                @endif
            </div>
            <svg class="w-4 h-4 text-slate-400 transition-transform duration-200 shrink-0"
                 :class="open ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
        </button>

        {{-- Filter form --}}
        <form method="GET" action="{{ route('admin.campaigns.reviews', $campaign) }}"
              x-show="open"
              x-transition:enter="transition ease-out duration-200"
              x-transition:enter-start="opacity-0 -translate-y-2"
              x-transition:enter-end="opacity-100 translate-y-0"
              x-transition:leave="transition ease-in duration-150"
              x-transition:leave-start="opacity-100 translate-y-0"
              x-transition:leave-end="opacity-0 -translate-y-2"
              class="border-t border-slate-100 px-5 sm:px-6 pb-5 sm:pb-6 pt-5"
              style="display: none;">
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
                <a href="{{ route('admin.campaigns.reviews', $campaign) }}"
                   class="flex items-center gap-1.5 bg-white border-2 border-slate-200 text-slate-600 text-sm font-bold px-5 py-2.5 rounded-2xl transition-all hover:border-slate-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    إعادة تعيين
                </a>
            </div>
        </form>
    </div>

    {{-- Reviews --}}
    @if($reviews->isEmpty())
    <div class="bg-white rounded-3xl p-16 shadow-sm border border-slate-100 text-center">
        <div class="w-20 h-20 rounded-3xl flex items-center justify-center mx-auto mb-6" style="background: linear-gradient(135deg, #ffedd5, #fed7aa);">
            <svg class="w-10 h-10" style="color: #EC943C;" fill="currentColor" viewBox="0 0 20 20">
                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
            </svg>
        </div>
        @if(array_filter($reviewFilters))
        <p class="text-lg font-bold text-slate-700 mb-2">لا توجد نتائج مطابقة</p>
        <p class="text-slate-400 text-sm mb-6">جرب تعديل معايير البحث</p>
        <a href="{{ route('admin.campaigns.reviews', $campaign) }}"
           class="inline-flex items-center gap-2 text-white text-sm font-bold px-6 py-3 rounded-2xl hover:opacity-90"
           style="background: linear-gradient(135deg, #2691C2, #22334A);">
            إعادة تعيين الفلاتر
        </a>
        @else
        <p class="text-lg font-bold text-slate-700 mb-2">لا توجد تقييمات بعد</p>
        <p class="text-slate-400 text-sm mb-6">شارك رابط الحملة مع الطلاب لبدء استقبال التقييمات</p>
        <a href="{{ route('admin.campaigns.show', $campaign) }}"
           class="inline-flex items-center gap-2 text-white text-sm font-bold px-6 py-3 rounded-2xl transition-all hover:opacity-90"
           style="background: linear-gradient(135deg, #2691C2, #22334A);">
            عرض رابط الحملة
        </a>
        @endif
    </div>
    @else
    <div class="space-y-5">
        @foreach($reviews as $review)
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">

            <div class="px-5 sm:px-7 py-5 border-b border-slate-50 flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <div class="w-11 h-11 rounded-2xl flex items-center justify-center shrink-0 font-bold text-white text-sm"
                         style="background: linear-gradient(135deg, #2691C2, #22334A);">
                        {{ mb_substr($review->student_name, 0, 1) }}
                    </div>
                    <div>
                        <p class="font-bold text-slate-900">{{ $review->student_name }}</p>
                        <p class="text-xs text-slate-400">{{ $review->email ?? 'بدون بريد إلكتروني' }} · {{ $review->created_at->format('d/m/Y') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 flex-wrap">
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
                        {{ $review->would_recommend ? '👍 يوصي بها' : '👎 لا يوصي' }}
                    </span>
                </div>
            </div>

            <div class="p-5 sm:p-7">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                    @foreach([
                        ['label' => 'التقييم الكلي', 'val' => $review->overall_rating,       'bg' => 'bg-sky-50'],
                        ['label' => 'المحتوى',        'val' => $review->course_content_rating, 'bg' => 'bg-orange-50'],
                        ['label' => 'المدرب',         'val' => $review->instructor_rating,     'bg' => 'bg-green-50'],
                        ['label' => 'التنظيم',        'val' => $review->organization_rating,   'bg' => 'bg-violet-50'],
                    ] as $r)
                    <div class="rounded-2xl p-4 {{ $r['bg'] }}">
                        <p class="text-xs font-bold text-slate-500 mb-2">{{ $r['label'] }}</p>
                        <div class="flex items-center gap-0.5">
                            @for($i = 0; $i < 5; $i++)
                            <span class="text-base {{ $i < $r['val'] ? 'text-yellow-400' : 'text-slate-200' }}">★</span>
                            @endfor
                        </div>
                        <p class="text-xs font-bold mt-1 text-slate-600">{{ $r['val'] }}/5</p>
                    </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-bold text-slate-500 mb-2 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-emerald-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            ما أعجبه
                        </p>
                        <div class="bg-slate-50 rounded-2xl p-4">
                            <p class="text-sm text-slate-700 leading-relaxed">{{ $review->what_did_you_like }}</p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-500 mb-2 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            اقتراحات التحسين
                        </p>
                        <div class="bg-slate-50 rounded-2xl p-4">
                            <p class="text-sm text-slate-700 leading-relaxed">{{ $review->what_can_be_improved }}</p>
                        </div>
                    </div>
                    @if($review->additional_notes)
                    <div class="lg:col-span-2">
                        <p class="text-xs font-bold text-slate-500 mb-2">ملاحظات إضافية</p>
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

    <div class="mt-8">
        <a href="{{ route('admin.campaigns.show', $campaign) }}"
           class="inline-flex items-center gap-2 bg-white border-2 border-slate-200 text-slate-700 font-bold px-6 py-3 rounded-2xl transition-all hover:border-slate-300">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            العودة للحملة
        </a>
    </div>
</main>
@endsection
