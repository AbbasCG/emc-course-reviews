<?php

/*
 * Test helper (not a test): a separate PHP process that keeps WRITING and READING live entries of a file cache store while the
 * test prunes the same directory, to prove that pruning never removes an entry that is still valid.
 *
 *   php tests/Support/cache_writer_worker.php <root> <cacheDirectory> <iterations>
 *
 * Prints the number of live entries that could NOT be read back (must be 0).
 */

[, $root, $directory, $iterations] = $argv;

require $root . '/vendor/autoload.php';

$store = new Illuminate\Cache\FileStore(new Illuminate\Filesystem\Filesystem, $directory);
$lost = 0;
for ($i = 0; $i < (int) $iterations; $i++) {
    $store->put("live-$i", "value-$i", 600);
    $store->put("short-$i", 'x', 1);       // these expire while the test runs and are fair game for the pruner
    if ($store->get("live-$i") !== "value-$i") {
        $lost++;
    }
}
for ($i = 1; $i <= 100; $i++) {              // the test seeded these as EXPIRED: renewing them races with the pruner
    $store->put("renew-$i", "renewed-$i", 600);
    $store->add("renew-$i", 'must-not-replace', 600);
}
for ($i = 1; $i <= 100; $i++) {
    if ($store->get("renew-$i") !== "renewed-$i") {
        $lost++;
    }
}
// read everything again at the end: a pruned live entry would show up now
for ($i = 0; $i < (int) $iterations; $i++) {
    if ($store->get("live-$i") !== "value-$i") {
        $lost++;
    }
}

echo $lost . "\n";
