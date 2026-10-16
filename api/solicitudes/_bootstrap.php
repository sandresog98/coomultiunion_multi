<?php
declare(strict_types=1);

/**
 * Bootstrap común de los endpoints de solicitudes.
 * Reconcilia DOBLE sesión de forma segura: PHP solo admite un session_name por
 * petición, así que se decide por la cookie presente y se abre SOLO esa sesión:
 *   - cookie `multiappv2_staff`  → staff (core/Auth),  actor ['tipo'=>'interno']
 *   - cookie `multiappv2_portal` → portal (PortalAuth), actor ['tipo'=>'asociado']
 * Expone $service (SolicitudService) y $actor normalizado.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/portal/PortalAuth.php';
require_once $root . '/core/solicitudes/SolicitudService.php';
require_once $root . '/core/Email/EmailCola.php';

set_exception_handler(static function (Throwable $e): void {
    // DomainException = error de negocio (400); 4xx explícito; el resto = error interno (500).
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), $e->getCode() >= 400 ? $e->getCode() : 400);
    }
    error_log('[solicitudes-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

$hasPortal = isset($_COOKIE['multiappv2_portal']);
$hasStaff  = isset($_COOKIE['multiappv2_staff']);

if ($hasPortal && !$hasStaff) {
    $p = PortalAuth::actor();
    $actor = $p ? ['tipo' => 'asociado', 'id' => (string) $p['cedula']] : null;
} else {
    $s = Auth::staff();
    $actor = $s ? Auth::actor() : null;
}

if (!$actor) {
    Http::fail('No autenticado', 401);
}

Http::csrf();

$service = new SolicitudService();