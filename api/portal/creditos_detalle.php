<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/creditos_detalle.php?numero= → detalle de un crédito del asociado
Http::requireMethod('GET');
$numero = trim((string) ($_GET['numero'] ?? ''));
if ($numero === '') {
    Http::fail('Falta el número de crédito.');
}
Http::ok($portal->creditoDetalle($actor['cedula'], $numero));