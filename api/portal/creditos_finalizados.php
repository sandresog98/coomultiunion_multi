<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/creditos_finalizados.php → créditos finalizados del asociado
Http::requireMethod('GET');
$all = $portal->creditos($actor['cedula']);
Http::ok($all['finalizados']);