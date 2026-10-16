<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/lineas.php → líneas de crédito activas para el formulario
Http::requireMethod('GET');
Http::ok($portal->lineas());