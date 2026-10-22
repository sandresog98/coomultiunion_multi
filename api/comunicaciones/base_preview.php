<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * POST /api/comunicaciones/base_preview
 * multipart: archivo (CSV/XLS/XLSX), canal
 * JSON:      { canal, destinatarios:[...] }
 */
Http::requireMethod('POST');
Http::crud('comunicaciones.nueva', 'cargar_base');

$in = Http::input();
$canal = trim((string) ($in['canal'] ?? ''));
if (!in_array($canal, ['sms', 'whatsapp', 'email'], true)) {
    Http::fail('Canal inválido.');
}

if (!empty($_FILES['archivo']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $file = $_FILES['archivo'];
    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $maxMb = 10;
    if ((int) $file['size'] > $maxMb * 1024 * 1024) {
        Http::fail("El archivo excede el máximo permitido ({$maxMb} MB).");
    }
    $lectura = $importador->leerArchivo((string) $file['tmp_name'], $ext);
    if (!$lectura['rows']) {
        Http::fail('El archivo no contiene registros.');
    }
    $validacion = $importador->validar($lectura['rows'], $canal, 500);
    Http::ok([
        'archivo' => $lectura['archivo'],
        'headers' => $lectura['headers'],
        'rows' => $validacion['rows'],
        'resumen' => $validacion['resumen'],
        'total' => $validacion['total'],
    ]);
}

$destinatarios = $in['destinatarios'] ?? [];
if (is_string($destinatarios)) {
    $destinatarios = json_decode($destinatarios, true);
}
if (!is_array($destinatarios) || !$destinatarios) {
    Http::fail('Adjunte un archivo o envíe destinatarios.');
}
$rows = [];
$resumen = ['total' => count($destinatarios), 'ok' => 0, 'error' => 0, 'omitido' => 0];
foreach ($destinatarios as $r) {
    if (!is_array($r)) { continue; }
    $row = $importador->validarFila($r, $canal);
    $rows[] = $row;
    $resumen[$row['estado']] = ($resumen[$row['estado']] ?? 0) + 1;
    if ($row['estado'] === 'omitido') {
        $resumen[$row['motivo']] = ($resumen[$row['motivo']] ?? 0) + 1;
    }
}
Http::ok(['archivo' => null, 'headers' => [], 'rows' => $rows, 'resumen' => $resumen, 'total' => count($rows)]);
