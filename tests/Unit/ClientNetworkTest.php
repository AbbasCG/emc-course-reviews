<?php

namespace Tests\Unit;

use App\Support\ClientNetwork;
use PHPUnit\Framework\TestCase;

class ClientNetworkTest extends TestCase
{
    public function test_ipv4_addresses_are_bucketed_individually(): void
    {
        $this->assertSame('203.0.113.7', ClientNetwork::bucket('203.0.113.7'));
        $this->assertNotSame(ClientNetwork::bucket('203.0.113.7'), ClientNetwork::bucket('203.0.113.8'));
    }

    public function test_ipv6_addresses_in_the_same_64_share_a_bucket(): void
    {
        $this->assertSame(
            ClientNetwork::bucket('2001:db8:abcd:12::1'),
            ClientNetwork::bucket('2001:db8:abcd:12:ffff:ffff:ffff:ffff')
        );
        $this->assertNotSame(
            ClientNetwork::bucket('2001:db8:abcd:12::1'),
            ClientNetwork::bucket('2001:db8:abcd:13::1')
        );
    }

    public function test_ipv4_mapped_ipv6_is_treated_as_ipv4(): void
    {
        $this->assertSame('198.51.100.9', ClientNetwork::bucket('::ffff:198.51.100.9'));
    }

    public function test_missing_or_invalid_addresses_do_not_crash(): void
    {
        $this->assertSame('unknown', ClientNetwork::bucket(null));
        $this->assertSame('unknown', ClientNetwork::bucket(''));
        $this->assertSame('not-an-ip', ClientNetwork::bucket('not-an-ip'));
    }
}
