<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/cobranza/tipos?q=&limit=&offset=  (listado admin / selects)
Http::requireMethod('GET');
$q = trim((string) ($_GET['q'] ?? ''));
$solo_opciones = ($_GET['opciones'] ?? '') === '1';
if ($solo_opciones) { Http::ok($tip->all(true)); }
$order = trim((string) ($_GET['order'] ?? 'tipo'));
$dir = trim((string) ($_GET['dir'] ?? 'ASC'));
Http::ok($tip->listar($q, $order, $dir, min(200, max(1, (int) ($_GET['limit'] ?? 50))), max(0, (int) ($_GET['offset'] ?? 0))));