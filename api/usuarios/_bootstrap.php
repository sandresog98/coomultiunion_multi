<?php
declare(strict_types=1);

/**
 * Bootstrap de la administración de usuarios (requiere permiso usuarios.gestion).
 */
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/UsuarioService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) {
        Http::fail($e->getMessage(), 400);
    }
    error_log('[usuarios-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

$staff = Auth::requireModulo('usuarios.gestion');
$actor = ['id' => (string) $staff['id'], 'rol' => $staff['rol']];
$service = new UsuarioService();
