<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/portal/aportes/retiro_archivo.php?id=&tipo=retiro|cert
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
$tipo = (string) ($_GET['tipo'] ?? '');
$col = $tipo === 'retiro' ? 'ruta_pdf_retiro' : ($tipo === 'cert' ? 'ruta_pdf_certificado' : '');

if ($id <= 0 || $col === '') {
    Http::fail('Faltan id o tipo');
}

$arch = $retiro->archivoPdf($cedula, $id, $col);
if (!$arch || !$arch['existe']) {
    Http::fail('Documento no encontrado', 404);
}

// Contención anti-traversal: solo dentro de uploads base.
$rootAbs = $retiro->uploadsRoot(); // ruta absoluta de uploads/
$real = realpath($arch['absoluta']);
if ($real === false || strpos($real, realpath($rootAbs) . DIRECTORY_SEPARATOR) !== 0) {
    Http::fail('Acceso denegado', 403);
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . basename($arch['relativa']) . '"');
header('X-Content-Type-Options: nosniff');
header('Content-Length: ' . filesize($real));
readfile($real);
exit;