<?php

namespace App\Providers;

use App\Models\AdminNotification;
use App\Models\User;
use App\Support\RequestStats;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One counter object per request (config/monitoring.php).
        $this->app->scoped(RequestStats::class);
    }

    public function boot(): void
    {
        // Cheap per-query counters for the request monitor: one closure call per query, SQL templates of slow
        // queries only (never bindings). Skipped entirely when monitoring is off.
        if (config('monitoring.enabled')) {
            DB::listen(function (QueryExecuted $query) {
                app(RequestStats::class)->record($query->sql, $query->time, (float) config('monitoring.slow_query_ms'));
            });
        }

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
