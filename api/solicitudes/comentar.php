<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/solicitudes/comentar.php   { id, mensaje }
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
$in    = Http::input();

$id      = (int) ($in['id'] ?? 0);
$mensaje = trim((string) ($in['mensaje'] ?? ''));
if ($id <= 0 || $mensaje === '') {
    Http::fail('Faltan id o mensaje');
}

// Aislamiento: un asociado solo comenta en sus solicitudes.
if ($actor['tipo'] === 'asociado') {
    $d = $service->detalle($id);
    if ((string) $d['solicitud']['cedula_solicitante'] !== (string) $actor['id']) {
        Http::fail('No autorizado', 403);
    }
} else {
    Auth::requireModulo('solicitudes');
    Http::crud('solicitudes.gestion', 'comentar');
}

Http::ok($service->comentar($id, $actor, $mensaje));