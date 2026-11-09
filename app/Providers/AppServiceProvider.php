<?php

namespace App\Providers;

use App\Models\AdminNotification;
use App\Models\User;
use App\Support\ClientNetwork;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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

        $this->configureReviewRateLimiter();

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

    /**
     * Review submission limits (values in config/ratelimits.php):
     *  - per IP: wide ceiling, because a whole classroom can share one public IP;
     *  - per session: the strict anti-spam limit for a single browser/device.
     * The IP is Laravel's resolved one, i.e. X-Forwarded-For only counts when the
     * request really comes from a proxy listed in config/trustedproxy.php.
     */
    private function configureReviewRateLimiter(): void
    {
        RateLimiter::for('review-submit', function (Request $request) {
            $config = config('ratelimits.review_submit');

            $limits = [
                Limit::perMinute($config['per_ip'])->by('ip:' . ClientNetwork::bucket($request->ip())),
            ];

            if ($request->hasSession() && $request->session()->getId()) {
                $limits[] = Limit::perMinute($config['per_session'])->by('session:' . $request->session()->getId());
            }

            return collect($limits)->map(fn (Limit $limit) => $limit->response(
                fn (Request $request, array $headers) => response()->json(
                    ['message' => 'يرجى الانتظار قليلًا قبل المحاولة مرة أخرى.'],
                    429,
                    $headers
                )
            ))->all();
        });
    }
}
