<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/productos/listar → tipos activos (productos + cláusulas)
Http::requireMethod('GET');
Http::ok($service->listarTiposActivos());
