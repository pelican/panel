<?php

use App\Services\Helpers\PluginRegistryService;
use Illuminate\Support\Facades\Http;

// pelican/wings plugin contract v3, R2: one deadline for a whole download, against a real (local) https server,
// since faked responses never reach Guzzle's handlers.

/** @var list<resource> */
$servers = [];

beforeEach(function () use (&$servers) {
    $servers = [];

    // The local server's certificate is made up on the spot.
    Http::globalOptions(['verify' => false]);
});

afterEach(function () use (&$servers) {
    foreach ($servers as $server) {
        proc_terminate($server);
        proc_close($server);
    }
});

/**
 * Starts fixtures/slow-https-server.php in the given mode and returns its URL.
 *
 * @param  list<resource>  $servers
 */
function slowHttpsServer(string $mode, array &$servers): string
{
    $server = proc_open([PHP_BINARY, __DIR__ . '/fixtures/slow-https-server.php', $mode], [1 => ['pipe', 'w']], $pipes);
    $servers[] = $server;

    stream_set_timeout($pipes[1], 5);
    $port = trim((string) fgets($pipes[1]));
    expect($port)->toMatch('/\A\d+\z/');

    return "https://127.0.0.1:$port/registry.json";
}

/**
 * Runs $fetch and returns the message it failed with (null when it didn't) and how long it took, in seconds.
 *
 * @return array{?string, float}
 */
function failureAndDuration(Closure $fetch): array
{
    $start = hrtime(true);

    try {
        $fetch();
        $message = null;
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
    }

    return [$message, (hrtime(true) - $start) / 1e9];
}

it('stops waiting for response headers that trickle in at the deadline', function () use (&$servers) {
    $url = slowHttpsServer('headers', $servers);

    [$message, $seconds] = failureAndDuration(fn () => app(PluginRegistryService::class)->fetch($url, null, 1024, 1));

    expect($seconds)->toBeLessThan(2.0)
        ->and($message)->toContain('timed out');
});

it('stops a body that trickles in at the deadline', function () use (&$servers) {
    $url = slowHttpsServer('body', $servers);

    [$message, $seconds] = failureAndDuration(fn () => app(PluginRegistryService::class)->fetch($url, null, 1024, 1));

    expect($seconds)->toBeLessThan(2.0)
        ->and($message)->toContain('timed out');
});

it('stops a real download without Content-Length once it passes the size cap', function () use (&$servers) {
    $url = slowHttpsServer('large', $servers);

    [$message] = failureAndDuration(fn () => app(PluginRegistryService::class)->fetch($url, null, 1024 * 1024, 5));

    expect($message)->toContain('is too large');
});
