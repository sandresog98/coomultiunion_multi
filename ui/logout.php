<?php
declare(strict_types=1);
require_once __DIR__ . '/config/paths.php';
require_once dirname(__DIR__) . '/core/Auth.php';

// Mitigación CSRF sin tocar el layout compartido (los enlaces de logout son GET).
// Se rechaza si el Referer proviene de otro origen (evita logout forzado por sitio externo).
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$ref  = parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST) ?? '';
if ($host !== '' && $ref !== '' && strtolower($ref) !== strtolower($host)) {
    http_response_code(403);
    exit('Origen no permitido');
}
Auth::logout();
header('Location: ' . uiBaseUrl() . 'login');
exit;
