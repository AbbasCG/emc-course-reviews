<?php

/*
 * Test helper (not a test): boots the real application in its own PHP process and handles ONE POST /review/{token}, so a test
 * can start several of these at the same time and observe genuine parallel behaviour (counter files, cache locks, unique indexes).
 *
 *   php tests/Support/submit_worker.php '<json>'     json: root, token, payload, session, cookie, ip
 *
 * The environment (database file, cache and counter directories, storage path) is passed through the process environment by
 * the test. Prints {"status": <http status>} on one line.
 */

$config = json_decode($argv[1] ?? '', true, 512, JSON_THROW_ON_ERROR);

require $config['root'] . '/vendor/autoload.php';
$app = require $config['root'] . '/bootstrap/app.php';

// The test sends the session cookie unencrypted.
\Illuminate\Cookie\Middleware\EncryptCookies::except([$config['cookie']]);

$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$cookies = $config['session'] === null ? [] : [$config['cookie'] => $config['session']];
$request = \Illuminate\Http\Request::create(
    '/review/' . $config['token'],
    'POST',
    $config['payload'],
    $cookies,
    [],
    ['REMOTE_ADDR' => $config['ip'], 'HTTP_ACCEPT' => 'application/json']
);

$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode()]) . "\n";
$kernel->terminate($request, $response);
