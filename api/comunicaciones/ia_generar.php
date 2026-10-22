<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/comunicaciones/IaRedaccionService.php';

/**
 * POST /api/comunicaciones/ia_generar
 * {tipo:mensaje|html, canal:sms|whatsapp|email, objetivo, tono, largo, contexto}
 */
Http::requireMethod('POST');
Http::crud('comunicaciones.nueva', 'ia');

$clave = 'ia-com:' . (string) ($staff['id'] ?? 'anon');
if (RateLimit::bloqueado($clave, 20, 3600)) {
    Http::fail('Demasiadas generaciones de IA en la última hora. Intente más tarde.', 429);
}
RateLimit::registrar($clave);

$in = Http::input();
$tipo = trim((string) ($in['tipo'] ?? 'mensaje'));
$canal = trim((string) ($in['canal'] ?? ''));
$objetivo = trim((string) ($in['objetivo'] ?? ''));
$tono = trim((string) ($in['tono'] ?? 'institucional'));
$largo = trim((string) ($in['largo'] ?? 'medio'));
$contexto = (string) ($in['contexto'] ?? '');

$svc = new IaRedaccionService();
$res = $svc->generar($tipo, $canal, $objetivo, $tono, $largo, $contexto);
Logger::log('generar_ia', 'comunicaciones', $staff['id'] ?? null,
    'Borrador IA ' . $tipo . ' para ' . $canal . ' · modelo ' . ($res['modelo'] ?? ''));
Http::ok($res);
