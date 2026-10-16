<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/configuracion/listar → { config: { clave: valor, ... } }
Http::requireMethod('GET');
Http::ok(['config' => $service->all()]);
