@extends('layouts.app')

@section('title', 'تم إرسال التقييم — EMC')

@section('content')
<style>
    @keyframes checkDraw {
        from { stroke-dashoffset: 100; }
        to   { stroke-dashoffset: 0; }
    }
    @keyframes ringPulse {
        0%   { transform: scale(0.8); opacity: 0; }
        50%  { transform: scale(1.05); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .ring-animate  { animation: ringPulse 0.6s ease forwards; }
    .check-animate {
        stroke-dasharray: 100;
        stroke-dashoffset: 100;
        animation: checkDraw 0.5s ease 0.5s forwards;
    }
    .fade-up-1 { animation: fadeUp 0.5s ease 0.7s both; }
    .fade-up-2 { animation: fadeUp 0.5s ease 0.9s both; }
    .fade-up-3 { animation: fadeUp 0.5s ease 1.1s both; }
</style>

<div class="min-h-screen flex items-center justify-center px-4 py-12"
     style="background: linear-gradient(160deg, #0F172A 0%, #22334A 60%, #1e3a5f 100%);">
    <div class="max-w-md w-full">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">

            <div class="h-2" style="background: linear-gradient(90deg, #2691C2, #EC943C);"></div>

            <div class="p-10 sm:p-12 text-center">

                {{-- Animated checkmark --}}
                <div class="flex justify-center mb-8">
                    <div class="ring-animate w-24 h-24 rounded-full flex items-center justify-center"
                         style="background: linear-gradient(135deg, #d1fae5, #a7f3d0);">
                        <svg class="w-12 h-12" viewBox="0 0 48 48" fill="none">
                            <path class="check-animate" d="M10 24l10 10L38 14"
                                  stroke="#16a34a" stroke-width="3.5"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                </div>

                {{-- Message --}}
                <div class="fade-up-1 mb-6">
                    <h1 class="text-2xl font-extrabold text-slate-900 mb-2">تم إرسال تقييمك بنجاح</h1>
                    <p class="text-slate-500 text-sm leading-relaxed">
                        شكرًا لمشاركتك رأيك، ملاحظاتك تساعدنا<br>على تطوير برامج EMC.
                    </p>
                </div>

                {{-- Course Info --}}
                <div class="fade-up-2 rounded-2xl p-5 mb-6 border" style="background: #eff9ff; border-color: #bfdbfe;">
                    <p class="text-xs font-bold uppercase tracking-wider mb-1" style="color: #2691C2;">الدورة</p>
                    <p class="font-bold text-slate-900 text-base">{{ $campaign->course_name }}</p>
                    @if($campaign->instructor_name)
                    <p class="text-xs text-slate-500 mt-0.5">{{ $campaign->instructor_name }}</p>
                    @endif
                </div>

                {{-- EMC Logo --}}
                <div class="fade-up-3 border-t border-slate-100 pt-6">
                    <img src="{{ asset('assets/logo/emclogo.png') }}" alt="EMC"
                         class="h-10 w-auto object-contain mx-auto mb-2">
                    <p class="text-xs text-slate-400">EMC Education Platform</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
<script>
(function () {
    function fire(particleRatio, opts) {
        confetti(Object.assign({
            origin: { y: 0.6 },
            disableForReducedMotion: true,
        }, opts, { particleCount: Math.floor(200 * particleRatio) }));
    }
    setTimeout(function () {
        fire(0.25, { spread: 26, startVelocity: 55 });
        fire(0.20, { spread: 60 });
        fire(0.35, { spread: 100, decay: 0.91, scalar: 0.8 });
        fire(0.10, { spread: 120, startVelocity: 25, decay: 0.92, scalar: 1.2 });
        fire(0.10, { spread: 120, startVelocity: 45 });
    }, 700);
})();
</script>
@endsection
