<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id, returns it as X-Request-Id and adds it to all log lines of the request,
 * so a student's "error" message can be traced without logging anything about the student.
 * Also strips the X-Powered-By header (PHP version) from responses.
 */
class RequestId
{
    /** Ids supplied by a client/proxy are used only when they look harmless. */
    private const SAFE_ID = '/^[A-Za-z0-9._-]{8,64}\z/D';   // \z: "$" would also accept a trailing newline

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $id = is_string($incoming) && preg_match(self::SAFE_ID, $incoming) ? $incoming : (string) Str::ulid();

        $request->attributes->set('request_id', $id);
        Log::shareContext(['request_id' => $id]);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $id);
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');   // PHP itself adds it when expose_php is on
        }

        return $response;
    }

    /** Id of the current request, if the middleware ran. */
    public static function current(Request $request): ?string
    {
        return $request->attributes->get('request_id');
    }

    /** A CDN-provided id that is safe to log (lets Hostinger support correlate a request). */
    public static function safe(?string $value): ?string
    {
        return is_string($value) && preg_match(self::SAFE_ID, $value) ? $value : null;
    }
}
