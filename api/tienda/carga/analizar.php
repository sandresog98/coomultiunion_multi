<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/tienda/carga/analizar (multipart: archivo)
Http::requireMethod('POST');
Http::crud('tienda.catalogo', 'carga');
Idempotencia::begin('tienda.catalogo.carga');
$file = $_FILES['archivo'] ?? [];
Http::ok($carga->analizar($file, $actor), 201);
