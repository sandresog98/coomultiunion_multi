<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/usuarios/crear.php  { usuario, nombre_completo, email?, rol }
Http::requireMethod('POST');
Http::crud('usuarios', 'crear');
Idempotencia::begin('usuarios');
$in = Http::input();
$r = $service->crear(
    (string) ($in['usuario'] ?? ''),
    (string) ($in['nombre_completo'] ?? ''),
    isset($in['email']) ? (string) $in['email'] : null,
    (string) ($in['rol'] ?? ''),
    $actor
);
// La contraseña generada (de un solo uso) se devuelve UNA vez para entregarla al usuario.
Http::ok(['id' => $r['id'], 'password_temporal' => $r['password']], 201);
