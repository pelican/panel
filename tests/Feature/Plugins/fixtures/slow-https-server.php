<?php

/*
 * A one-connection https server on 127.0.0.1 for PluginRegistryDeadlineTest, with a throwaway self-signed
 * certificate. It prints its port, answers one request and exits. Modes:
 *  - headers: sends the status line, then one header byte every 100 ms for 3 seconds before ending the headers;
 *  - body: sends the headers at once, then one body byte every 100 ms for 3 seconds;
 *  - large: sends the headers at once, then a body without Content-Length as fast as it can, up to 64 MiB.
 */

$mode = $argv[1] ?? '';

$key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
$certificate = openssl_csr_sign(openssl_csr_new(['commonName' => '127.0.0.1'], $key, ['digest_alg' => 'sha256']), null, $key, 1, ['digest_alg' => 'sha256']);
openssl_x509_export($certificate, $certificatePem);
openssl_pkey_export($key, $keyPem);

$pem = tempnam(sys_get_temp_dir(), 'slow-https-');
file_put_contents($pem, $certificatePem . $keyPem);
register_shutdown_function(fn () => @unlink($pem));

$server = stream_socket_server('tls://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, stream_context_create(['ssl' => ['local_cert' => $pem]]));
if ($server === false) {
    fwrite(STDERR, "listen: $error\n");
    exit(1);
}

fwrite(STDOUT, parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT) . "\n");
fflush(STDOUT);

$client = @stream_socket_accept($server, 10);
if ($client === false) {
    exit(1);
}

$request = '';
while (!str_contains($request, "\r\n\r\n") && ($line = fgets($client)) !== false) {
    $request .= $line;
}

$trickle = function () use ($client) {
    for ($until = microtime(true) + 3; microtime(true) < $until; usleep(100_000)) {
        if (@fwrite($client, 'a') === false) {
            return;
        }
    }
};

switch ($mode) {
    case 'headers':
        @fwrite($client, "HTTP/1.1 200 OK\r\nX-Slow: ");
        $trickle();
        @fwrite($client, "\r\nContent-Length: 2\r\nConnection: close\r\n\r\n{}");
        break;
    case 'body':
        @fwrite($client, "HTTP/1.1 200 OK\r\nConnection: close\r\n\r\n");
        $trickle();
        break;
    case 'large':
        @fwrite($client, "HTTP/1.1 200 OK\r\nConnection: close\r\n\r\n");
        $chunk = str_repeat('a', 64 * 1024);
        for ($sent = 0; $sent < 64 * 1024 * 1024; $sent += strlen($chunk)) {
            if (@fwrite($client, $chunk) === false) {
                break;
            }
        }
        break;
}

@fclose($client);
