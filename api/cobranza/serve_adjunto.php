<?php
declare(strict_types=1);
// Endpoint abierto en navegador: sin sesión → redirige a la raíz (no JSON).
$GLOBALS['AUTH_REDIRECT_UNATH'] = '../../';
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';
// GET /api/cobranza/serve_adjunto?id=
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id del adjunto.');
$a = $ind->getAdjuntoPorId($id);
if (!$a) Http::fail('Adjunto no encontrado.', 404);
$mime = match (strtolower(pathinfo((string) $a['ruta_archivo'], PATHINFO_EXTENSION))) {
    'pdf' => 'application/pdf',
    'png' => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    default => 'application/octet-stream',
};
try {
    Storage::for('cobranza')->serve('cobranza/' . $a['ruta_archivo'], [
        'mime'        => $mime,
        'disposition' => 'inline',
        'filename'    => basename((string) ($a['nombre_archivo'] ?? 'adjunto')),
    ]);
} catch (Throwable $e) {
    Http::fail('Archivo no disponible.', 404);
}
exit;