<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_public.php';

if (Http::method() !== 'POST') {
    http_response_code(405);
    exit;
}

$secret = trim((string) env('SENDGRID_EVENT_WEBHOOK_SECRET', ''));
$recibido = trim((string) ($_GET['k'] ?? $_SERVER['HTTP_X_WEBHOOK_SECRET'] ?? ''));
if ($secret !== '') {
    if (!hash_equals($secret, $recibido)) {
        http_response_code(403);
        exit('Firma inválida');
    }
} elseif (app_env() !== 'local') {
    http_response_code(403);
    exit('Webhook sin secreto configurado');
}

$raw = file_get_contents('php://input') ?: '';
$eventos = json_decode($raw, true);
if (!is_array($eventos)) {
    http_response_code(400);
    exit('JSON inválido');
}

try {
    $envio->procesarSendGrid($eventos);
} catch (Throwable $e) {
    error_log('[comunicaciones][webhook-sendgrid] ' . $e->getMessage());
}
http_response_code(200);
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
