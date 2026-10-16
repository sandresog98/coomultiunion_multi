<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/solicitudes/listar.php?tipo=&estado=&fase=&responsable=&cedula=&radicado=&q=&limit=&offset=
Http::requireMethod('GET');

$filtros = [
    'tipo'        => $_GET['tipo']        ?? null,
    'estado'      => $_GET['estado']      ?? null,
    'fase'        => $_GET['fase']        ?? null,
    'responsable' => $_GET['responsable'] ?? null,
    'radicado'    => $_GET['radicado']    ?? null,
    'q'           => $_GET['q']           ?? null,
];
// Aislamiento: un asociado lista solo lo suyo (identidad desde sesión).
if ($actor['tipo'] === 'asociado') {
    $filtros['cedula'] = (string) $actor['id'];
} else {
    Auth::requireModulo('solicitudes');
    if (!empty($_GET['cedula'])) {
        $filtros['cedula'] = (string) $_GET['cedula'];
    }
}

$limit  = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));

Http::ok($service->listar($filtros, $limit, $offset));