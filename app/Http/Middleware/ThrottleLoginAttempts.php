<?php

namespace App\Http\Middleware;

use App\Support\ClientNetwork;
use App\Support\RateLimiting\WindowCounter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Brute-force protection for the admin login (it had none): per e-mail address from one IP, and per IP.
 * The e-mail is part of a keyed hash, never stored; the limit is checked before the password is verified.
 */
class ThrottleLoginAttempts
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('ratelimits.login');
        $counter = WindowCounter::make();
        $ip = ClientNetwork::bucket($request->ip());
        $email = $request->input('email');
        $account = is_string($email) ? mb_strtolower(trim($email)) : '';   // an array (email[]=x) must reach validation, not crash here

        $layers = [
            ['login_per_account_ip', WindowCounter::key('login-account', $account . '|' . $ip), (int) $config['per_account_ip']],
            ['login_per_ip', WindowCounter::key('login-ip', $ip), (int) $config['per_ip']],
        ];

        foreach ($layers as [$name, $key, $limit]) {
            $result = $counter->hit($key, $limit, 60);
            if (! $result['allowed']) {
                $request->attributes->set('rate_limit_layer', $name);
                $headers = ['Retry-After' => $result['retry_after'], 'Cache-Control' => 'no-store'];
                $message = 'محاولات تسجيل دخول كثيرة. يرجى الانتظار ' . $result['retry_after'] . ' ثانية ثم المحاولة مرة أخرى.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'retry_after' => $result['retry_after']], 429, $headers);
                }

                // A real 429 with Retry-After whose body is the login form again, showing the message.
                $errors = (new ViewErrorBag)->put('default', new MessageBag(['email' => [$message]]));

                return response()->view('auth.login', ['errors' => $errors], 429, $headers);
            }
        }

        return $next($request);
    }
}
