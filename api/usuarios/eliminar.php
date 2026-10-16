<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/usuarios/eliminar.php  { id }  → baja lógica (estado_activo = 0)
Http::requireMethod('POST');
Http::crud('usuarios', 'eliminar');
Idempotencia::begin('usuarios');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}
$service->eliminar($actor, $id);
Http::ok(['deactivated' => true]);
