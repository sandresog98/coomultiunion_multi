<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/chat/listar?cedula=&estado=&fecha_desde=&fecha_hasta=&q=&order=&dir=&limit=&offset=
Http::requireMethod('GET');
$f = [];
foreach (['cedula', 'estado', 'fecha_desde', 'fecha_hasta', 'q'] as $k) {
    $v = trim((string) ($_GET[$k] ?? ''));
    if ($v !== '') {
        $f[$k] = $v;
    }
}
$f['limit']  = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$f['offset'] = max(0, (int) ($_GET['offset'] ?? 0));
$f['order']  = trim((string) ($_GET['order'] ?? 'fecha_actualizacion'));
$f['dir']    = trim((string) ($_GET['dir'] ?? 'DESC'));
Http::ok($service->listar($f));
