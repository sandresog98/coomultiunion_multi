<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/movimientos_tipos/crear { tipo, nombre, clasificacion, requiere_referido?, valida_disponible?, activo? }
Http::requireMethod('POST');
Http::crud('movimientos_tipos', 'crear');
Idempotencia::begin('movimientos.tipos');
$out = $service->crear(Http::input(), Auth::actor());
Http::ok(['tipo' => $out], 201);