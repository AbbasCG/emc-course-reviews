@extends('layouts.app')

@section('title', 'انتهت صلاحية الرابط — EMC')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 py-12"
     style="background: linear-gradient(160deg, #0F172A 0%, #22334A 60%, #1e3a5f 100%);">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

            <div class="h-2" style="background: linear-gradient(90deg, #6b7280, #9ca3af);"></div>

            <div class="p-10 sm:p-12 text-center">

                {{-- Icon --}}
                <div class="flex justify-center mb-8">
                    <div class="w-24 h-24 rounded-full flex items-center justify-center"
                         style="background: linear-gradient(135deg, #f1f5f9, #e2e8f0);">
                        <svg class="w-12 h-12 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>

                {{-- Message --}}
                <h1 class="text-2xl font-extrabold text-slate-900 mb-3">انتهت صلاحية رابط التقييم</h1>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                    يرجى التواصل مع إدارة EMC للحصول على رابط جديد.
                </p>

                @if(isset($campaign))
                <div class="rounded-2xl p-5 mb-6 border text-right" style="background: #f8fafc; border-color: #e2e8f0;">
                    <p class="text-xs font-bold uppercase tracking-wider mb-1 text-slate-400">الدورة</p>
                    <p class="font-bold text-slate-900 text-base">{{ $campaign->course_name }}</p>
                    @if($campaign->instructor_name)
                    <p class="text-xs text-slate-500 mt-0.5">{{ $campaign->instructor_name }}</p>
                    @endif
                    @if($campaign->expires_at)
                    <p class="text-xs text-slate-400 mt-2">انتهت الصلاحية: {{ $campaign->expires_at->format('d/m/Y') }}</p>
                    @endif
                </div>
                @endif

                {{-- EMC Brand --}}
                <div class="border-t border-slate-100 pt-6">
                    <img src="{{ asset('assets/logo/emclogo.png') }}" alt="EMC"
                         class="h-10 w-auto object-contain mx-auto mb-2">
                    <p class="text-xs text-slate-400">EMC Education Platform</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
