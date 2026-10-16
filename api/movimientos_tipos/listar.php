<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/movimientos_tipos/listar?q=&order=&dir=&limit=&offset=
Http::requireMethod('GET');
$q = trim((string) ($_GET['q'] ?? ''));
$order = trim((string) ($_GET['order'] ?? 'nombre'));
$dir = trim((string) ($_GET['dir'] ?? 'ASC'));
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($service->listar($q, $order, $dir, $limit, $offset));