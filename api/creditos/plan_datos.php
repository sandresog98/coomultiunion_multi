<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/creditos/plan_datos?id=   (JSON)
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.');
// Parámetros opcionales del plan (emulan el modal legacy): fechas y "otros".
$opts = [];
foreach (['fecha_inicio', 'primer_pago', 'aportes', 'bolsillo', 'fondo_solidario'] as $k) {
    if (isset($_GET[$k])) $opts[$k] = $_GET[$k];
}
Http::ok($cred->planDatos($id, $opts));