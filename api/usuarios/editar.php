<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/usuarios/editar.php  { id, nombre_completo, email?, rol, estado_activo }
Http::requireMethod('POST');
Http::crud('usuarios', 'editar');
Idempotencia::begin('usuarios');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}
$service->actualizar(
    $actor,
    $id,
    (string) ($in['nombre_completo'] ?? ''),
    isset($in['email']) ? (string) $in['email'] : null,
    (string) ($in['rol'] ?? ''),
    (bool) ($in['estado_activo'] ?? true)
);
Http::ok(['updated' => true]);
