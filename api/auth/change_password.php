<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/UsuarioService.php';

// POST /api/auth/change_password.php  { actual, nueva }
Http::requireMethod('POST');
$s = Auth::staff();
if ($s === null) {
    Http::fail('No autenticado', 401);
}
Http::csrf();
$in = Http::input();
$actual = (string) ($in['actual'] ?? '');
$nueva  = (string) ($in['nueva'] ?? '');
$confirmar = (string) ($in['confirmar'] ?? $nueva);

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[change_password] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

if ($actual === '') {
    Http::fail('Debe indicar la contraseña actual.');
}
if ($nueva === '') {
    Http::fail('Debe indicar la nueva contraseña.');
}
if ($confirmar !== $nueva) {
    Http::fail('La confirmación de la nueva contraseña no coincide.');
}
(new UsuarioService())->cambiarPassword((int) $s['id'], $actual, $nueva, false);

// refresca la sesión (ya no requiere cambio)
Auth::logout();
Http::ok(['changed' => true, 'relogin' => true]);
