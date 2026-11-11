<?php

/*
 * Test helper (not a test): one PHP process that hits a WindowCounter many times, to prove the counter is atomic when several
 * processes (= PHP workers) hit the same key at once.
 *
 *   php tests/Support/counter_worker.php <root> <directory> <key> <hits> <limit> <window>
 *
 * Prints the number of hits that were ALLOWED.
 */

[, $root, $directory, $key, $hits, $limit, $window] = $argv;

require $root . '/vendor/autoload.php';

$counter = new \App\Support\RateLimiting\WindowCounter($directory);
$allowed = 0;
for ($i = 0; $i < (int) $hits; $i++) {
    if ($counter->hit($key, (int) $limit, (int) $window)['allowed']) {
        $allowed++;
    }
}

echo $allowed . "\n";
