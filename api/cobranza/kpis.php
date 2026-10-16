<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/cobranza/kpis
Http::requireMethod('GET');
Http::ok($cob->kpis());