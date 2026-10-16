<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/logs/LogService.php';

set_exception_handler(static function (Throwable $e): void {
    error_log('[logs-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

Auth::requireModulo('logs.gestion');
$service = new LogService();
