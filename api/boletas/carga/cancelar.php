<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/boletas/carga/cancelar → descarta la carga pendiente
Http::requireMethod('POST');
Http::crud('convenios.carga', 'procesar');
Idempotencia::begin('boletas.carga');
$carga->cancelar(isset(Auth::actor()['id']) ? (int) Auth::actor()['id'] : null);
Http::ok(['ok' => true]);