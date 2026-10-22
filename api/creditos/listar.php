<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/creditos/listar?q=&cedula=&estado=&etapa=&order=&dir=&limit=&offset=
Http::requireMethod('GET');
$cedula = trim((string) ($_GET['cedula'] ?? ''));
Http::ok($cred->listar(
    trim((string) ($_GET['q'] ?? '')),
    trim((string) ($_GET['estado'] ?? '')),
    trim((string) ($_GET['etapa'] ?? '')),
    trim((string) ($_GET['order'] ?? 'id')), trim((string) ($_GET['dir'] ?? 'DESC')),
    min(200, max(1, (int) ($_GET['limit'] ?? 20))), max(0, (int) ($_GET['offset'] ?? 0)),
    $cedula !== '' ? $cedula : null
));