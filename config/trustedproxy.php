<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies / CDN
|--------------------------------------------------------------------------
|
| Laravel only believes the X-Forwarded-For header when the request reaches
| the app from one of these addresses. Everywhere else $request->ip() is the
| real TCP peer (REMOTE_ADDR), so a visitor cannot choose their own IP by
| sending a forged header.
|
| TRUSTED_PROXIES is a comma separated list of IPs / CIDR ranges, e.g.
|   TRUSTED_PROXIES=203.0.113.0/24,2001:db8::/32
| The default (empty) trusts nobody. Use the admin page
| /admin/diagnostics/client-ip to see what the server really receives before
| filling this in. Prefer the observed CDN address/CIDR (or the word
| REMOTE_ADDR) over "*": "*" trusts whoever connects, so a request sent
| straight to the origin could choose its own IP. Leave it EMPTY if the
| diagnostics page already shows your real IP as resolved_ip.
|
| Release check: before a big event open the diagnostics page from two
| different networks; if resolved_ip is not each network's own IP, every
| student shares the CDN edge IP and one per-IP bucket.
|
| Only X-Forwarded-For is honoured (see bootstrap/app.php); forwarded
| host/proto headers are ignored so URL generation never changes.
|
*/

$raw = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'proxies' => $raw === '*'
        ? '*'
        : array_values(array_filter(array_map('trim', explode(',', $raw)))),
];
