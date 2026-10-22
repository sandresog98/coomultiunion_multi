<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// Sirve el PDF obligatorio de una solicitud del asociado (tras verificar dueño).
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
$pdf = $fondo->archivoPdf($cedula, $id);
if (!$pdf || !$pdf['existe']) Http::fail('PDF no disponible.', 404);
Logger::descarga($cedula, 'portal.fondo', 'PDF obligatorio solicitud #' . $id);
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($pdf['relativa']) . '"');
header('X-Accel-Redirect: /' . $pdf['relativa']);
readfile($pdf['absoluta']);
exit;