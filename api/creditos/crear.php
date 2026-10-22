<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/creditos/crear
Http::requireMethod('POST');
Http::crud('creditos.nueva_solicitud', 'crear');
Idempotencia::begin('creditos');
$id = $cred->crear(Http::input(), Auth::actor());
Http::ok(['id' => $id], 201);