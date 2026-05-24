@extends('layouts.app')

@section('title', 'الحملة غير متاحة — EMC')

@section('content')
<div class="min-h-screen flex items-center justify-center px-4 py-12"
     style="background: linear-gradient(160deg, #0F172A 0%, #22334A 60%, #1e3a5f 100%);">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

            <div class="h-2" style="background: linear-gradient(90deg, #ef4444, #f97316);"></div>

            <div class="p-10 sm:p-12 text-center">

                {{-- Icon --}}
                <div class="flex justify-center mb-8">
                    <div class="w-24 h-24 rounded-full flex items-center justify-center"
                         style="background: linear-gradient(135deg, #fee2e2, #fecaca);">
                        <svg class="w-12 h-12 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                </div>

                {{-- Message --}}
                <h1 class="text-2xl font-extrabold text-slate-900 mb-3">الحملة غير متاحة</h1>
                <p class="text-slate-500 text-sm leading-relaxed mb-6">
                    عذراً، هذه الحملة غير متاحة حالياً.<br>
                    يرجى التواصل مع الإدارة للمزيد من المعلومات.
                </p>

                @if(isset($campaign))
                <div class="rounded-2xl p-5 mb-6 border" style="background: #fff7ed; border-color: #fed7aa;">
                    <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: #EC943C;">الدورة</p>
                    <p class="font-bold text-slate-900 text-base">{{ $campaign->course_name }}</p>
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
