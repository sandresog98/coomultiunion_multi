<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/Storage/Storage.php';
// GET /api/portal/serve_documento.php?id= → descarga un documento propio (re-auth de dueño)
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
$d = $portal->documentoGet($id, $actor['cedula']);
Logger::descarga($actor['cedula'], 'portal.documentos', 'Documento #' . $id);
try {
    // MIME derivado de la extensión (nunca del tipo_mime de BD, que puede venir del cliente).
    $rutaDoc = (string) $d['ruta_archivo'];
    $ext = strtolower(pathinfo($rutaDoc, PATHINFO_EXTENSION));
    $mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
              'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    $mime = $mimes[$ext] ?? 'application/octet-stream';
    Storage::for('creditos')->serve('creditos/' . $rutaDoc, [
        'mime'        => $mime,
        'disposition' => isset($mimes[$ext]) ? 'inline' : 'attachment',
        'filename'    => basename((string) ($d['nombre_archivo'] ?? 'documento')),
    ]);
} catch (Throwable $e) {
    Http::fail('Archivo no disponible.', 404);
}
exit;