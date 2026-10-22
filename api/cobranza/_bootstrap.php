<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';
require_once $root . '/core/TokenAuth.php';
require_once $root . '/core/cobranza/CobranzaService.php';
require_once $root . '/core/cobranza/ComunicacionService.php';
require_once $root . '/core/cobranza/IndagacionService.php';
require_once $root . '/core/cobranza/TipoComunicacionService.php';

set_exception_handler(static function (Throwable $e): void {
    if ($e instanceof DomainException) { Http::fail($e->getMessage(), 400); }
    error_log('[cobranza-api] ' . $e->getMessage());
    Http::fail('Error interno', 500);
});

TokenAuth::boot();
Auth::requireModulo('cobranza');
$cob = new CobranzaService();
$com = new ComunicacionService();
$ind = new IndagacionService();
$tip = new TipoComunicacionService();