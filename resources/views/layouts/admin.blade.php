<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    {{-- FOUC prevention: apply dark class synchronously before any render --}}
    <script>
    (function(){if(localStorage.getItem('theme')!=='light'){document.documentElement.classList.add('dark');}})();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'لوحة التحكم') — EMC</title>
    <link rel="icon" type="image/png" href="{{ asset('assets/logo/emclogo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { overflow-x: hidden; }
        [x-cloak] { display: none !important; }

        /* ─── CSS Variable Theme System ─── */
        :root {
            --pg-bg:           #f1f5f9;
            --pg-card:         #ffffff;
            --pg-border:       rgba(0,0,0,0.07);
            --pg-border-2:     #e2e8f0;
            --pg-text:         #0f172a;
            --pg-text-2:       #334155;
            --pg-text-3:       #64748b;
            --pg-text-4:       #94a3b8;
            --pg-input-bg:     #ffffff;
            --pg-input-border: #cbd5e1;
        }
        html.dark {
            --pg-bg:           #070d1a;
            --pg-card:         #0c1627;
            --pg-border:       rgba(38,145,194,0.13);
            --pg-border-2:     rgba(38,145,194,0.25);
            --pg-text:         #e2e8f0;
            --pg-text-2:       #94a3b8;
            --pg-text-3:       #64748b;
            --pg-text-4:       #334155;
            --pg-input-bg:     rgba(255,255,255,0.04);
            --pg-input-border: rgba(38,145,194,0.2);
        }

        /* ─── Body theme transition ─── */
        body {
            background: var(--pg-bg) !important;
            color: var(--pg-text);
            transition: background-color 0.25s ease, color 0.2s ease;
        }

        /* ─── Global dark-mode overrides for Tailwind classes ─── */
        html.dark .bg-white            { background: var(--pg-card)  !important; }
        html.dark .bg-slate-50         { background: var(--pg-bg)    !important; }
        html.dark .bg-slate-100        { background: rgba(255,255,255,0.03) !important; }
        html.dark .bg-slate-50\/60     { background: rgba(255,255,255,0.02) !important; }

        html.dark .border-slate-100,
        html.dark .border-slate-200    { border-color: var(--pg-border) !important; }

        html.dark .text-slate-900,
        html.dark .text-slate-800      { color: var(--pg-text)  !important; }
        html.dark .text-slate-700,
        html.dark .text-slate-600      { color: var(--pg-text-2) !important; }
        html.dark .text-slate-500,
        html.dark .text-slate-400      { color: var(--pg-text-3) !important; }

        html.dark .shadow-sm           { box-shadow: 0 1px 3px rgba(0,0,0,0.35) !important; }

        /* Form inputs */
        html.dark input[type=text],
        html.dark input[type=email],
        html.dark input[type=date],
        html.dark input[type=search],
        html.dark select,
        html.dark textarea {
            background:   var(--pg-input-bg)     !important;
            border-color: var(--pg-input-border)  !important;
            color:        var(--pg-text)           !important;
        }
        html.dark input::placeholder,
        html.dark textarea::placeholder { color: var(--pg-text-4) !important; }

        /* Flash messages */
        html.dark .bg-emerald-50   { background: rgba(16,185,129,0.1)  !important; }
        html.dark .border-emerald-200 { border-color: rgba(16,185,129,0.2) !important; }
        html.dark .text-emerald-700   { color: #34d399 !important; }
        html.dark .bg-red-50       { background: rgba(239,68,68,0.1)   !important; }
        html.dark .border-red-200  { border-color: rgba(239,68,68,0.2)  !important; }
        html.dark .text-red-700    { color: #f87171 !important; }

        /* Rating mini cards */
        html.dark .bg-sky-50,
        html.dark .bg-sky-50\/60   { background: rgba(14,165,233,0.07) !important; }
        html.dark .border-sky-100  { border-color: rgba(14,165,233,0.15) !important; }
        html.dark .bg-orange-50    { background: rgba(234,88,12,0.07)   !important; }
        html.dark .border-orange-100 { border-color: rgba(234,88,12,0.15) !important; }
        html.dark .bg-amber-50,
        html.dark .bg-amber-50\/60,
        html.dark .bg-amber-50\/70 { background: rgba(245,158,11,0.07) !important; }
        html.dark .border-amber-100 { border-color: rgba(245,158,11,0.15) !important; }
        html.dark .bg-emerald-50\/60,
        html.dark .bg-emerald-50\/70 { background: rgba(16,185,129,0.07) !important; }
        html.dark .border-emerald-100 { border-color: rgba(16,185,129,0.15) !important; }
        html.dark .bg-violet-50    { background: rgba(139,92,246,0.07)  !important; }
        html.dark .border-violet-100 { border-color: rgba(139,92,246,0.15) !important; }
        html.dark .bg-green-50     { background: rgba(34,197,94,0.07)   !important; }

        /* Status badges */
        html.dark .bg-emerald-100  { background: rgba(16,185,129,0.12) !important; }
        html.dark .text-emerald-700 { color: #34d399 !important; }
        html.dark .text-emerald-600 { color: #34d399 !important; }
        html.dark .bg-amber-100    { background: rgba(245,158,11,0.12) !important; }
        html.dark .text-amber-700  { color: #fbbf24 !important; }
        html.dark .bg-red-100      { background: rgba(239,68,68,0.12)  !important; }
        html.dark .text-red-600,
        html.dark .text-red-700    { color: #f87171 !important; }

        /* Danger (delete) buttons */
        html.dark .bg-red-50       { background: rgba(239,68,68,0.08)  !important; }
        html.dark .border-red-200  { border-color: rgba(239,68,68,0.2)  !important; }
        html.dark .hover\:bg-red-100:hover { background: rgba(239,68,68,0.18) !important; }

        /* Table row hover */
        html.dark .hover\:bg-slate-50\/70:hover { background: rgba(255,255,255,0.03) !important; }

        /* Dividers */
        html.dark .divide-slate-50 > * + *,
        html.dark .divide-y > * + *    { border-color: rgba(255,255,255,0.04) !important; }

        /* Hover states on list/sidebar items */
        html.dark .hover\:bg-slate-50\/50:hover { background: rgba(255,255,255,0.02) !important; }

        /* Link card with dashed orange border */
        html.dark .border-dashed { border-color: rgba(236,148,60,0.2) !important; }

        /* ─── Smooth transitions for all themed elements ─── */
        .bg-white, .bg-slate-50, .bg-slate-100, .bg-slate-50\/60,
        input, select, textarea,
        .border-slate-100, .border-slate-200,
        .text-slate-900, .text-slate-800, .text-slate-700, .text-slate-600,
        .text-slate-500, .text-slate-400,
        .rounded-3xl, .rounded-2xl, .rounded-xl {
            transition:
                background-color 0.25s ease,
                color 0.2s ease,
                border-color 0.2s ease,
                box-shadow 0.2s ease;
        }

        /* ─── Navbar action buttons (always on dark navbar) ─── */
        .nav-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.45rem 0.75rem;
            border-radius: 0.75rem;
            border: 1px solid rgba(255,255,255,0.1);
            background: rgba(255,255,255,0.07);
            color: #cbd5e1;
            transition: all 0.15s ease;
            white-space: nowrap;
            cursor: pointer;
            text-decoration: none;
        }
        .nav-btn:hover { background: rgba(255,255,255,0.13); color: #fff; border-color: rgba(255,255,255,0.18); }
        .nav-btn:disabled { opacity: 0.55; cursor: default; }
        .nav-btn-primary {
            background: linear-gradient(135deg, #EC943C, #c9680d);
            color: #fff;
            border-color: transparent;
        }
        .nav-btn-primary:hover { opacity: 0.9; background: linear-gradient(135deg, #EC943C, #c9680d); color: #fff; }

        /* ─── Notification dropdown item hover ─── */
        .notif-item { transition: background 0.13s; text-decoration: none; display: flex; }
        .notif-item.notif-unread { background: rgba(38,145,194,0.04); }

        /* Dark mode hover */
        html.dark .notif-item:hover { background: rgba(255,255,255,0.04) !important; }
        html.dark .notif-item.notif-unread:hover { background: rgba(38,145,194,0.1) !important; }

        /* Light mode hover */
        html:not(.dark) .notif-item:hover { background: #f8fafc !important; }
        html:not(.dark) .notif-item.notif-unread { background: rgba(38,145,194,0.03); }
        html:not(.dark) .notif-item.notif-unread:hover { background: rgba(38,145,194,0.07) !important; }

        /* ─── Notification dropdown container ─── */
        .notif-dropdown {
            position: absolute;
            inset-inline-end: 0;
            top: calc(100% + 0.5rem);
            width: 25rem;
            max-width: calc(100vw - 1.5rem);
            z-index: 50;
            overflow: hidden;
            border-radius: 1.25rem;
            border: 1px solid var(--pg-border-2);
        }
        html.dark .notif-dropdown {
            background: #0e1c30;
            box-shadow: 0 16px 48px rgba(0,0,0,0.4), 0 4px 16px rgba(0,0,0,0.25);
        }
        html:not(.dark) .notif-dropdown {
            background: #ffffff;
            border-color: #e2e8f0;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1), 0 2px 8px rgba(0,0,0,0.05);
        }
    </style>
    @stack('head')
</head>

{{-- Body carries the Alpine theme controller --}}
<body class="antialiased"
      x-data="{
          dark: localStorage.getItem('theme') !== 'light',
          toggleTheme() {
              this.dark = !this.dark;
              document.documentElement.classList.toggle('dark', this.dark);
              localStorage.setItem('theme', this.dark ? 'dark' : 'light');
          }
      }"
      x-init="document.documentElement.classList.toggle('dark', dark)">

<div class="min-h-screen flex flex-col">

    {{-- Navbar (always dark — brand element) --}}
    <header style="background: linear-gradient(135deg, #0F172A 0%, #22334A 100%); box-shadow: 0 2px 20px rgba(0,0,0,0.4);">
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

                {{-- Actions + Theme + Notifications + Logout --}}
                <div class="flex items-center gap-2 sm:gap-3 flex-wrap justify-end">

                    {{-- Page-specific action buttons (sync, create, etc.) --}}
                    <div class="flex items-center gap-2">
                        @yield('nav-actions')
                    </div>

                    {{-- ── Separator ── --}}
                    <div class="w-px h-5 rounded-full shrink-0" style="background:rgba(255,255,255,0.12);"></div>

                    {{-- ── Theme Toggle ── --}}
                    <button type="button"
                            @click="toggleTheme()"
                            class="relative flex items-center justify-center w-8 h-8 rounded-lg transition-all"
                            style="color:#94a3b8;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.05);"
                            onmouseover="this.style.background='rgba(255,255,255,0.12)';this.style.color='#fff';"
                            onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.color='#94a3b8';"
                            :title="dark ? 'التبديل إلى الوضع الفاتح' : 'التبديل إلى الوضع الليلي'">
                        {{-- Moon: shown in light mode --}}
                        <svg x-show="!dark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                        {{-- Sun: shown in dark mode --}}
                        <svg x-show="dark" style="display:none;" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </button>

                    {{-- ── Notification Bell ── --}}
                    <div x-data="{
                            open: false,
                            unread: {{ $adminUnreadCount }},
                            markAll() {
                                fetch('{{ route('admin.notifications.read-all') }}', {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
                                }).then(() => { this.unread = 0; });
                            }
                         }" class="relative">

                        <button type="button" @click="open = !open"
                                class="relative flex items-center justify-center w-8 h-8 rounded-lg transition-all"
                                style="color:#94a3b8;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.05);"
                                onmouseover="this.style.background='rgba(255,255,255,0.12)';this.style.color='#fff';"
                                onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.color='#94a3b8';">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span x-show="unread > 0" style="display:none;"
                                  x-text="unread > 9 ? '9+' : unread"
                                  class="absolute -top-1 -end-1 min-w-[1.1rem] h-[1.1rem] px-0.5 rounded-full flex items-center justify-center text-white text-[9px] font-extrabold leading-none"
                                  style="background:#EC943C;"></span>
                        </button>

                        {{-- Notification Dropdown --}}
                        <div x-show="open" @click.outside="open = false"
                             x-transition:enter="transition ease-out duration-200"
                             x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-150"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                             class="notif-dropdown"
                             style="display:none;">

                            {{-- ── Header ── --}}
                            <div class="flex items-center justify-between px-5 py-4"
                                 style="border-bottom:1px solid var(--pg-border);">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-xl flex items-center justify-center shrink-0"
                                         style="background:rgba(38,145,194,0.12);border:1px solid rgba(38,145,194,0.2);">
                                        <svg class="w-4 h-4" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-extrabold leading-tight" style="color:var(--pg-text);">الإشعارات</h3>
                                        <p class="text-[11px] leading-none mt-0.5" style="color:var(--pg-text-4);"
                                           x-show="unread > 0" style="display:none;"
                                           x-text="unread + ' غير مقروءة'"></p>
                                    </div>
                                    <span x-show="unread > 0" style="display:none;" x-text="unread"
                                          class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full text-[10px] font-extrabold text-white"
                                          style="background:#EC943C;"></span>
                                </div>
                                <button type="button" @click="markAll()"
                                        x-show="unread > 0" style="display:none;"
                                        class="text-xs font-semibold px-2.5 py-1 rounded-lg transition-all"
                                        style="color:#2691C2;border:1px solid rgba(38,145,194,0.2);background:rgba(38,145,194,0.07);">
                                    تعيين كمقروء
                                </button>
                            </div>

                            @if($adminNotifications->isEmpty())
                            {{-- ── Empty state ── --}}
                            <div class="px-5 py-14 text-center">
                                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mx-auto mb-4"
                                     style="background:var(--pg-border);border:1px solid var(--pg-border-2);">
                                    <svg class="w-6 h-6" style="color:var(--pg-text-4);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                                    </svg>
                                </div>
                                <p class="text-sm font-bold mb-1" style="color:var(--pg-text-2);">لا توجد إشعارات حالياً</p>
                                <p class="text-xs" style="color:var(--pg-text-4);">ستظهر هنا عند وصول تقييمات جديدة</p>
                            </div>

                            @else
                            {{-- ── Notification list ── --}}
                            <div class="overflow-y-auto" style="max-height:360px;scrollbar-width:thin;">
                                @foreach($adminNotifications as $notif)
                                @php $nRead = $notif->read_at !== null; @endphp
                                <a href="{{ route('admin.notifications.open', $notif) }}"
                                   @click="unread = Math.max(0, unread - {{ $nRead ? 0 : 1 }}); open = false;"
                                   class="notif-item {{ !$nRead ? 'notif-unread' : '' }} items-start gap-3.5 px-5 py-4"
                                   style="border-bottom:1px solid var(--pg-border);">

                                    {{-- ── Icon circle ── --}}
                                    <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center mt-0.5"
                                         style="background:{{ $notif->type === 'review'
                                             ? 'linear-gradient(135deg,#2691C2,#22334A)'
                                             : 'linear-gradient(135deg,#EC943C,#c9680d)' }};">
                                        @if($notif->type === 'review')
                                        <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                        </svg>
                                        @else
                                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                        </svg>
                                        @endif
                                    </div>

                                    {{-- ── Text content ── --}}
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-start justify-between gap-2 mb-1">
                                            <p class="text-sm font-bold leading-snug break-words" style="color:var(--pg-text);">{{ $notif->title }}</p>
                                            @if(!$nRead)
                                            <span class="w-2 h-2 rounded-full mt-1 flex-shrink-0" style="background:#EC943C;"></span>
                                            @endif
                                        </div>
                                        @if($notif->message)
                                        <p class="text-xs leading-relaxed break-words" style="color:var(--pg-text-3);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">{{ $notif->message }}</p>
                                        @endif
                                        <p class="text-[11px] mt-1.5 font-medium" style="color:var(--pg-text-4);">{{ $notif->created_at->diffForHumans() }}</p>
                                    </div>

                                </a>
                                @endforeach
                            </div>

                            {{-- ── Footer ── --}}
                            <div class="flex items-center justify-between px-5 py-3.5"
                                 style="border-top:1px solid var(--pg-border);">
                                <a href="{{ route('admin.notifications.index') }}" @click="open = false"
                                   class="text-xs font-bold transition-colors"
                                   style="color:#2691C2;">
                                    عرض جميع الإشعارات
                                </a>
                                <svg class="w-3.5 h-3.5" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                </svg>
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Logout --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                                class="flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg transition-all whitespace-nowrap"
                                style="color:#94a3b8;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.05);"
                                onmouseover="this.style.background='rgba(239,68,68,0.12)';this.style.color='#f87171';this.style.borderColor='rgba(239,68,68,0.2)';"
                                onmouseout="this.style.background='rgba(255,255,255,0.05)';this.style.color='#94a3b8';this.style.borderColor='rgba(255,255,255,0.08)';">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                            </svg>
                            <span class="hidden sm:inline">خروج</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
    </header>

    {{-- Thin separator between navbar and hero --}}
    <div style="height:1px;background:linear-gradient(90deg,transparent,rgba(38,145,194,0.25),transparent);opacity:0.7;"></div>

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

{{-- Sync Toast --}}
@if(request('synced'))
<div x-data="{ show: true }"
     x-init="
         setTimeout(() => show = false, 3500);
         const _su = new URL(window.location.href);
         _su.searchParams.delete('synced');
         history.replaceState({}, '', _su.toString());
     "
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-4"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-4"
     class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 flex items-center gap-3 text-sm font-bold px-5 py-3.5 rounded-2xl shadow-2xl whitespace-nowrap border border-slate-700"
     style="background: #0F172A;">
    <span class="w-6 h-6 rounded-full bg-emerald-500 flex items-center justify-center shrink-0">
        <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
        </svg>
    </span>
    <span class="text-white">تم تحديث البيانات بنجاح</span>
    <button @click="show = false" class="text-slate-500 hover:text-slate-300 transition-colors mr-1">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    </button>
</div>
@endif

</body>
</html>
