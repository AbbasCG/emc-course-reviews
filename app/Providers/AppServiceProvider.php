<?php

namespace App\Providers;

use App\Models\AdminNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        View::composer('layouts.admin', function ($view) {
            if (Auth::check()) {
                $adminNotifications = AdminNotification::latest()->take(10)->get();
                $adminUnreadCount   = AdminNotification::whereNull('read_at')->count();
            } else {
                $adminNotifications = collect();
                $adminUnreadCount   = 0;
            }

            $view->with(compact('adminNotifications', 'adminUnreadCount'));
        });
    }
}
