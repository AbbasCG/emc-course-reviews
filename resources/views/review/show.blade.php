@extends('layouts.app')

@section('title', 'تقييم دورة - ' . $campaign->course_name)

@php
$themeGradients = [
    'default'  => 'linear-gradient(160deg, #0F172A 0%, #22334A 60%, #1e3a5f 100%)',
    'ai'       => 'linear-gradient(160deg, #020617 0%, #0d1b3e 60%, #0a3060 100%)',
    'business' => 'linear-gradient(160deg, #1a0800 0%, #2d1400 60%, #4a2000 100%)',
    'data'     => 'linear-gradient(160deg, #0e0021 0%, #1a0050 60%, #2a0070 100%)',
];
$bg = $themeGradients[$campaign->theme ?? 'default'] ?? $themeGradients['default'];
@endphp

@section('content')
<div class="min-h-screen py-8 px-4 sm:px-6" style="background: {{ $bg }};">

    {{-- Course Badge --}}
    <div class="max-w-2xl mx-auto mb-6 text-center">
        <div class="inline-flex items-center gap-3 bg-white/10 backdrop-blur-sm rounded-2xl px-5 py-3 border border-white/15 mb-5">
            <img src="{{ asset('assets/logo/emclogo.png') }}" alt="EMC" class="h-7 w-auto object-contain">
            <span class="text-white/80 font-semibold text-sm">EMC Education</span>
        </div>
        <h1 class="text-2xl font-extrabold text-white leading-tight">{{ $campaign->course_name }}</h1>
        @if($campaign->instructor_name)
        <p class="text-blue-300 text-sm mt-1">{{ $campaign->instructor_name }}</p>
        @endif
    </div>

    <div x-data="reviewForm()" class="max-w-2xl mx-auto">

        {{-- Progress Stepper --}}
        <div class="bg-white/8 backdrop-blur-sm rounded-3xl p-5 mb-5 border border-white/10">
            <div class="flex items-center">
                @php
                    $steps = [
                        ['num' => 1, 'label' => 'بياناتك'],
                        ['num' => 2, 'label' => 'التقييم'],
                        ['num' => 3, 'label' => 'الملاحظات'],
                        ['num' => 4, 'label' => 'المراجعة'],
                    ];
                @endphp
                @foreach($steps as $step)
                @if(!$loop->first)
                <div class="flex-1 h-0.5 mx-1 rounded-full transition-all duration-500"
                     :class="currentStep > {{ $step['num'] - 1 }} ? 'opacity-100' : 'opacity-20'"
                     :style="currentStep > {{ $step['num'] - 1 }} ? 'background: #2691C2;' : 'background: white;'">
                </div>
                @endif
                <div class="flex flex-col items-center" style="min-width: 48px;">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-bold text-sm transition-all duration-300 border-2"
                         :class="currentStep > {{ $step['num'] }} ? 'border-transparent' : (currentStep === {{ $step['num'] }} ? 'border-transparent' : 'border-white/20 bg-white/5 text-white/40')"
                         :style="currentStep >= {{ $step['num'] }} ? 'background: linear-gradient(135deg, #2691C2, #22334A); border-color: transparent; color: white;' : ''">
                        <template x-if="currentStep > {{ $step['num'] }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                            </svg>
                        </template>
                        <template x-if="currentStep <= {{ $step['num'] }}">
                            <span>{{ $step['num'] }}</span>
                        </template>
                    </div>
                    <p class="text-xs mt-1.5 font-semibold transition-colors duration-300"
                       :class="currentStep >= {{ $step['num'] }} ? 'text-white' : 'text-white/30'">
                        {{ $step['label'] }}
                    </p>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Form --}}
        <form @submit.prevent="submitReview">
            <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

                {{-- Global Error --}}
                <div x-show="globalError" x-transition class="bg-red-50 border-b border-red-200 px-8 py-4">
                    <p class="text-red-700 font-semibold text-sm" x-text="globalError"></p>
                </div>

                <div class="p-8">

                    {{-- STEP 1: Student Info --}}
                    <div x-show="currentStep === 1"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-2"
                         x-transition:enter-end="opacity-100 translate-x-0">

                        <div class="flex items-center gap-3 mb-7">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #2691C2, #22334A);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-extrabold text-slate-900">بياناتك</h2>
                                <p class="text-xs text-slate-400">أدخل معلوماتك الأساسية للمتابعة</p>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">
                                    الاسم الكامل <span class="text-red-500">*</span>
                                </label>
                                <input type="text" x-model="form.student_name"
                                    placeholder="أدخل اسمك الكامل"
                                    class="w-full px-4 py-3.5 rounded-2xl border-2 focus:outline-none transition-colors text-slate-900 placeholder-slate-400"
                                    :class="errors.student_name ? 'border-red-400 bg-red-50' : 'border-slate-200 focus:border-[#2691C2]'">
                                <template x-if="errors.student_name">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.student_name[0]"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">
                                    البريد الإلكتروني
                                    <span class="font-normal text-slate-400 mr-1">(اختياري)</span>
                                </label>
                                <input type="email" x-model="form.email"
                                    placeholder="example@email.com"
                                    class="w-full px-4 py-3.5 rounded-2xl border-2 focus:outline-none transition-colors text-slate-900 placeholder-slate-400"
                                    :class="errors.email ? 'border-red-400 bg-red-50' : 'border-slate-200 focus:border-[#2691C2]'">
                                <template x-if="errors.email">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.email[0]"></p>
                                </template>
                                <p class="text-xs text-slate-400 mt-1.5">يُستخدم للتحقق من عدم التكرار فقط</p>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 2: Ratings --}}
                    <div x-show="currentStep === 2"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-2"
                         x-transition:enter-end="opacity-100 translate-x-0">

                        <div class="flex items-center gap-3 mb-7">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #EC943C, #d4782a);">
                                <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-extrabold text-slate-900">تقييم الدورة</h2>
                                <p class="text-xs text-slate-400">قيّم تجربتك في جوانب مختلفة</p>
                            </div>
                        </div>

                        <div class="space-y-7">
                            {{-- Overall Rating --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-sm font-bold text-slate-700">التقييم الكلي <span class="text-red-500">*</span></label>
                                    <span class="text-xs font-semibold px-3 py-1 rounded-full transition-all"
                                          :class="form.overall_rating ? 'bg-yellow-100 text-yellow-700' : 'text-slate-400'"
                                          x-text="form.overall_rating ? starLabel(form.overall_rating) : 'اختر تقييمك'"></span>
                                </div>
                                <div class="flex gap-2">
                                    <template x-for="i in [1,2,3,4,5]" :key="i">
                                        <button type="button"
                                            @click="form.overall_rating = i"
                                            @mouseenter="hoverRating.overall = i"
                                            @mouseleave="hoverRating.overall = 0"
                                            class="text-4xl transition-all duration-100 hover:scale-110 focus:outline-none leading-none"
                                            :class="(hoverRating.overall || form.overall_rating) >= i ? 'text-yellow-400' : 'text-slate-200'">★</button>
                                    </template>
                                </div>
                                <template x-if="errors.overall_rating">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.overall_rating[0]"></p>
                                </template>
                            </div>

                            {{-- Content Rating --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-sm font-bold text-slate-700">محتوى الدورة <span class="text-red-500">*</span></label>
                                    <span class="text-xs font-semibold px-3 py-1 rounded-full transition-all"
                                          :class="form.course_content_rating ? 'bg-yellow-100 text-yellow-700' : 'text-slate-400'"
                                          x-text="form.course_content_rating ? starLabel(form.course_content_rating) : 'اختر تقييمك'"></span>
                                </div>
                                <div class="flex gap-2">
                                    <template x-for="i in [1,2,3,4,5]" :key="i">
                                        <button type="button"
                                            @click="form.course_content_rating = i"
                                            @mouseenter="hoverRating.content = i"
                                            @mouseleave="hoverRating.content = 0"
                                            class="text-4xl transition-all duration-100 hover:scale-110 focus:outline-none leading-none"
                                            :class="(hoverRating.content || form.course_content_rating) >= i ? 'text-yellow-400' : 'text-slate-200'">★</button>
                                    </template>
                                </div>
                                <template x-if="errors.course_content_rating">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.course_content_rating[0]"></p>
                                </template>
                            </div>

                            {{-- Instructor Rating --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-sm font-bold text-slate-700">المدرب <span class="text-red-500">*</span></label>
                                    <span class="text-xs font-semibold px-3 py-1 rounded-full transition-all"
                                          :class="form.instructor_rating ? 'bg-yellow-100 text-yellow-700' : 'text-slate-400'"
                                          x-text="form.instructor_rating ? starLabel(form.instructor_rating) : 'اختر تقييمك'"></span>
                                </div>
                                <div class="flex gap-2">
                                    <template x-for="i in [1,2,3,4,5]" :key="i">
                                        <button type="button"
                                            @click="form.instructor_rating = i"
                                            @mouseenter="hoverRating.instructor = i"
                                            @mouseleave="hoverRating.instructor = 0"
                                            class="text-4xl transition-all duration-100 hover:scale-110 focus:outline-none leading-none"
                                            :class="(hoverRating.instructor || form.instructor_rating) >= i ? 'text-yellow-400' : 'text-slate-200'">★</button>
                                    </template>
                                </div>
                                <template x-if="errors.instructor_rating">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.instructor_rating[0]"></p>
                                </template>
                            </div>

                            {{-- Organization Rating --}}
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <label class="text-sm font-bold text-slate-700">التنظيم والإدارة <span class="text-red-500">*</span></label>
                                    <span class="text-xs font-semibold px-3 py-1 rounded-full transition-all"
                                          :class="form.organization_rating ? 'bg-yellow-100 text-yellow-700' : 'text-slate-400'"
                                          x-text="form.organization_rating ? starLabel(form.organization_rating) : 'اختر تقييمك'"></span>
                                </div>
                                <div class="flex gap-2">
                                    <template x-for="i in [1,2,3,4,5]" :key="i">
                                        <button type="button"
                                            @click="form.organization_rating = i"
                                            @mouseenter="hoverRating.org = i"
                                            @mouseleave="hoverRating.org = 0"
                                            class="text-4xl transition-all duration-100 hover:scale-110 focus:outline-none leading-none"
                                            :class="(hoverRating.org || form.organization_rating) >= i ? 'text-yellow-400' : 'text-slate-200'">★</button>
                                    </template>
                                </div>
                                <template x-if="errors.organization_rating">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.organization_rating[0]"></p>
                                </template>
                            </div>

                            {{-- Difficulty --}}
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-3">مستوى الصعوبة <span class="text-red-500">*</span></label>
                                <div class="grid grid-cols-3 gap-3">
                                    <button type="button" @click="form.difficulty_level = 'سهل'"
                                        class="py-3.5 rounded-2xl border-2 text-sm font-bold transition-all duration-200"
                                        :class="form.difficulty_level === 'سهل' ? 'bg-emerald-50 border-emerald-400 text-emerald-700 scale-105' : 'bg-slate-50 border-slate-200 text-slate-600 hover:border-slate-300'">
                                        سهل
                                    </button>
                                    <button type="button" @click="form.difficulty_level = 'متوسط'"
                                        class="py-3.5 rounded-2xl border-2 text-sm font-bold transition-all duration-200"
                                        :class="form.difficulty_level === 'متوسط' ? 'bg-amber-50 border-amber-400 text-amber-700 scale-105' : 'bg-slate-50 border-slate-200 text-slate-600 hover:border-slate-300'">
                                        متوسط
                                    </button>
                                    <button type="button" @click="form.difficulty_level = 'صعب'"
                                        class="py-3.5 rounded-2xl border-2 text-sm font-bold transition-all duration-200"
                                        :class="form.difficulty_level === 'صعب' ? 'bg-red-50 border-red-400 text-red-700 scale-105' : 'bg-slate-50 border-slate-200 text-slate-600 hover:border-slate-300'">
                                        صعب
                                    </button>
                                </div>
                                <template x-if="errors.difficulty_level">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.difficulty_level[0]"></p>
                                </template>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 3: Feedback --}}
                    <div x-show="currentStep === 3"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-2"
                         x-transition:enter-end="opacity-100 translate-x-0">

                        <div class="flex items-center gap-3 mb-7">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #22334A, #0F172A);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-extrabold text-slate-900">ملاحظاتك</h2>
                                <p class="text-xs text-slate-400">شاركنا رأيك بالتفصيل</p>
                            </div>
                        </div>

                        <div class="space-y-5">
                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">
                                    ما الذي أعجبك في الدورة؟ <span class="text-red-500">*</span>
                                </label>
                                <textarea x-model="form.what_did_you_like"
                                    placeholder="اكتب ما استمتعت به وأفادك..."
                                    rows="4"
                                    class="w-full px-4 py-3.5 rounded-2xl border-2 focus:outline-none transition-colors text-slate-900 placeholder-slate-400 resize-none"
                                    :class="errors.what_did_you_like ? 'border-red-400 bg-red-50' : 'border-slate-200 focus:border-[#2691C2]'"></textarea>
                                <template x-if="errors.what_did_you_like">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.what_did_you_like[0]"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">
                                    ما الذي يمكن تحسينه؟ <span class="text-red-500">*</span>
                                </label>
                                <textarea x-model="form.what_can_be_improved"
                                    placeholder="اكتب اقتراحاتك لتطوير الدورة..."
                                    rows="4"
                                    class="w-full px-4 py-3.5 rounded-2xl border-2 focus:outline-none transition-colors text-slate-900 placeholder-slate-400 resize-none"
                                    :class="errors.what_can_be_improved ? 'border-red-400 bg-red-50' : 'border-slate-200 focus:border-[#2691C2]'"></textarea>
                                <template x-if="errors.what_can_be_improved">
                                    <p class="text-red-500 text-xs mt-1.5 font-medium" x-text="errors.what_can_be_improved[0]"></p>
                                </template>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-3">
                                    هل توصي بهذه الدورة؟ <span class="text-red-500">*</span>
                                </label>
                                <div class="grid grid-cols-2 gap-4">
                                    <button type="button" @click="form.would_recommend = '1'"
                                        class="flex items-center justify-center gap-2 py-4 rounded-2xl border-2 font-bold text-sm transition-all duration-200"
                                        :class="form.would_recommend === '1' ? 'bg-emerald-50 border-emerald-400 text-emerald-700 scale-105' : 'bg-slate-50 border-slate-200 text-slate-600 hover:border-slate-300'">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/>
                                        </svg>
                                        نعم، أوصي بها
                                    </button>
                                    <button type="button" @click="form.would_recommend = '0'"
                                        class="flex items-center justify-center gap-2 py-4 rounded-2xl border-2 font-bold text-sm transition-all duration-200"
                                        :class="form.would_recommend === '0' ? 'bg-red-50 border-red-400 text-red-700 scale-105' : 'bg-slate-50 border-slate-200 text-slate-600 hover:border-slate-300'">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018a2 2 0 01.485.06l3.76.94m-7 10v5a2 2 0 002 2h.096c.5 0 .905-.405.905-.904 0-.715.211-1.413.608-2.008L17 13V4m-7 10h2m5-10h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/>
                                        </svg>
                                        لا
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-bold text-slate-700 mb-2">
                                    ملاحظات إضافية
                                    <span class="font-normal text-slate-400 mr-1">(اختياري)</span>
                                </label>
                                <textarea x-model="form.additional_notes"
                                    placeholder="أي تعليقات أخرى تريد مشاركتها..."
                                    rows="3"
                                    class="w-full px-4 py-3.5 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900 placeholder-slate-400 resize-none"></textarea>
                            </div>
                        </div>
                    </div>

                    {{-- STEP 4: Review & Submit --}}
                    <div x-show="currentStep === 4"
                         x-transition:enter="transition ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-x-2"
                         x-transition:enter-end="opacity-100 translate-x-0">

                        <div class="flex items-center gap-3 mb-7">
                            <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0" style="background: linear-gradient(135deg, #2691C2, #EC943C);">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-xl font-extrabold text-slate-900">مراجعة التقييم</h2>
                                <p class="text-xs text-slate-400">تحقق من بياناتك قبل الإرسال</p>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <div class="bg-slate-50 rounded-2xl p-5 space-y-3">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">بياناتك</p>
                                <div class="flex justify-between items-center">
                                    <span class="text-sm text-slate-500">الاسم</span>
                                    <span class="text-sm font-bold text-slate-900" x-text="form.student_name"></span>
                                </div>
                                <div class="flex justify-between items-center border-t border-slate-200 pt-3">
                                    <span class="text-sm text-slate-500">البريد الإلكتروني</span>
                                    <span class="text-sm font-bold text-slate-900" x-text="form.email || 'غير مُدخل'"></span>
                                </div>
                            </div>

                            <div class="bg-slate-50 rounded-2xl p-5">
                                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">التقييمات</p>
                                <div class="grid grid-cols-2 gap-3 mb-3">
                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">الكلي</p>
                                        <span class="text-yellow-400 text-base" x-text="'★'.repeat(form.overall_rating) + '☆'.repeat(5 - form.overall_rating)"></span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">المحتوى</p>
                                        <span class="text-yellow-400 text-base" x-text="'★'.repeat(form.course_content_rating) + '☆'.repeat(5 - form.course_content_rating)"></span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">المدرب</p>
                                        <span class="text-yellow-400 text-base" x-text="'★'.repeat(form.instructor_rating) + '☆'.repeat(5 - form.instructor_rating)"></span>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-400 mb-1">التنظيم</p>
                                        <span class="text-yellow-400 text-base" x-text="'★'.repeat(form.organization_rating) + '☆'.repeat(5 - form.organization_rating)"></span>
                                    </div>
                                </div>
                                <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
                                    <span class="text-sm text-slate-500">مستوى الصعوبة</span>
                                    <span class="text-sm font-bold text-slate-900" x-text="form.difficulty_level"></span>
                                </div>
                            </div>

                            <div class="bg-slate-50 rounded-2xl p-5 flex justify-between items-center">
                                <span class="text-sm text-slate-500">التوصية</span>
                                <span class="text-sm font-bold px-3 py-1 rounded-full"
                                      :class="form.would_recommend === '1' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'"
                                      x-text="form.would_recommend === '1' ? '👍 نعم، أوصي بها' : '👎 لا'">
                                </span>
                            </div>

                            <div class="rounded-2xl p-5 border" style="background: #eff9ff; border-color: #bfdbfe;">
                                <div class="flex items-start gap-2">
                                    <svg class="w-4 h-4 shrink-0 mt-0.5" style="color: #2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="text-xs text-slate-600 leading-relaxed">
                                        بالضغط على "إرسال التقييم" تؤكد أن ما أدخلته صحيح وتوافق على مشاركة رأيك مع فريق EMC.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Navigation --}}
                <div class="px-8 py-6 border-t border-slate-100 bg-slate-50/50 flex justify-between items-center">
                    <button type="button" @click="previousStep()"
                        x-show="currentStep > 1"
                        class="flex items-center gap-2 bg-white border-2 border-slate-200 text-slate-700 px-6 py-3 rounded-2xl font-bold text-sm transition-all hover:border-slate-300">
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                        السابق
                    </button>
                    <div x-show="currentStep === 1"></div>

                    <button type="button" @click="nextStep()"
                        x-show="currentStep < 4"
                        class="flex items-center gap-2 text-white px-8 py-3 rounded-2xl font-bold text-sm transition-all hover:opacity-90 shadow-lg"
                        style="background: linear-gradient(135deg, #2691C2, #22334A);">
                        التالي
                        <svg class="w-4 h-4 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>

                    <button type="submit"
                        x-show="currentStep === 4"
                        :disabled="submitting"
                        class="flex items-center gap-2 text-white px-8 py-3 rounded-2xl font-bold text-sm transition-all shadow-lg disabled:opacity-60 disabled:cursor-not-allowed"
                        style="background: linear-gradient(135deg, #EC943C, #d4782a);">
                        <span x-show="!submitting" class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                            إرسال التقييم
                        </span>
                        <span x-show="submitting" class="flex items-center gap-2">
                            <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                            جاري الإرسال...
                        </span>
                    </button>
                </div>
            </div>
        </form>

        {{-- Footer --}}
        <p class="text-center text-white/30 text-xs mt-6">EMC Education Platform · {{ $campaign->course_name }}</p>
    </div>
</div>

<script>
function reviewForm() {
    return {
        currentStep: 1,
        submitting: false,
        errors: {},
        globalError: '',
        hoverRating: { overall: 0, content: 0, instructor: 0, org: 0 },
        form: {
            student_name: '',
            email: '',
            overall_rating: 0,
            course_content_rating: 0,
            instructor_rating: 0,
            organization_rating: 0,
            difficulty_level: '',
            what_did_you_like: '',
            what_can_be_improved: '',
            would_recommend: '1',
            additional_notes: '',
        },

        starLabel(n) {
            return ['', 'ضعيف', 'مقبول', 'جيد', 'جيد جداً', 'ممتاز'][n] || '';
        },

        nextStep() {
            if (this.validateCurrentStep()) this.currentStep++;
        },

        previousStep() {
            if (this.currentStep > 1) this.currentStep--;
        },

        validateCurrentStep() {
            this.errors = {};

            if (this.currentStep === 1) {
                if (!this.form.student_name.trim()) {
                    this.errors.student_name = ['الاسم مطلوب'];
                    return false;
                }
                if (this.form.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.form.email)) {
                    this.errors.email = ['البريد الإلكتروني غير صحيح'];
                    return false;
                }
            }

            if (this.currentStep === 2) {
                if (!this.form.overall_rating) { this.errors.overall_rating = ['التقييم الكلي مطلوب']; return false; }
                if (!this.form.course_content_rating) { this.errors.course_content_rating = ['تقييم المحتوى مطلوب']; return false; }
                if (!this.form.instructor_rating) { this.errors.instructor_rating = ['تقييم المدرب مطلوب']; return false; }
                if (!this.form.organization_rating) { this.errors.organization_rating = ['تقييم التنظيم مطلوب']; return false; }
                if (!this.form.difficulty_level) { this.errors.difficulty_level = ['مستوى الصعوبة مطلوب']; return false; }
            }

            if (this.currentStep === 3) {
                if (!this.form.what_did_you_like.trim()) { this.errors.what_did_you_like = ['هذا الحقل مطلوب']; return false; }
                if (!this.form.what_can_be_improved.trim()) { this.errors.what_can_be_improved = ['هذا الحقل مطلوب']; return false; }
            }

            return true;
        },

        async submitReview() {
            if (!this.validateCurrentStep()) return;

            this.submitting = true;
            this.errors = {};
            this.globalError = '';

            const formData = new FormData();
            Object.entries(this.form).forEach(([key, val]) => formData.append(key, val));

            try {
                const response = await fetch('{{ route('review.store', $campaign->token) }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });

                if (response.ok) {
                    const data = await response.json();
                    window.location.href = data.redirect;
                } else if (response.status === 422) {
                    const data = await response.json();
                    this.errors = data.errors || {};
                    if (this.errors.student_name || this.errors.email) this.currentStep = 1;
                    else if (this.errors.overall_rating || this.errors.course_content_rating || this.errors.instructor_rating || this.errors.organization_rating || this.errors.difficulty_level) this.currentStep = 2;
                    else if (this.errors.what_did_you_like || this.errors.what_can_be_improved) this.currentStep = 3;
                    else this.globalError = 'يرجى التحقق من البيانات المدخلة.';
                } else if (response.status === 429) {
                    const data = await response.json().catch(() => ({}));
                    const wait = parseInt(response.headers.get('Retry-After') || data.retry_after || '0', 10);
                    this.globalError = (data.message || 'يرجى الانتظار قليلًا قبل المحاولة مرة أخرى.')
                        + (wait > 0 && wait <= 120 ? ' (' + wait + ' ثانية)' : '');
                } else if (response.status === 409) {
                    const data = await response.json().catch(() => ({}));
                    this.globalError = data.message || 'جارٍ معالجة تقييمك، يرجى الانتظار لحظات.';
                } else {
                    this.globalError = 'حدث خطأ في الإرسال. يرجى المحاولة مرة أخرى.';
                }
            } catch (e) {
                this.globalError = 'تعذّر الاتصال. يرجى التحقق من الإنترنت والمحاولة مجدداً.';
            } finally {
                this.submitting = false;
            }
        },
    };
}
</script>
@endsection
