<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/movimientos_tipos/activos
Http::requireMethod('GET');
Http::ok($service->all(true));