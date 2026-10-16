<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/solicitudes/reasignar.php   { id, responsable_id }  → reasigna SIN cambiar estado (tickets).
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
if ($actor['tipo'] !== 'interno') {
    Http::fail('Solo personal interno', 403);
}
Auth::requireModulo('solicitudes');
Http::crud('solicitudes.gestion', 'asignar');
$in = Http::input();
$id  = (int) ($in['id'] ?? 0);
$rid = (int) ($in['responsable_id'] ?? 0);
if ($id <= 0 || $rid <= 0) {
    Http::fail('Faltan id o responsable_id');
}

Http::ok($service->reasignar($id, $rid, $actor));