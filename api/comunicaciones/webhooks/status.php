<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_public.php';

if (Http::method() !== 'POST') {
    http_response_code(405);
    exit;
}

$params = $_POST;
if (TwilioSignature::validationEnabled()) {
    if (!TwilioSignature::validate(TwilioSignature::statusUrl(), $params)) {
        error_log('[comunicaciones][webhook-status] firma inválida desde ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        http_response_code(403);
        exit('Firma inválida');
    }
}

try {
    $envio->procesarStatus($params);
} catch (Throwable $e) {
    error_log('[comunicaciones][webhook-status] ' . $e->getMessage());
    // Igual se responde 200 para que Twilio no reintente indefinidamente un
    // evento que no podemos procesar; queda el log para diagnóstico.
}
http_response_code(204);
exit;
