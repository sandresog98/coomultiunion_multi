<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/solicitudes/crear.php   { tipo, detalle:{...}, cedula_solicitante?, responsable_id? }
Http::requireMethod('POST');
$in = Http::input();
Idempotencia::begin('solicitudes');

$tipo = (string) ($in['tipo'] ?? '');
if ($tipo === '') {
    Http::fail('Falta el tipo de solicitud');
}
$detalle = is_array($in['detalle'] ?? null) ? $in['detalle'] : [];

// Asociado (portal): crea SOLO para sí mismo. Staff: puede crear a nombre de un asociado o tickets internos.
if ($actor['tipo'] === 'asociado') {
    $cedula = (string) $actor['id'];
    $ctx = [
        'origen'           => 'portal',
        'solicitante_tipo' => 'asociado',
        'actor_tipo'       => 'asociado',
        'actor_id'         => (string) $actor['id'],
    ];
    // El portal no crea tickets (tipo interno UI→UI) ni con responsable.
    if ($tipo === 'ticket') {
        Http::fail('No autorizado', 403);
    }
    // El asociado NO elige responsable.
    $detalle['responsable_id'] = null;
    unset($detalle['responsable_id']);
} else {
    // Staff: requiere permiso solicitudes.*
    Auth::requireModulo('solicitudes');
    $cedula = (string) ($in['cedula_solicitante'] ?? '');
    if ($cedula === '') {
        Http::fail('Falta la cédula del solicitante');
    }
    $ctx = [
        'origen'           => 'ui',
        'creado_por'       => (int) $actor['id'],
        'actor_tipo'       => 'interno',
        'actor_id'         => (string) $actor['id'],
    ];
    if ($tipo === 'ticket') {
        // Ticket interno: solicitante es un usuario (id) y requiere responsable.
        $ctx['solicitante_tipo'] = 'interno';
        $ctx['creado_por']       = (int) $actor['id'];
        $ctx['responsable_id']   = (int) (($in['responsable_id'] ?? $in['detalle']['responsable_id'] ?? 0) ?: 0) ?: null;
        if ($ctx['responsable_id'] === null) {
            Http::fail('El ticket requiere un responsable_id');
        }
    } else {
        $ctx['solicitante_tipo'] = 'asociado';
    }
}

$res = $service->crear($tipo, $cedula, $detalle, $ctx);
// Aviso al responsable (solo si la solicitud quedó con responsable asignado, p.ej. tickets).
EmailCola::notificarSolicitudCreada($res);
Http::ok($res, 201);