<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/cumpleanos/listar?q=&mes=1..12&limit=&offset=
Http::requireMethod('GET');
$q      = trim((string) ($_GET['q'] ?? ''));
$mes    = (int) ($_GET['mes'] ?? 0);
$limit  = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($service->listar($q, $mes, $limit, $offset));
