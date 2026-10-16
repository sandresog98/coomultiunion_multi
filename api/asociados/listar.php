<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/asociados/listar?q=&estado=&creditos=&productos=&limit=&offset=&order=&dir=
Http::requireMethod('GET');
$q = trim((string) ($_GET['q'] ?? ''));
$estado = trim((string) ($_GET['estado'] ?? ''));
$creditos = trim((string) ($_GET['creditos'] ?? ''));
$productos = trim((string) ($_GET['productos'] ?? ''));
$limit = min(200, max(1, (int) ($_GET['limit'] ?? 20)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
$orderBy = trim((string) ($_GET['order'] ?? 'nombre'));
$orderDir = trim((string) ($_GET['dir'] ?? 'ASC'));
Http::ok($service->listar($q, $estado, $creditos, $productos, $limit, $offset, $orderBy, $orderDir));
