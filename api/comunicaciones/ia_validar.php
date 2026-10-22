<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/comunicaciones/IaRedaccionService.php';

/**
 * POST /api/comunicaciones/ia_validar
 * {canal, formato, asunto, cuerpo, cuerpo_html, adjuntos:[{nombre,mime}], destinatarios}
 */
Http::requireMethod('POST');
Http::crud('comunicaciones.nueva', 'ia');

$clave = 'ia-com-val:' . (string) ($staff['id'] ?? 'anon');
if (RateLimit::bloqueado($clave, 20, 3600)) {
    Http::fail('Demasiadas validaciones de IA en la última hora. Intente más tarde.', 429);
}
RateLimit::registrar($clave);

$in = Http::input();
$svc = new IaRedaccionService();
$res = $svc->validarCoherencia($in);
Logger::log('validar_ia', 'comunicaciones', $staff['id'] ?? null,
    'Validación IA ' . ($in['canal'] ?? '') . ' · ' . (!empty($res['ok']) ? 'OK' : 'con observaciones'));
Http::ok($res);
