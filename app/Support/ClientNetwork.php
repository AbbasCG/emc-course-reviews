<?php

namespace App\Support;

/**
 * Rate-limit bucket for a client address.
 *
 * IPv6 users usually own a whole /64, so keying on the full address would let a
 * single machine rotate through billions of "different" clients. IPv6 is therefore
 * bucketed on its /64 prefix; IPv4-mapped IPv6 (::ffff:1.2.3.4) is treated as IPv4.
 */
final class ClientNetwork
{
    public static function bucket(?string $ip): string
    {
        if ($ip === null || $ip === '') {
            return 'unknown';
        }

        $packed = @inet_pton($ip);
        if ($packed === false) {
            return $ip;
        }

        if (strlen($packed) === 16) {
            if (str_starts_with($packed, str_repeat("\0", 10) . "\xff\xff")) {
                return (string) inet_ntop(substr($packed, 12));
            }

            return bin2hex(substr($packed, 0, 8)) . '/64';
        }

        return $ip;
    }
}
