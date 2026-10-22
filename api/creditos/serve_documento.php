<?php
declare(strict_types=1);
// Endpoint abierto en navegador: sin sesión → redirige a la raíz (no JSON).
$GLOBALS['AUTH_REDIRECT_UNATH'] = '../../';
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';
// GET /api/creditos/serve_documento?id=
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.');
$d = $cred->getDocumento($id);
if (!$d) Http::fail('Documento no encontrado.', 404);
Logger::descarga(Auth::actor()['id'] ?? null, 'creditos.documentos', "Documento #$id");
try {
    Storage::for('creditos')->serve('creditos/' . $d['ruta_archivo'], [
        'mime'        => $d['tipo_mime'] ?: 'application/octet-stream',
        'disposition' => 'inline',
        'filename'    => basename((string) ($d['nombre_archivo'] ?? 'documento')),
    ]);
} catch (Throwable $e) {
    Http::fail('Archivo no disponible.', 404);
}
exit;