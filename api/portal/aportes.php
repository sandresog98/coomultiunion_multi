<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/aportes.php → resumen de aportes (vw_productos)
Http::requireMethod('GET');
Http::ok($portal->aportes($actor['cedula']));