<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/solicitudes/reasignar.php   { id, responsable_id }  → reasigna SIN cambiar estado.
// Autorización: gate 'solicitudes.gestion.reasignar' + (liderazgo o el responsable asignado).
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
if ($actor['tipo'] !== 'interno') {
    Http::fail('Solo personal interno', 403);
}
Auth::requireModulo('solicitudes');
Http::crud('solicitudes.gestion', 'reasignar');
$in = Http::input();
$id  = (int) ($in['id'] ?? 0);
$rid = (int) ($in['responsable_id'] ?? 0);
if ($id <= 0 || $rid <= 0) {
    Http::fail('Faltan id o responsable_id');
}

// Autorización dinámica: liderazgo (admin/lider) o el responsable asignado actual.
$solicitud = $service->detalle($id)['solicitud'];
$rol        = (string) ($actor['rol'] ?? '');
$esLiderazgo = in_array($rol, ['admin', 'lider'], true);
$esAsignado  = (string) ($solicitud['responsable_id'] ?? '') !== ''
    && (string) $solicitud['responsable_id'] === (string) $actor['id'];
if (!$esLiderazgo && !$esAsignado) {
    Http::fail('Solo liderazgo o el responsable asignado pueden reasignar.', 403);
}

Http::ok($service->reasignar($id, $rid, $actor));