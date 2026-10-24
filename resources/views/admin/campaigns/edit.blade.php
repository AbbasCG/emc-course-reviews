@extends('layouts.admin')

@section('title', 'تعديل — ' . $campaign->course_name)

@section('breadcrumbs')
<svg class="w-4 h-4 text-slate-600 mx-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
<a href="{{ route('admin.campaigns.show', $campaign) }}" class="text-slate-400 text-sm hover:text-white transition-colors truncate max-w-32 sm:max-w-48">{{ $campaign->course_name }}</a>
<svg class="w-4 h-4 text-slate-600 mx-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
<span class="text-slate-300 text-sm shrink-0">تعديل</span>
@endsection

@section('hero')
<div class="px-4 sm:px-6 py-8 sm:py-10" style="background: linear-gradient(135deg, #22334A 0%, #1a2a3e 100%);">
    <div class="max-w-3xl mx-auto">
        <a href="{{ route('admin.campaigns.show', $campaign) }}"
           class="inline-flex items-center gap-1.5 text-white/60 hover:text-white text-sm font-semibold mb-4 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            رجوع
        </a>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-1">تعديل الحملة</h1>
        <p class="text-slate-400 text-sm">{{ $campaign->course_name }}</p>
    </div>
</div>
@endsection

@section('content')
<main class="max-w-3xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">

        <div class="px-6 sm:px-8 py-6 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #EC943C, #d4782a);">
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-slate-900">تعديل بيانات الدورة</h2>
                <p class="text-xs text-slate-500">حدّث تفاصيل الحملة ثم احفظ التغييرات</p>
            </div>
        </div>

        @php
            $currentExpiresType = 'none';
            if ($campaign->expires_at) {
                $currentExpiresType = 'custom';
            }
        @endphp

        <form action="{{ route('admin.campaigns.update', $campaign) }}" method="POST"
              class="p-6 sm:p-8 space-y-6"
              x-data="{ expiresType: '{{ old('expires_type', $currentExpiresType) }}' }">
            @csrf
            @method('PUT')

            {{-- Course Name --}}
            <div>
                <label for="course_name" class="block text-sm font-bold text-slate-700 mb-2">
                    اسم الدورة <span class="text-red-500">*</span>
                </label>
                <input type="text" id="course_name" name="course_name"
                    value="{{ old('course_name', $campaign->course_name) }}"
                    placeholder="مثال: تطوير الويب بـ Laravel"
                    class="w-full px-4 py-3.5 rounded-2xl border-2 focus:outline-none transition-colors text-slate-900 placeholder-slate-400 {{ $errors->has('course_name') ? 'border-red-400 bg-red-50' : 'border-slate-200 focus:border-[#2691C2]' }}">
                @error('course_name')
                <p class="text-red-500 text-xs mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Instructor --}}
            <div>
                <label for="instructor_name" class="block text-sm font-bold text-slate-700 mb-2">
                    اسم المدرب <span class="text-slate-400 font-normal mr-1">(اختياري)</span>
                </label>
                <input type="text" id="instructor_name" name="instructor_name"
                    value="{{ old('instructor_name', $campaign->instructor_name) }}"
                    placeholder="مثال: أحمد محمد"
                    class="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900 placeholder-slate-400">
                @error('instructor_name')
                <p class="text-red-500 text-xs mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Activity Type --}}
            @php $__activityType = old('activity_type', array_key_exists($campaign->activity_type ?? 'course', \App\Models\ReviewCampaign::ACTIVITY_TYPES) ? ($campaign->activity_type ?? 'course') : 'other'); @endphp
            <div x-data="{ type: @js($__activityType) }">
                <label for="activity_type" class="block text-sm font-bold text-slate-700 mb-2">
                    نوع النشاط
                </label>
                <select id="activity_type" name="activity_type" x-model="type"
                    class="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900 bg-white">
                    @foreach(\App\Models\ReviewCampaign::ACTIVITY_TYPES as $key => $label)
                    <option value="{{ $key }}" @selected($__activityType === $key)>{{ $label }}</option>
                    @endforeach
                    <option value="other" @selected($__activityType === 'other')>نوع مخصص…</option>
                </select>
                <input type="text" name="activity_type_custom" x-show="type === 'other'" x-cloak
                    value="{{ old('activity_type_custom', array_key_exists($campaign->activity_type ?? 'course', \App\Models\ReviewCampaign::ACTIVITY_TYPES) ? '' : $campaign->activity_type) }}"
                    placeholder="مثال: ندوة، معسكر تدريبي"
                    class="w-full mt-2 px-4 py-3.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900 placeholder-slate-400">
                @error('activity_type_custom')
                <p class="text-red-500 text-xs mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>
            {{-- Course Date --}}
            <div>
                <label for="course_date" class="block text-sm font-bold text-slate-700 mb-2">
                    تاريخ الدورة <span class="text-slate-400 font-normal mr-1">(اختياري)</span>
                </label>
                <input type="date" id="course_date" name="course_date"
                    value="{{ old('course_date', $campaign->course_date?->format('Y-m-d')) }}"
                    class="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900">
                @error('course_date')
                <p class="text-red-500 text-xs mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Description --}}
            <div>
                <label for="description" class="block text-sm font-bold text-slate-700 mb-2">
                    وصف الدورة <span class="text-slate-400 font-normal mr-1">(اختياري)</span>
                </label>
                <textarea id="description" name="description" rows="3"
                    placeholder="أضف وصفاً مختصراً للدورة ومحتواها..."
                    class="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900 placeholder-slate-400 resize-none">{{ old('description', $campaign->description) }}</textarea>
                @error('description')
                <p class="text-red-500 text-xs mt-1.5 font-medium">{{ $message }}</p>
                @enderror
            </div>

            {{-- Expiration --}}
            <div class="rounded-2xl border-2 border-slate-100 p-5 space-y-4">
                <p class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    مدة صلاحية الرابط
                </p>
                @if($campaign->expires_at)
                <p class="text-xs text-amber-600 bg-amber-50 rounded-xl px-3 py-2">
                    الصلاحية الحالية: {{ $campaign->expires_at->format('d/m/Y') }}
                    {{ $campaign->isExpired() ? '(منتهية)' : '' }}
                </p>
                @endif
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach(['none' => 'بدون انتهاء', '7' => '٧ أيام', '30' => '٣٠ يومًا', 'custom' => 'تاريخ مخصص'] as $val => $label)
                    <button type="button" @click="expiresType = '{{ $val }}'"
                        class="py-2.5 rounded-xl border-2 text-sm font-bold transition-all"
                        :class="expiresType === '{{ $val }}' ? 'bg-[#2691C2]/10 border-[#2691C2] text-[#2691C2]' : 'bg-slate-50 border-slate-200 text-slate-600 hover:border-slate-300'">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
                <input type="hidden" name="expires_type" :value="expiresType">
                <div x-show="expiresType === 'custom'" x-transition>
                    <label class="block text-xs font-bold text-slate-500 mb-2">تاريخ الانتهاء</label>
                    <input type="date" name="expires_custom_date"
                        value="{{ old('expires_custom_date', $campaign->expires_at?->format('Y-m-d')) }}"
                        class="w-full sm:w-64 px-4 py-3 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900">
                </div>
            </div>

            {{-- Theme --}}
            <div class="rounded-2xl border-2 border-slate-100 p-5 space-y-4">
                <p class="text-sm font-bold text-slate-700 flex items-center gap-2">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
                    </svg>
                    النمط البصري للحملة
                </p>
                @php
                $themes = [
                    'default'  => ['label' => 'EMC الافتراضي', 'from' => '#0F172A', 'to' => '#22334A'],
                    'ai'       => ['label' => 'الذكاء الاصطناعي', 'from' => '#0a0a1a', 'to' => '#0d2040'],
                    'business' => ['label' => 'الأعمال', 'from' => '#1a0800', 'to' => '#4a1e00'],
                    'data'     => ['label' => 'البيانات', 'from' => '#0e0021', 'to' => '#2d0080'],
                ];
                $currentTheme = old('theme', $campaign->theme ?? 'default');
                @endphp
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach($themes as $key => $t)
                    <label class="cursor-pointer">
                        <input type="radio" name="theme" value="{{ $key }}" class="sr-only"
                            {{ $currentTheme === $key ? 'checked' : '' }}>
                        <div class="rounded-xl border-2 p-3 text-center transition-all hover:scale-105 theme-option {{ $currentTheme === $key ? 'border-[#2691C2] ring-2 ring-[#2691C2]/30' : 'border-slate-200' }}"
                             style="background: linear-gradient(135deg, {{ $t['from'] }}, {{ $t['to'] }});">
                            <p class="text-white text-xs font-bold">{{ $t['label'] }}</p>
                        </div>
                    </label>
                    @endforeach
                </div>
                <script>
                document.querySelectorAll('input[name="theme"]').forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        document.querySelectorAll('.theme-option').forEach(function(el) {
                            el.classList.remove('border-[#2691C2]', 'ring-2', 'ring-[#2691C2]/30');
                            el.classList.add('border-slate-200');
                        });
                        var opt = radio.closest('label').querySelector('.theme-option');
                        opt.classList.add('border-[#2691C2]', 'ring-2', 'ring-[#2691C2]/30');
                        opt.classList.remove('border-slate-200');
                    });
                });
                </script>
            </div>

            {{-- Actions --}}
            <div class="pt-2 border-t border-slate-100 flex flex-wrap gap-3">
                <button type="submit"
                    class="flex items-center gap-2 text-white font-bold px-8 py-3.5 rounded-2xl transition-all hover:opacity-90 shadow-lg"
                    style="background: linear-gradient(135deg, #EC943C, #d4782a);">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    حفظ التغييرات
                </button>
                <a href="{{ route('admin.campaigns.show', $campaign) }}"
                   class="flex items-center gap-2 bg-white border-2 border-slate-200 text-slate-700 font-bold px-8 py-3.5 rounded-2xl transition-all hover:border-slate-300">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</main>
@endsection
