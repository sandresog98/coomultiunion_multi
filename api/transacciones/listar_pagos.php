<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/transacciones/listar_pagos?tipo=pse|confiar
Http::requireMethod('GET');
$tipo = trim((string) ($_GET['tipo'] ?? 'pse'));
if (!in_array($tipo, ['pse', 'confiar'], true)) { Http::fail('Tipo inválido'); }
Http::ok($service->listarPagosDisponibles($tipo));
