<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Fondo/FondoBienestarValidacion.php';

// Diagnóstico de elegibilidad para staff (plantilla de pre-chequeo/rechazo).
Http::requireMethod('GET');
$cedula = trim((string) ($_GET['cedula'] ?? ''));
$convenioId = (int) ($_GET['convenio_id'] ?? 0);
if ($cedula === '' || $convenioId <= 0) Http::fail('Faltan cedula o convenio_id.');
$val = new FondoBienestarValidacion();
$cert = $val->validarApto($cedula, $convenioId);
Http::ok([
    'validacion' => $cert,
    'pagos' => $val->obtenerEstadoPagos($cedula),
    'tiene_producto' => $val->tieneProductoAsignado($cedula),
]);