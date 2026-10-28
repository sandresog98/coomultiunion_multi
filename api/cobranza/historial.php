<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/cobranza/historial?cedula=
// Historial unificado para la ficha del asociado: comunicaciones, indagaciones
// y compromisos en una sola petición (comparte conexión y bootstrap).
Http::requireMethod('GET');
$ced = trim((string) ($_GET['cedula'] ?? ''));
if ($ced === '') {
    Http::fail('Falta cédula.');
}
Http::ok([
    'comunicaciones' => $com->listarPorCedula($ced),
    'indagaciones'   => $ind->listarPorCedula($ced),
    'compromisos'    => $com->historialCompromisos($ced),
]);
