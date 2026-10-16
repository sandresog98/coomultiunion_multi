<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/solicitudes/detalle.php?id=123
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}

$res = $service->detalle($id);

// Aislamiento por dueño: un asociado solo ve sus solicitudes.
if ($actor['tipo'] === 'asociado') {
    if ((string) $res['solicitud']['cedula_solicitante'] !== (string) $actor['id']) {
        Http::fail('No autorizado', 403);
    }
} else {
    Auth::requireModulo('solicitudes');
}

Http::ok($res);