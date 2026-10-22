<?php
declare(strict_types=1);
require dirname(__DIR__) . '/_public.php';

if (Http::method() !== 'POST') {
    http_response_code(405);
    exit;
}

$params = $_POST;
if (TwilioSignature::validationEnabled()) {
    if (!TwilioSignature::validate(TwilioSignature::inboundUrl(), $params)) {
        error_log('[comunicaciones][webhook-inbound] firma inválida desde ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
        http_response_code(403);
        exit('Firma inválida');
    }
}

$res = ['ok' => false];
try {
    $res = $envio->procesarInbound($params);
} catch (Throwable $e) {
    error_log('[comunicaciones][webhook-inbound] ' . $e->getMessage());
}

$respuesta = (string) ($res['respuesta'] ?? '');
if ($respuesta !== '') {
    header('Content-Type: text/xml; charset=utf-8');
    echo '<?xml version="1.0" encoding="UTF-8"?><Response><Message>'
        . htmlspecialchars($respuesta, ENT_XML1 | ENT_QUOTES, 'UTF-8')
        . '</Message></Response>';
    exit;
}

http_response_code(204);
exit;
