<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/transacciones/listar_pagos?tipo=pse|confiar|todos&q=&origen=&limit=&offset=
Http::requireMethod('GET');
$tipo = trim((string) ($_GET['tipo'] ?? 'pse'));
if (!in_array($tipo, ['pse', 'confiar', 'todos', 'all'], true)) { Http::fail('Tipo inválido'); }

if ($tipo === 'todos' || $tipo === 'all') {
    $q = trim((string) ($_GET['q'] ?? ''));
    $origen = trim((string) ($_GET['origen'] ?? ''));
    if (!in_array($origen, ['', 'pse', 'confiar'], true)) { Http::fail('Origen inválido'); }
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = max(0, (int) ($_GET['offset'] ?? 0));
    Http::ok($service->listarPagosDisponiblesTodos($q, $origen, $limit, $offset));
}

Http::ok($service->listarPagosDisponibles($tipo));
