<?php
declare(strict_types=1);

/**
 * Ping/pong — endpoint mínimo de validación (health check).
 *
 *   GET  /api/ping.php  -> {"ok":true,"data":"pong"}
 *   POST /api/ping.php  -> {"ok":true,"data":"pong"}
 *
 * Si la petición incluye `Authorization: Bearer <token>`:
 *   - token válido   -> responde pong y audita el uso (last_used_at + logs);
 *   - token inválido/expirado -> 401.
 * Sin token es público (solo comprueba conectividad y método).
 */
$root = dirname(__DIR__);
require_once $root . '/core/Http.php';
require_once $root . '/core/TokenAuth.php';

// Valida el token SOLO si viene uno (si no, es público).
TokenAuth::boot();

$m = Http::method();
if ($m !== 'GET' && $m !== 'POST' && $m !== 'HEAD') {
    header('Allow: GET, POST, HEAD');
    Http::fail('Método no permitido', 405);
}

Http::ok('pong');
