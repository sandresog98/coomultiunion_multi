<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/solicitudes/transicion.php   { id, estado_destino, comentario?, resultado?, ruta_archivo? }
Http::requireMethod('POST');
$in = Http::input();
Idempotencia::begin('solicitudes');

$id       = (int) ($in['id'] ?? 0);
$destino  = (string) ($in['estado_destino'] ?? '');
if ($id <= 0 || $destino === '') {
    Http::fail('Faltan id o estado_destino');
}

// Asociado solo transiciona sus propias solicitudes (la máquina valida rol/estado).
if ($actor['tipo'] === 'asociado') {
    $d = $service->detalle($id);
    if ((string) $d['solicitud']['cedula_solicitante'] !== (string) $actor['id']) {
        Http::fail('No autorizado', 403);
    }
    // retiro_aporte: el desembolso/rechazo es gestión exclusiva de staff.
    if ($d['solicitud']['tipo_codigo'] === 'retiro_aporte') {
        Http::fail('No autorizado', 403);
    }
} else {
    Auth::requireModulo('solicitudes');
    Http::crud('solicitudes.gestion', 'acciones');
}

$res = $service->transicionar(
    $id,
    $destino,
    $actor,
    isset($in['comentario']) ? (string) $in['comentario'] : null,
    [
        'resultado'    => $in['resultado']    ?? null,
        'ruta_archivo' => $in['ruta_archivo'] ?? null,
    ]
);

// Si la transición resuelve la solicitud → aviso al asociado.
$fase = $res['solicitud']['fase'] ?? '';
if (in_array($fase, ['RESUELTA_POSITIVA', 'RESUELTA_NEGATIVA'], true)) {
    EmailCola::notificarSolicitudResuelta($res, isset($in['comentario']) ? (string) $in['comentario'] : null);
}

Http::ok($res);