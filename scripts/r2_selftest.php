<?php
declare(strict_types=1);

/**
 * Verifica credenciales/endpoint/bucket de R2 con un PUT/HEAD/GET/DELETE real.
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/r2_selftest.php
 *
 * Lee R2_* del .env o de variables de entorno. No imprime secretos.
 */

require_once __DIR__ . '/../core/env.php';
require_once __DIR__ . '/../utils/vendor/r2min/R2Client.php';

$account  = (string) (env('R2_ACCOUNT_ID') ?? '');
$endpoint = (string) (env('R2_ENDPOINT') ?? '');
$keyId    = (string) (env('R2_ACCESS_KEY_ID') ?? '');
$secret   = (string) (env('R2_SECRET_ACCESS_KEY') ?? '');
$bucket   = (string) (env('R2_BUCKET') ?? '');

if ($endpoint === '' && $account !== '') {
    $endpoint = "https://{$account}.r2.cloudflarestorage.com";
}

$missing = [];
foreach ([
    'R2_ACCOUNT_ID'        => $account,
    'R2_ACCESS_KEY_ID'     => $keyId,
    'R2_SECRET_ACCESS_KEY' => $secret,
    'R2_BUCKET'            => $bucket,
    'R2_ENDPOINT'          => $endpoint,
] as $name => $val) {
    if ($val === '') {
        $missing[] = $name;
    }
}

printf("Endpoint: %s\n", $endpoint !== '' ? $endpoint : '(vacío)');
printf("Bucket:   %s\n", $bucket !== '' ? $bucket : '(vacío)');
printf("Key ID:   %s\n", $keyId !== '' ? substr($keyId, 0, 8) . '...' : '(vacío)');
echo str_repeat('-', 60), "\n";

if ($missing !== []) {
    fwrite(STDERR, 'Faltan variables: ' . implode(', ', array_unique($missing)) . "\n");
    exit(1);
}

$client = new R2Client($endpoint, $keyId, $secret);
$key    = '_selftest/' . gmdate('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.txt';
$body   = 'coomultiunion r2 selftest ' . gmdate('c') . "\n";
$ok     = true;

try {
    echo 'PUT    ... ';
    $r = $client->putObject($bucket, $key, $body, ['Content-Type' => 'text/plain']);
    echo 'HTTP ' . $r['status'] . "\n";

    echo 'HEAD   ... ';
    $exists = $client->exists($bucket, $key);
    echo $exists ? "existe\n" : "NO existe\n";
    $ok = $ok && $exists;

    echo 'GET    ... ';
    [$stream, $mime, $len] = $client->getObject($bucket, $key);
    $got = stream_get_contents($stream);
    if (is_resource($stream)) {
        fclose($stream);
    }
    $same = ($got === $body);
    echo $same
        ? "contenido OK ({$len} bytes, {$mime})\n"
        : "contenido DISTINTO ({$len} bytes)\n";
    $ok = $ok && $same;
} catch (Throwable $e) {
    $ok = false;
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
} finally {
    try {
        echo 'DELETE ... ';
        $r = $client->deleteObject($bucket, $key);
        echo 'HTTP ' . $r['status'] . "\n";
    } catch (Throwable $e) {
        echo 'no se pudo borrar: ' . $e->getMessage() . "\n";
    }
}

echo str_repeat('-', 60), "\n";
echo $ok ? "RESULTADO: OK\n" : "RESULTADO: FALLO\n";
exit($ok ? 0 : 1);
