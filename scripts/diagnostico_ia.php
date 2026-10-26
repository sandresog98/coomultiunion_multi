<?php
declare(strict_types=1);

/**
 * Diagnóstico del Asistente IA (CLI). Ejecutar EN EL SERVIDOR (develop/producción):
 *
 *   php scripts/diagnostico_ia.php
 *
 * Comprueba: entorno/PHP/cURL, conexión y tablas del chat, y una llamada real mínima
 * a SiliconFlow. NO imprime el secreto completo (solo prefijo + longitud).
 */

require_once dirname(__DIR__) . '/core/env.php';

function dline(string $s = ''): void
{
    echo $s . PHP_EOL;
}

$key     = trim((string) env('SILICONFLOW_API_KEY', ''));
$base    = rtrim((string) env('SILICONFLOW_BASE_URL', 'https://api.siliconflow.com/v1'), '/');
$model   = trim((string) env('SILICONFLOW_MODEL', 'deepseek-ai/DeepSeek-V4.1-Flash'));
$timeout = max(5, (int) env('AI_CHAT_TIMEOUT', '30'));

dline('=== Diagnóstico Asistente IA ===');
dline('PHP: ' . PHP_VERSION . ' · SAPI: ' . PHP_SAPI);
dline('cURL: ' . (function_exists('curl_init') ? 'OK' : 'NO DISPONIBLE'));
dline('openssl: ' . (extension_loaded('openssl') ? 'OK' : 'NO'));
dline('API key: ' . ($key === ''
    ? 'VACÍA (revisa .env en la raíz del proyecto)'
    : 'presente (' . strlen($key) . ' chars, ' . substr($key, 0, 6) . '…)'));
dline('Base URL: ' . $base);
dline('Modelo: ' . $model);
dline('Timeout: ' . $timeout . 's');
dline();

// ── Config del asistente (BD) ──────────────────────────────────────────
dline('--- Base de datos / tablas del chat ---');
try {
    require_once dirname(__DIR__) . '/core/database.php';
    foreach (['chat_sesiones', 'chat_mensajes', 'chat_conocimiento', 'chat_uso', 'chat_precios_modelo', 'chat_feedback'] as $t) {
        $ok = (bool) db()->query("SHOW TABLES LIKE '" . $t . "'")->fetchColumn();
        dline('Tabla ' . $t . ': ' . ($ok ? 'OK' : 'FALTA (aplica las migraciones sql/)'));
    }
    foreach (db()->query("SELECT clave, valor FROM app_configuracion WHERE clave LIKE 'chat_ia%'")->fetchAll() as $r) {
        dline('  ' . $r['clave'] . ' = ' . $r['valor']);
    }
} catch (Throwable $e) {
    dline('DB error: ' . $e->getMessage());
}
dline();

if (!function_exists('curl_init')) {
    dline('Falta la extensión cURL: habilítala en php.ini (extension=curl).');
    exit(1);
}

$host = parse_url($base, PHP_URL_HOST) ?: '';
$ip   = $host !== '' ? gethostbyname($host) : '';
dline('DNS ' . $host . ': ' . ($ip !== $host ? 'resuelve a ' . $ip : 'NO resuelve'));
dline();

// ── Conexión (GET /models) ─────────────────────────────────────────────
dline('--- Conexión al proveedor (GET /models) ---');
$ch = curl_init($base . '/models');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => $timeout,
]);
$r    = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = (string) curl_error($ch);
$eno  = curl_errno($ch);
curl_close($ch);
dline('HTTP: ' . $http);
dline('cURL error (' . $eno . '): ' . ($err !== '' ? $err : '—'));
dline('Body: ' . substr((string) $r, 0, 400));
dline();

if ($err !== '') {
    dline('No se pudo conectar. Causas típicas: salida a Internet/whitelist bloqueada, DNS, proxy o CA de SSL.');
    dline('   → Prueba desde el servidor:  curl -I https://api.siliconflow.com/v1/models');
    exit(2);
}

// ── Chat mínimo (POST /chat/completions) ───────────────────────────────
dline('--- Llamada de chat mínima (POST /chat/completions) ---');
$payload = json_encode([
    'model'      => $model,
    'messages'   => [['role' => 'user', 'content' => 'Responde solo: ok']],
    'max_tokens' => 8,
    'stream'     => false,
], JSON_UNESCAPED_UNICODE);
$ch = curl_init($base . '/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => $timeout,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$r    = curl_exec($ch);
$http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = (string) curl_error($ch);
curl_close($ch);
dline('HTTP: ' . $http);
dline('cURL error: ' . ($err !== '' ? $err : '—'));
dline('Body: ' . substr((string) $r, 0, 600));
dline();

if ($http === 200) {
    dline('OK: el proveedor respondió. Si el portal sigue fallando, revisa AI_CHAT_ENABLED/chat_ia_habilitado y logs.');
} elseif ($http === 401 || $http === 403) {
    dline('API key inválida o sin permisos (401/403). Revisa SILICONFLOW_API_KEY del .env del servidor.');
} elseif ($http === 400 || $http === 404) {
    dline('Endpoint o modelo inválido (400/404). SILICONFLOW_BASE_URL debe incluir /v1 y el modelo debe existir en la cuenta.');
} elseif ($http === 429) {
    dline('Límite/cuota de la cuenta del proveedor (429).');
} elseif ($http >= 500) {
    dline('Error del proveedor (5xx): reintenta más tarde.');
} else {
    dline('Revisa el Body de arriba para el detalle.');
}
