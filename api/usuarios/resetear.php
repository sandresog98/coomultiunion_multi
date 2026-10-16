<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/usuarios/resetear.php  { id }  → nueva contraseña temporal de un solo uso
Http::requireMethod('POST');
Http::crud('usuarios', 'resetear');
Idempotencia::begin('usuarios');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}
$pass = $service->resetear($actor, $id);
Http::ok(['password_temporal' => $pass]);
