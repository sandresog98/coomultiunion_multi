<?php
declare(strict_types=1);

/**
 * Bootstrap común de los endpoints de solicitudes.
 * Resuelve el actor distinguiendo portal (cx/) y staff (ui/), que comparten esta API:
 *   - Petición con header `X-Portal-Auth` → SIEMPRE portal (PortalAuth, actor asociado);
 *     si la sesión de portal no es válida → 401 (nunca cae a staff).
 *   - Sin header: portal solo si hay cookie `multiappv2_portal` y NO `multiappv2_staff`;
 *     en otro caso staff (core/Auth).
 * Expone $service (SolicitudService) y $actor normalizado.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/portal/PortalAuth.php';
require_once $root . '/core/portal/ApiActor.php';
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

// El portal (cx) marca sus peticiones con X-Portal-Auth: evita que una cookie de staff
// coexistente haga que el portal opere como staff (fuga de aislamiento por cédula).
if (ApiActor::via(ApiActor::pidePortal(), $hasPortal, $hasStaff) === 'portal') {
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