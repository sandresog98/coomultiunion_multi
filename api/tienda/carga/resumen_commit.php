<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/tienda/carga/resumen_commit
Http::requireMethod('GET');
Http::ok($carga->resumenCommit());
