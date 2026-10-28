<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/cargas/listar.php  → historial + tipos soportados
Http::requireMethod('GET');
Http::ok([
    'tipos'     => $service->tipos(),
    'etiquetas' => $service->etiquetas(),
    'historial' => $service->historial(30),
]);
