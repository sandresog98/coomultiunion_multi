<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// Staff: sirve el PDF obligatorio de una solicitud (via detalle del motor).
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');

require_once $root . '/core/solicitudes/SolicitudService.php';
$svc = new SolicitudService();
$d = $svc->detalle($id);
$ruta = $d['detalle']['ruta_pdf_obligatorio'] ?? null;
if (!$ruta) Http::fail('PDF no disponible.', 404);

$base = getenv('UPLOAD_DIR') ?: ($root . '/uploads');
if ($base[0] !== '/') $base = $root . '/' . $base;
$abs = rtrim($base, '/') . '/' . $ruta;
if (!is_file($abs)) Http::fail('PDF no encontrado en disco.', 404);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($ruta) . '"');
readfile($abs);
exit;