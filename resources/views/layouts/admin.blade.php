<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'لوحة التحكم') — EMC</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/logo/emclogo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { overflow-x: hidden; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
<div class="min-h-screen flex flex-col">

    {{-- Navbar --}}
    <header style="background: linear-gradient(135deg, #0F172A 0%, #22334A 100%);">
        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="flex flex-wrap items-center justify-between gap-y-2 py-3">

                {{-- Logo + Breadcrumbs --}}
                <div class="flex items-center gap-2 min-w-0 shrink">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 shrink-0">
                        <img src="{{ asset('assets/logo/emclogo.png') }}" alt="EMC" class="h-8 w-auto object-contain">
                        <div class="hidden sm:block">
                            <span class="text-white font-bold text-base leading-none">EMC</span>
                            <span class="text-slate-400 text-xs block leading-none">Course Reviews</span>
                        </div>
                    </a>
                    <div class="flex items-center gap-1 min-w-0 text-sm overflow-hidden">
                        @yield('breadcrumbs')
                    </div>
                </div>

                {{-- Actions + Logout --}}
                <div class="flex items-center gap-2 flex-wrap justify-end">
                    @yield('nav-actions')
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="flex items-center gap-1.5 text-slate-400 hover:text-white text-xs font-semibold px-3 py-2 rounded-xl hover:bg-white/10 transition-all whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="hidden sm:inline">خروج</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </header>

    {{-- Page Hero --}}
    @yield('hero')

    {{-- Flash Messages --}}
    @if(session('success') || session('error'))
    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 pt-5">
        @if(session('success'))
        <div class="bg-emerald-50 border border-emerald-200 rounded-2xl px-5 py-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            <p class="text-emerald-700 font-semibold text-sm">{{ session('success') }}</p>
        </div>
        @endif
        @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-2xl px-5 py-4 flex items-center gap-3 mt-3">
            <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-red-700 font-semibold text-sm">{{ session('error') }}</p>
        </div>
        @endif
    </div>
    @endif

    {{-- Page Content --}}
    @yield('content')

</div>
</body>
</html>
