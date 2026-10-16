<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/usuarios/listar.php?q=&limit=&offset=
Http::requireMethod('GET');
$q = trim((string) ($_GET['q'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 20);
$offset = (int) ($_GET['offset'] ?? 0);
Http::ok($service->listarPaginado($q, max(1, min(200, $limit)), max(0, $offset)));
