<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    private string $rateLimitDirectory = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Rate-limit counters are files (App\Support\RateLimiting\WindowCounter): every test gets its own empty directory,
        // so tests never share counters and nothing is written into the project's storage/.
        $this->rateLimitDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'emc-ratelimit-' . bin2hex(random_bytes(6));
        config(['ratelimits.store_path' => $this->rateLimitDirectory]);
    }

    protected function tearDown(): void
    {
        if ($this->rateLimitDirectory !== '' && is_dir($this->rateLimitDirectory)) {
            foreach (glob($this->rateLimitDirectory . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->rateLimitDirectory);
        }

        parent::tearDown();
    }

    protected function rateLimitDirectory(): string
    {
        return $this->rateLimitDirectory;
    }
}
