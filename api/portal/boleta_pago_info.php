<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/portal/boleta_pago_info.php → elegibilidad del crédito cooperativa para boletas.
Http::requireMethod('GET');
Http::ok($portal->elegibleCreditoBoleta((string) $actor['cedula']));
