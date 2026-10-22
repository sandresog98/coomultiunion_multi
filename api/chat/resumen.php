<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/chat/resumen?cedula=&estado=&fecha_desde=&fecha_hasta=&q= — totales (con filtros) + opciones.
Http::requireMethod('GET');
$f = [];
foreach (['cedula', 'estado', 'fecha_desde', 'fecha_hasta', 'q'] as $k) {
    $v = trim((string) ($_GET[$k] ?? ''));
    if ($v !== '') {
        $f[$k] = $v;
    }
}
Http::ok($service->resumen($f));
