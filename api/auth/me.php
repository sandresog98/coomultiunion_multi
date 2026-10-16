<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Auth.php';

// GET /api/auth/me.php  → sesión actual
Http::requireMethod('GET');
$s = Auth::staff();
if ($s === null) {
    Http::fail('No autenticado', 401);
}
Http::ok([
    'usuario'         => $s['usuario'],
    'nombre_completo' => $s['nombre_completo'],
    'rol'             => $s['rol'],
    'must_change'     => $s['must_change'],
]);
