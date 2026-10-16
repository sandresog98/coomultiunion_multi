<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/boletas/carga/analizar (multipart: archivo) → resume + deja filas en sesión
Http::requireMethod('POST');
Http::crud('convenios.carga', 'procesar');
$actor = Auth::actor();
$file = $_FILES['archivo'] ?? [];
Http::ok($carga->analizar($file, $actor), 201);