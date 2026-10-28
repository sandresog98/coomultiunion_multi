<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/productos/listar?basico=1 → tipos activos (productos + cláusulas)
Http::requireMethod('GET');
$basico = ($_GET['basico'] ?? '') === '1';
Http::ok($service->listarTiposActivos($basico));
