<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/boletas/carga/pendientes → filas pendientes de la carga en curso
Http::requireMethod('GET');
Http::ok($carga->pendientes());