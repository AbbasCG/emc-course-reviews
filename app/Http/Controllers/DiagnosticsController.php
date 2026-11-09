<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiagnosticsController extends Controller
{
    /** Headers that proxies / CDNs commonly use to pass the visitor IP. */
    private const FORWARDING_HEADERS = [
        'X-Forwarded-For', 'X-Real-IP', 'CF-Connecting-IP', 'True-Client-IP', 'Forwarded', 'X-Forwarded-Proto', 'X-Forwarded-Host',
    ];

    /**
     * Shows how the server sees the admin's OWN request so the real client-IP
     * handling behind the CDN can be verified without log access: open it from a
     * known network and compare "resolved_ip" with your public IP.
     */
    public function clientIp(Request $request): JsonResponse
    {
        $received = [];
        foreach (self::FORWARDING_HEADERS as $header) {
            if ($request->headers->has($header)) {
                $received[$header] = $request->headers->get($header);
            }
        }

        $trusted = config('trustedproxy.proxies');

        return response()->json([
            'resolved_ip'                => $request->ip(),
            'remote_addr'                => $request->server->get('REMOTE_ADDR'),
            'ip_chain_seen_by_laravel'   => $request->ips(),
            'forwarding_headers_received' => $received,
            'trusted_proxies_config'     => $trusted === [] ? '(none - nobody is trusted)' : $trusted,
            'is_secure'                  => $request->isSecure(),
            'host'                       => $request->getHost(),
            'how_to_read'                => 'Open this page from your own device and compare resolved_ip with your public IP (try IPv4, IPv6 and mobile data). '
                . 'If resolved_ip equals your IP, leave TRUSTED_PROXIES empty. If remote_addr is a CDN/proxy address and the LAST entry of '
                . 'X-Forwarded-For is your IP, put that remote_addr (its CIDR range, or the word REMOTE_ADDR) in TRUSTED_PROXIES. '
                . 'Prefer that over "*": with "*" a request sent straight to the origin can choose its own IP.',
        ], 200, [
            'X-Robots-Tag'  => 'noindex, nofollow',
            'Cache-Control' => 'no-store, private',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
