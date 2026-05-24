@extends('layouts.admin')

@section('title', 'لوحة التحكم')

@section('breadcrumbs')
@endsection

@section('nav-actions')
<a href="{{ route('admin.campaigns.create') }}"
   class="flex items-center gap-1.5 text-white text-xs font-bold px-4 py-2.5 rounded-xl transition-all hover:opacity-90 shadow-lg whitespace-nowrap"
   style="background: linear-gradient(135deg, #EC943C, #d4782a);">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
    </svg>
    <span class="hidden sm:inline">حملة جديدة</span>
    <span class="sm:hidden">جديد</span>
</a>
@endsection

@section('hero')
<div class="px-4 sm:px-6 py-8 sm:py-10" style="background: linear-gradient(135deg, #22334A 0%, #1a2a3e 100%);">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-1">لوحة التحكم الإدارية</h1>
        <p class="text-slate-400 text-sm">إدارة ومتابعة حملات تقييمات دورات EMC</p>
    </div>
</div>
@endsection

@section('content')
<main class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">

    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 sm:gap-5 mb-8">
        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                <svg class="w-7 h-7" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </div>
            <div>
                <p class="text-3xl font-extrabold text-slate-900">{{ $totalCampaigns }}</p>
                <p class="text-sm text-slate-500 font-medium mt-0.5">إجمالي الحملات</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #ffedd5, #fed7aa);">
                <svg class="w-7 h-7" style="color:#EC943C;" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                </svg>
            </div>
            <div>
                <p class="text-3xl font-extrabold text-slate-900">{{ $totalReviews }}</p>
                <p class="text-sm text-slate-500 font-medium mt-0.5">إجمالي التقييمات</p>
            </div>
        </div>

        <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 flex items-center gap-5">
            <div class="w-14 h-14 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #d1fae5, #a7f3d0);">
                <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <div class="flex items-baseline gap-1">
                    <p class="text-3xl font-extrabold text-slate-900">{{ $averageRating }}</p>
                    <span class="text-yellow-400">★</span>
                    <span class="text-slate-400 text-sm">/ 5</span>
                </div>
                <p class="text-sm text-slate-500 font-medium mt-0.5">متوسط التقييم</p>
            </div>
        </div>
    </div>

    {{-- Campaign Filters --}}
    <form method="GET" action="{{ route('admin.dashboard') }}"
          class="bg-white rounded-3xl shadow-sm border border-slate-100 p-5 sm:p-6 mb-6">
        <div class="flex items-center gap-2 mb-4">
            <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/>
            </svg>
            <span class="text-sm font-bold text-slate-700">تصفية الحملات</span>
            @if(array_filter($filters))
            <span class="text-xs font-bold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">مُفعّل</span>
            @endif
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5">بحث عن دورة</label>
                <input type="text" name="course_name" value="{{ $filters['course_name'] ?? '' }}"
                    placeholder="اسم الدورة..."
                    class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 placeholder-slate-400 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5">اسم المدرب</label>
                <input type="text" name="instructor_name" value="{{ $filters['instructor_name'] ?? '' }}"
                    placeholder="اسم المدرب..."
                    class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 placeholder-slate-400 transition-colors">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5">الحالة</label>
                <select name="status"
                    class="w-full px-3.5 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 bg-white transition-colors">
                    <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>جميع الحالات</option>
                    <option value="active"   {{ ($filters['status'] ?? '') === 'active'   ? 'selected' : '' }}>نشطة</option>
                    <option value="inactive" {{ ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' }}>متوقفة</option>
                    <option value="expired"  {{ ($filters['status'] ?? '') === 'expired'  ? 'selected' : '' }}>منتهية</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"
                        class="w-full px-3 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 transition-colors">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1.5">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"
                        class="w-full px-3 py-2.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] text-sm text-slate-900 transition-colors">
                </div>
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
            @if(array_filter($filters))
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-1.5 bg-white border-2 border-slate-200 text-slate-600 text-sm font-bold px-5 py-2.5 rounded-2xl transition-all hover:border-slate-300">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                إعادة تعيين
            </a>
            @endif
        </div>
    </form>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 sm:gap-8 mb-10">

        {{-- Campaigns Table --}}
        <div class="xl:col-span-2">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 sm:px-8 py-5 border-b border-slate-100 flex items-center justify-between gap-3">
                    <h2 class="text-lg font-bold text-slate-900">الحملات</h2>
                    @if($campaigns->isNotEmpty())
                    <span class="text-xs text-slate-500 bg-slate-100 rounded-full px-3 py-1 font-semibold shrink-0">{{ $campaigns->count() }} حملة</span>
                    @endif
                </div>

                @if($campaigns->isEmpty())
                <div class="px-6 py-16 text-center">
                    <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4" style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
                        <svg class="w-8 h-8" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <p class="text-slate-700 font-bold mb-2">لا توجد حملات بعد</p>
                    <p class="text-slate-400 text-sm mb-6">ابدأ بإنشاء حملة تقييم لدورتك الأولى</p>
                    <a href="{{ route('admin.campaigns.create') }}"
                       class="inline-flex items-center gap-2 text-white text-sm font-bold px-6 py-3 rounded-2xl transition-all hover:opacity-90"
                       style="background: linear-gradient(135deg, #2691C2, #22334A);">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
                        </svg>
                        إنشاء حملة
                    </a>
                </div>
                @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-130">
                        <thead>
                            <tr class="border-b border-slate-100 bg-slate-50/60">
                                <th class="px-6 sm:px-8 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">الدورة</th>
                                <th class="px-4 py-4 text-center text-xs font-bold text-slate-500">التقييمات</th>
                                <th class="px-4 py-4 text-center text-xs font-bold text-slate-500">المتوسط</th>
                                <th class="px-4 py-4 text-center text-xs font-bold text-slate-500">الحالة</th>
                                <th class="px-4 py-4 text-center text-xs font-bold text-slate-500">إجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            @foreach($campaigns as $campaign)
                            @php $badge = $campaign->statusBadge(); @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors cursor-pointer group"
                                onclick="window.location='{{ route('admin.campaigns.show', $campaign) }}'">
                                <td class="px-6 sm:px-8 py-5">
                                    <p class="font-bold text-slate-900 text-sm group-hover:text-[#2691C2] transition-colors leading-tight">{{ $campaign->course_name }}</p>
                                    @if($campaign->instructor_name)
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $campaign->instructor_name }}</p>
                                    @endif
                                    @if($campaign->expires_at)
                                    <p class="text-xs text-slate-400 mt-0.5">
                                        {{ $campaign->isExpired() ? 'انتهت: ' : 'تنتهي: ' }}{{ $campaign->expires_at->format('d/m/Y') }}
                                    </p>
                                    @endif
                                </td>
                                <td class="px-4 py-5 text-center">
                                    <span class="text-sm font-bold text-slate-700">{{ $campaign->reviews_count }}</span>
                                </td>
                                <td class="px-4 py-5 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <span class="text-sm font-bold text-slate-700">{{ number_format($campaign->reviews_avg_overall_rating ?? 0, 1) }}</span>
                                        <span class="text-yellow-400 text-sm">★</span>
                                    </div>
                                </td>
                                <td class="px-4 py-5 text-center">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ $badge['class'] }}">
                                        {{ $badge['label'] }}
                                    </span>
                                </td>
                                <td class="px-4 py-5" onclick="event.stopPropagation()">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        <form method="POST" action="{{ route('admin.campaigns.toggle', $campaign) }}">
                                            @csrf @method('PATCH')
                                            <button type="submit"
                                                class="text-xs font-bold px-3 py-1.5 rounded-xl transition-all border whitespace-nowrap {{ $campaign->is_active ? 'bg-amber-50 text-amber-700 border-amber-200 hover:bg-amber-100' : 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100' }}">
                                                {{ $campaign->is_active ? 'إيقاف' : 'تفعيل' }}
                                            </button>
                                        </form>
                                        <a href="{{ route('admin.campaigns.edit', $campaign) }}"
                                           class="text-xs font-bold px-3 py-1.5 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-all whitespace-nowrap">
                                            تعديل
                                        </a>
                                        <form method="POST" action="{{ route('admin.campaigns.destroy', $campaign) }}"
                                              x-data
                                              @submit.prevent="if(confirm('هل أنت متأكد من حذف هذه الحملة وجميع تقييماتها؟')) $el.submit()">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="text-xs font-bold px-3 py-1.5 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 border border-red-200 transition-all">
                                                حذف
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
        <div class="xl:col-span-1">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100">
                    <h2 class="text-lg font-bold text-slate-900">آخر التقييمات</h2>
                </div>
                @if($latestReviews->isEmpty())
                <div class="px-6 py-12 text-center">
                    <p class="text-sm text-slate-400">لا توجد تقييمات بعد</p>
                </div>
                @else
                <div class="divide-y divide-slate-50">
                    @foreach($latestReviews as $review)
                    <div class="px-6 py-4 hover:bg-slate-50/50 transition-colors">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-slate-900 text-sm truncate">{{ $review->student_name }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $review->campaign->course_name }}</p>
                            </div>
                            <div class="shrink-0 text-left">
                                <div class="flex gap-0.5">
                                    @for($i = 0; $i < 5; $i++)
                                    <span class="text-sm {{ $i < $review->overall_rating ? 'text-yellow-400' : 'text-slate-200' }}">★</span>
                                    @endfor
                                </div>
                                <p class="text-xs text-slate-400 mt-0.5">{{ $review->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Analytics Charts --}}
    @if($totalReviews > 0)
    <div class="mb-8">
        <h2 class="text-xl font-extrabold text-slate-900 mb-5">التحليلات</h2>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Monthly Reviews --}}
            <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-100">
                <h3 class="text-base font-bold text-slate-900 mb-5">التقييمات شهرياً</h3>
                <div class="relative" style="height: 220px;">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>

            {{-- Recommendation Donut --}}
            <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-100">
                <h3 class="text-base font-bold text-slate-900 mb-5">نسبة التوصية</h3>
                <div class="relative" style="height: 180px;">
                    <canvas id="recommendChart"></canvas>
                </div>
                <div class="flex justify-center gap-6 mt-4">
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-emerald-400"></span>
                        <span class="text-xs text-slate-500">يوصي ({{ $recommendYes }})</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-3 h-3 rounded-full bg-red-400"></span>
                        <span class="text-xs text-slate-500">لا يوصي ({{ $recommendNo }})</span>
                    </div>
                </div>
            </div>

            {{-- Difficulty Distribution --}}
            <div class="bg-white rounded-3xl p-6 sm:p-7 shadow-sm border border-slate-100">
                <h3 class="text-base font-bold text-slate-900 mb-5">مستوى الصعوبة</h3>
                <div class="relative" style="height: 200px;">
                    <canvas id="difficultyChart"></canvas>
                </div>
            </div>

        </div>
    </div>

    {{-- Chart data passed via JSON script block (avoids IDE JS-linter false positives) --}}
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
        Chart.defaults.color = '#64748b';

        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: cd.monthlyLabels,
                datasets: [{
                    label: 'التقييمات',
                    data: cd.monthlyValues,
                    backgroundColor: 'rgba(38, 145, 194, 0.18)',
                    borderColor: '#2691C2',
                    borderWidth: 2,
                    borderRadius: 8,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
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
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '65%',
                plugins: { legend: { display: false } }
            }
        });

        var diffLabels = Object.keys(cd.difficultyData);
        var diffValues = Object.values(cd.difficultyData);
        var diffColors = diffLabels.map(function(l) {
            return l === 'سهل' ? '#34d399' : l === 'متوسط' ? '#fbbf24' : '#f87171';
        });

        new Chart(document.getElementById('difficultyChart'), {
            type: 'doughnut',
            data: {
                labels: diffLabels,
                datasets: [{
                    data: diffValues,
                    backgroundColor: diffColors,
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '50%',
                plugins: { legend: { position: 'bottom', labels: { padding: 16, boxWidth: 12 } } }
            }
        });
    })();
    </script>
    @endif

</main>
@endsection
