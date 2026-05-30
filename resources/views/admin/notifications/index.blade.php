@extends('layouts.admin')

@section('title', 'الإشعارات')

@section('breadcrumbs')
<svg class="w-4 h-4 text-slate-600 mx-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
</svg>
<span class="text-slate-300 text-sm">الإشعارات</span>
@endsection

@section('hero')
<div class="px-4 sm:px-6 py-8 sm:py-10" style="background: linear-gradient(135deg, #22334A 0%, #1a2a3e 100%);">
    <div class="max-w-7xl mx-auto flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-1">الإشعارات</h1>
            <p class="text-slate-400 text-sm">جميع إشعارات النظام الإداري</p>
        </div>
    </div>
</div>
@endsection

@section('content')
<main class="max-w-4xl mx-auto px-4 sm:px-6 py-8">

    @if($notifications->isEmpty())
    <div class="bg-white rounded-3xl p-16 shadow-sm border border-slate-100 text-center">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4"
             style="background: linear-gradient(135deg, #dbeafe, #bfdbfe);">
            <svg class="w-8 h-8" style="color:#2691C2;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
            </svg>
        </div>
        <p class="text-slate-700 font-bold mb-1">لا توجد إشعارات حالياً</p>
        <p class="text-slate-400 text-sm">ستظهر الإشعارات هنا عند وصول تقييمات جديدة</p>
    </div>
    @else
    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <h2 class="text-base font-bold text-slate-900">جميع الإشعارات</h2>
            <span class="text-xs text-slate-500 bg-slate-100 rounded-full px-3 py-1 font-semibold">
                {{ $notifications->total() }} إشعار
            </span>
        </div>
        <div class="divide-y divide-slate-50">
            @foreach($notifications as $notif)
            @php
                $isRead   = $notif->read_at !== null;
                $iconBg   = $notif->type === 'review' ? 'linear-gradient(135deg,#2691C2,#22334A)' : 'linear-gradient(135deg,#EC943C,#d4782a)';
            @endphp
            <div class="flex items-start gap-4 px-6 py-4 {{ !$isRead ? 'bg-sky-50/40' : '' }} hover:bg-slate-50/50 transition-colors">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shrink-0 mt-0.5"
                     style="background: {{ $iconBg }};">
                    @if($notif->type === 'review')
                    <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    @else
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-bold text-slate-900 text-sm">{{ $notif->title }}</p>
                            <p class="text-sm text-slate-600 mt-0.5 leading-relaxed">{{ $notif->message }}</p>
                            @if($notif->data && isset($notif->data['student_name']))
                            <p class="text-xs text-slate-400 mt-1">
                                الطالب: {{ $notif->data['student_name'] }}
                                @if(isset($notif->data['rating']))
                                · التقييم: {{ $notif->data['rating'] }}/5
                                @endif
                            </p>
                            @endif
                        </div>
                        <div class="shrink-0 text-left flex flex-col items-end gap-2">
                            @if(!$isRead)
                            <span class="w-2.5 h-2.5 rounded-full" style="background: #EC943C;"></span>
                            @endif
                            <p class="text-xs text-slate-400 whitespace-nowrap">{{ $notif->created_at->diffForHumans() }}</p>
                        </div>
                    </div>
                    @if($notif->data && isset($notif->data['campaign_id']))
                    <a href="{{ route('admin.campaigns.show', $notif->data['campaign_id']) }}"
                       class="inline-flex items-center gap-1 text-xs font-bold mt-2 transition-colors"
                       style="color: #2691C2;">
                        عرض الحملة
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @if($notifications->hasPages())
        <div class="border-t border-slate-100 px-6 py-4">
            {{ $notifications->links('pagination::tailwind') }}
        </div>
        @endif
    </div>
    @endif

</main>
@endsection
