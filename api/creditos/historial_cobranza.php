<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/creditos/historial_cobranza?id= → cédula de la solicitud (para enlazar a Cobranza)
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.');
Http::ok($cred->historialCobranza($id));