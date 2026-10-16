<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/pagos/confirmar  { confiar_id, cedula, comentario, ruta_comprobante } → asignar
// PUT  /api/pagos/confirmar  { confiar_id, motivo }                 → rechazar
// PUT  /api/pagos/confirmar  { confiar_id, desasignar: true }       → desasignar
$method = $_SERVER['REQUEST_METHOD'];
Http::crud('pagos', 'confirmar');
$in = json_decode(file_get_contents('php://input'), true) ?: [];
$actor = Auth::actor();

if ($method === 'POST') {
    Idempotencia::begin('pagos');
    $confiarId = trim((string) ($in['confiar_id'] ?? ''));
    $cedula = trim((string) ($in['cedula'] ?? ''));
    if ($confiarId === '' || $cedula === '') { Http::fail('Faltan confiar_id o cedula'); }
    $service->confirmarConfiar($confiarId, $cedula, $in['comentario'] ?? null, $in['ruta_comprobante'] ?? null, $actor);
    Http::ok(['confirmed' => true]);

} elseif ($method === 'PUT') {
    Idempotencia::begin('pagos');
    $confiarId = trim((string) ($in['confiar_id'] ?? ''));
    if ($confiarId === '') { Http::fail('Falta confiar_id'); }

    if (!empty($in['desasignar'])) {
        $service->desasignarConfiar($confiarId, $actor);
        Http::ok(['unassigned' => true]);
    } else {
        $motivo = trim((string) ($in['motivo'] ?? ''));
        if ($motivo === '') { Http::fail('Falta motivo'); }
        $service->rechazarConfiar($confiarId, $motivo, $actor);
        Http::ok(['rejected' => true]);
    }

} else {
    Http::fail('Método no permitido', 405);
}
