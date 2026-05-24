<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول — EMC</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/logo/emclogo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>html, body { overflow-x: hidden; }</style>
</head>
<body class="antialiased" style="background: linear-gradient(160deg, #0F172A 0%, #22334A 60%, #1e3a5f 100%); min-height: 100vh;">
<div class="min-h-screen flex items-center justify-center px-4 py-12">

    <div class="w-full max-w-md">

        {{-- Brand --}}
        <div class="text-center mb-8">
            <img src="{{ asset('assets/logo/emclogo.png') }}" alt="EMC" class="h-16 w-auto object-contain mx-auto mb-4">
            <h1 class="text-2xl font-extrabold text-white">لوحة التحكم</h1>
            <p class="text-slate-400 text-sm mt-1">سجّل دخولك للمتابعة</p>
        </div>

        {{-- Login Card --}}
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden">
            <div class="h-1.5" style="background: linear-gradient(90deg, #2691C2, #EC943C);"></div>

            <div class="p-8">
                <form method="POST" action="{{ route('login.post') }}" x-data="{ showPass: false }">
                    @csrf

                    {{-- Email --}}
                    <div class="mb-5">
                        <label for="email" class="block text-sm font-bold text-slate-700 mb-2">
                            البريد الإلكتروني
                        </label>
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="{{ old('email') }}"
                            placeholder="admin@emc.com"
                            autocomplete="email"
                            autofocus
                            class="w-full px-4 py-3.5 rounded-2xl border-2 focus:outline-none transition-colors text-slate-900 placeholder-slate-400 {{ $errors->has('email') ? 'border-red-400 bg-red-50' : 'border-slate-200 focus:border-[#2691C2]' }}"
                        >
                        @error('email')
                        <p class="text-red-500 text-xs mt-1.5 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Password --}}
                    <div class="mb-5">
                        <label for="password" class="block text-sm font-bold text-slate-700 mb-2">
                            كلمة المرور
                        </label>
                        <div class="relative">
                            <input
                                :type="showPass ? 'text' : 'password'"
                                id="password"
                                name="password"
                                placeholder="••••••••"
                                autocomplete="current-password"
                                class="w-full px-4 py-3.5 pe-12 rounded-2xl border-2 border-slate-200 focus:outline-none focus:border-[#2691C2] transition-colors text-slate-900 placeholder-slate-400 {{ $errors->has('password') ? 'border-red-400 bg-red-50' : '' }}"
                            >
                            <button type="button" @click="showPass = !showPass"
                                class="absolute inset-y-0 left-0 px-4 flex items-center text-slate-400 hover:text-slate-600 transition-colors">
                                <svg x-show="!showPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg x-show="showPass" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                        @error('password')
                        <p class="text-red-500 text-xs mt-1.5 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Remember Me --}}
                    <div class="flex items-center gap-2 mb-7">
                        <input type="checkbox" id="remember" name="remember"
                            class="w-4 h-4 rounded accent-[#2691C2] cursor-pointer">
                        <label for="remember" class="text-sm text-slate-600 cursor-pointer select-none">
                            تذكّرني
                        </label>
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                        class="w-full flex items-center justify-center gap-2 text-white font-bold py-4 rounded-2xl transition-all hover:opacity-90 shadow-lg active:scale-95"
                        style="background: linear-gradient(135deg, #2691C2, #22334A);">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"/>
                        </svg>
                        تسجيل الدخول
                    </button>
                </form>
            </div>
        </div>

        <p class="text-center text-slate-500 text-xs mt-6">
            EMC Education Platform &copy; {{ date('Y') }}
        </p>
    </div>
</div>
</body>
</html>
