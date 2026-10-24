<?php

namespace App\Providers;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Every authenticated account is an admin in this system, so exporting
        // follows the existing auth guard. Personal data can be narrowed further
        // via EXPORT_PII_ALLOWED_EMAILS.
        Gate::define('export-reviews', fn (User $user) => true);
        Gate::define('export-personal-data', function (User $user) {
            $allowed = config('exports.pii_allowed_emails', []);

            return empty($allowed) || in_array(strtolower($user->email), array_map('strtolower', $allowed), true);
        });

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
