<?php
declare(strict_types=1);
/**
 * /api/portal/solicitud_calificar_token.php  → PÚBLICO (valida token, sin sesión).
 *   GET  ?token=…                                  → info de la solicitud a calificar.
 *   POST { token, puntuacion, comentarios? }        → registra la calificación.
 * El token se envía por correo al asociado al resolver su solicitud.
 */
require_once dirname(__DIR__, 2) . '/core/Http.php';
require_once dirname(__DIR__, 2) . '/core/Logger.php';
require_once dirname(__DIR__, 2) . '/core/solicitudes/SolicitudService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 400);
    }
    error_log('[calificar-token] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

$svc = new SolicitudService();

if (Http::method() === 'GET') {
    $token = trim((string) ($_GET['token'] ?? ''));
    if ($token === '') {
        Http::fail('Falta el token.');
    }
    Http::ok($svc->porToken($token));
}

if (Http::method() === 'POST') {
    Idempotencia::begin('solicitudes');
    $in    = Http::input();
    $token = trim((string) ($in['token'] ?? ''));
    if ($token === '') {
        Http::fail('Falta el token.');
    }
    Http::ok($svc->calificarPorToken($token, $in));
}

Http::fail('Método no permitido', 405);
