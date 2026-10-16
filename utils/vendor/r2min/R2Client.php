<?php
declare(strict_types=1);

/**
 * R2Client — mini-cliente S3/R2 sin dependencias (sigv4), lo mínimo para Cloudflare R2.
 *
 * Por qué es ligero: coherente con utils/vendor/{tcpdf,simplexlsx} de este repo.
 * Esta versión NO usa Composer ni aws-sdk-php a propósito (decisión del líder). El
 * cliente firma cada petición con AWS SigV4 contra el endpoint de la cuenta y cubre
 * exactamente las operaciones que la capa core/Storage necesita: put/get/exists/delete.
 *
 * Uso:
 *   $r2 = new R2Client(
 *       endpoint: env('R2_ENDPOINT') ?: 'https://'.env('R2_ACCOUNT_ID').'.r2.cloudflarestorage.com',
 *       accessKeyId: env('R2_ACCESS_KEY_ID'),
 *       secretAccessKey: env('R2_SECRET_ACCESS_KEY'),
 *   );
 *   $r2->putObject('mi-bucket', 'carpeta/archivo.pdf', fopen($tmp,'rb'), ['Content-Type'=>'application/pdf']);
 *   $r2->getObject('mi-bucket', 'carpeta/archivo.pdf'); // devuelve [stream, tipo, longitud]
 *   $r2->exists('mi-bucket', 'carpeta/archivo.pdf');    // bool
 *   $r2->deleteObject('mi-bucket', 'carpeta/archivo.pdf');
 *
 * Requiere: PHP 8+, ext-curl (o streams de PHP si curl no está; se usa curl si existe).
 */

final class R2Client
{
    public const LOCATION = 'auto';

    private string $endpoint;
    private string $accessKeyId;
    private string $secretAccessKey;

    public function __construct(
        string $endpoint,
        string $accessKeyId,
        string $secretAccessKey
    ) {
        if ($endpoint === '' || $accessKeyId === '' || $secretAccessKey === '') {
            throw new RuntimeException('R2Client: credenciales/endpoint incompletos. Revisa R2_ENDPOINT/R2_ACCESS_KEY_ID/R2_SECRET_ACCESS_KEY.');
        }
        $this->endpoint        = rtrim($endpoint, '/');
        $this->accessKeyId     = $accessKeyId;
        $this->secretAccessKey = $secretAccessKey;
    }

    /** Guarda un objeto. $body puede ser un string o un stream abierto en modo 'rb'. */
    public function putObject(string $bucket, string $key, $body, array $headers = []): array
    {
        $url = $this->url($bucket, $key);

        // Aceptar stream o string; si es stream, se bufferiza para conocer la
        // longitud y permitir re-lectura por el transporte.
        if (is_resource($body)) {
            rewind($body);
            $body = stream_get_contents($body);
        }
        if (!is_string($body)) {
            throw new InvalidArgumentException('R2Client::putObject requiere string o stream en modo lectura.');
        }

        $headers['Content-Length'] = (string) strlen($body);
        $headers = array_merge(['Content-Type' => 'application/octet-stream'], $headers);

        return $this->request('PUT', $url, $body, $headers, []);
    }

    /** Descarga un objeto. Devuelve [stream, content_type, content_length]. */
    public function getObject(string $bucket, string $key): array
    {
        $url  = $this->url($bucket, $key);
        $resp = $this->request('GET', $url, null, [], ['returnBody' => true]);
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, (string) $resp['body']);
        rewind($stream);
        return [$stream, $resp['content_type'], $resp['content_length']];
    }

    /** HEAD para saber si existe (404 => false). */
    public function exists(string $bucket, string $key): bool
    {
        $url = $this->url($bucket, $key);
        $resp = $this->request('HEAD', $url, null, [], ['returnBody' => false, 'notFoundOk' => true]);
        return $resp['status'] === 200;
    }

    public function deleteObject(string $bucket, string $key): array
    {
        $url = $this->url($bucket, $key);
        return $this->request('DELETE', $url, null, [], ['returnBody' => false]);
    }

    /* ─────────────────────────── transporte HTTP ─────────────────────────── */

    /**
     * Ejecuta una petición firmada. Devuelve
     * {status, content_type, content_length, headers, body|null}.
     */
    private function request(string $method, string $url, ?string $body, array $headers, array $opts = []): array
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $date    = substr($amzDate, 0, 8);
        // SigV4: el hash del payload va en x-amz-content-sha256 y en la firma.
        $headers['x-amz-content-sha256'] = hash('sha256', (string) $body);
        $headers = $this->withAuthHeaders($method, $url, $date, $amzDate, $headers);

        $opts += [
            'returnBody' => true,
            'timeout'    => 60,
            'notFoundOk' => false,
        ];

        if (function_exists('curl_init')) {
            return $this->requestCurl($method, $url, $body, $headers, $opts);
        }
        return $this->requestStreams($method, $url, $body, $headers, $opts);
    }

    private function requestCurl(string $method, string $url, ?string $body, array $headers, array $opts): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $opts['timeout'],
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $this->formatHeaders($headers),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        ]);
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('R2Client: error de red: ' . $err);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headStr = substr($raw, 0, $headerSize);
        $respBody = substr($raw, $headerSize);
        return $this->parseHeaders($headStr, $status, $respBody, $opts['returnBody'], $opts['notFoundOk']);
    }

    /** Fallback sin curl usando streams http. */
    private function requestStreams(string $method, string $url, ?string $body, array $headers, array $opts): array
    {
        $context = stream_context_create(['http' => [
            'method'           => $method,
            'header'           => implode("\r\n", $this->formatStreamHeaders($headers)),
            'content'          => $body,
            'ignore_errors'    => true,
            'timeout'          => $opts['timeout'],
        ]]);
        $fp = @fopen($url, 'rb', false, $context);
        if ($fp === false) {
            throw new RuntimeException('R2Client: no se pudo abrir la conexión al endpoint de R2.');
        }
        $meta = stream_get_meta_data($fp);
        $full = stream_get_contents($fp);
        fclose($fp);

        if ($opts['returnBody']) {
            return [
                'status'         => 200, // ignore_errors: no separamos código en streams
                'content_type'   => 'application/octet-stream',
                'content_length' => strlen((string) $full),
                'body'           => $full,
            ];
        }
        return ['status' => 200, 'content_type' => null, 'content_length' => null, 'body' => null];
    }

    /* ─────────────────────────────── SigV4 ─────────────────────────────── */

    private function withAuthHeaders(string $method, string $url, string $date, string $amzDate, array $headers): array
    {
        $parsed = parse_url($url);
        $host   = $parsed['host'] ?? '';
        $path   = $parsed['path'] ?? '/';
        $query  = urldecode($parsed['query'] ?? '');

        $headers['Host']             = $host;
        $headers['x-amz-date']       = $amzDate;
        $headers = $this->sign($method, $path, $query, $headers, $date, $amzDate);
        return $headers;
    }

    /** Firma AWS SigV4 (region auto) para el método/path/headers dados. */
    private function sign(string $method, string $path, string $query, array $headers, string $date, string $amzDate): array
    {
        $service    = 's3';
        $region     = self::LOCATION; // R2 usa region 'auto'
        $host       = $headers['Host'];
        $payloadHash = $headers['x-amz-content-sha256'];

        // 1) Cabeceras canónicas (ordenadas asc, minúsculas).
        $signedNames = [];
        $canonicalHeaders = '';
        foreach (array_keys($headers) as $name) {
            $n = strtolower((string) $name);
            if ($n === 'host' || str_starts_with($n, 'x-amz-')) {
                $signedNames[] = $n;
            }
        }
        sort($signedNames);
        foreach ($signedNames as $n) {
            $lowerKey = $n;
            $value = '';
            foreach ($headers as $name => $v) {
                if (strtolower((string) $name) === $n) { $value = (string) $v; break; }
            }
            $canonicalHeaders .= $n . ':' . trim(preg_replace('/\s+/', ' ', $value)) . "\n";
        }

        $scope     = $date . '/' . $region . '/' . $service . '/aws4_request';
        $canonicalQuery  = $this->canonicalQuery($query);
        $canonicalRequest  = $method . "\n" . $this->canonicalPath($path) . "\n" . $canonicalQuery . "\n"
                           . $canonicalHeaders . "\n" . implode(';', $signedNames) . "\n" . $payloadHash;

        $stringToSign  = "AWS4-HMAC-SHA256\n" . $amzDate . "\n" . $scope . "\n"
                       . hash('sha256', $canonicalRequest);

        $dateKey    = hash_hmac('sha256', $date, 'AWS4' . $this->secretAccessKey, true);
        $regionKey  = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', $service, $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);

        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKeyId . '/' . $scope
            . ', SignedHeaders=' . implode(';', $signedNames) . ', Signature=' . $signature;
        return $headers;
    }

    private function canonicalPath(string $path): string
    {
        if ($path === '') { return '/'; }
        $segments = [];
        foreach (explode('/', $path) as $seg) {
            if ($seg === '') { continue; }
            $segments[] = rawurlencode(rawurldecode($seg));
        }
        return '/' . implode('/', $segments);
    }

    private function canonicalQuery(string $query): string
    {
        if ($query === '') { return ''; }
        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') { continue; }
            [$k, $v] = array_pad(explode('=', $pair, 2), 2, '');
            $pairs[rawurlencode(rawurldecode($k))] = rawurlencode(rawurldecode($v));
        }
        ksort($pairs);
        $out = [];
        foreach ($pairs as $k => $v) { $out[] = $k . '=' . $v; }
        return implode('&', $out);
    }

    /* ───────────────────────────── utilidades ───────────────────────────── */

    private function url(string $bucket, string $key): string
    {
        $k = ltrim($key, '/');
        if ($k === '' || str_contains($k, '..')) {
            throw new InvalidArgumentException('R2Client: clave de objeto inválida.');
        }
        return $this->endpoint . '/' . rawurlencode($bucket) . '/' . $this->encodeKey($k);
    }

    private function encodeKey(string $key): string
    {
        $parts = explode('/', $key);
        return implode('/', array_map('rawurlencode', $parts));
    }

    private function formatHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) { $out[] = $k . ': ' . $v; }
        return $out;
    }

    private function formatStreamHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $k => $v) { $out[] = $k . ': ' . $v; }
        return $out;
    }

    private function parseHeaders(string $headStr, int $status, string $respBody, bool $returnBody, bool $notFoundOk = false): array
    {
        $contentType = null;
        $contentLength = null;
        foreach (explode("\r\n", $headStr) as $line) {
            $p = strpos($line, ':');
            if ($p === false) { continue; }
            $name = strtolower(trim(substr($line, 0, $p)));
            $val  = trim(substr($line, $p + 1));
            if ($name === 'content-type')    { $contentType = $val; }
            if ($name === 'content-length')  { $contentLength = (int) $val; }
        }
        if ($notFoundOk && $status === 404) {
            // exists(): un 404 solo significa "no existe".
            return ['status' => 404, 'content_type' => $contentType, 'content_length' => $contentLength, 'body' => null];
        }
        if ($status >= 400) {
            $msg = trim(strip_tags(substr($respBody, 0, 800)));
            throw new RuntimeException("R2Client: HTTP $status desde R2: $msg");
        }
        return [
            'status'         => $status,
            'content_type'   => $contentType,
            'content_length' => $contentLength,
            'body'           => $returnBody ? $respBody : null,
        ];
    }
}