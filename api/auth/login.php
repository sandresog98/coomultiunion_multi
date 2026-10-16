<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Auth.php';

// POST /api/auth/login.php  { usuario, password }
Http::requireMethod('POST');
$in = Http::input();
$usuario  = trim((string) ($in['usuario'] ?? ''));
$password = (string) ($in['password'] ?? '');
if ($usuario === '' || $password === '') {
    Http::fail('Usuario y contraseña son obligatorios');
}

$s = Auth::login($usuario, $password);
if ($s === null) {
    Http::fail('Credenciales inválidas', 401);
}

Http::ok([
    'usuario'         => $s['usuario'],
    'nombre_completo' => $s['nombre_completo'],
    'rol'             => $s['rol'],
    'must_change'     => $s['must_change'],   // si true, la UI debe forzar cambio de contraseña
]);
