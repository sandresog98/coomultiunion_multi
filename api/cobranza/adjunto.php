<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__, 2) . '/core/Storage/Storage.php';
// POST /api/cobranza/adjunto   (multipart: indagacion_id, archivo) → sube y asocia
// DELETE /api/cobranza/adjunto { id } → elimina adjunto
$method = $_SERVER['REQUEST_METHOD'];
Http::crud('cobranza.lista', 'adjuntar');
$actor = Auth::actor();

if ($method === 'POST') {
    Idempotencia::begin('cobranza');
    $indId = (int) ($_POST['indagacion_id'] ?? 0);
    if ($indId <= 0) Http::fail('Falta la indagación.');
    if (empty($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? 1) !== UPLOAD_ERR_OK) Http::fail('Archivo no recibido.');

    $orig = $_FILES['archivo']['name'];
    $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: '';
    $permitidas = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
                   'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    if (!isset($permitidas[$ext])) {
        Http::fail('Tipo de archivo no permitido. Use PDF o imagen (png/jpg/jpeg/gif/webp).', 400);
    }
    $maxMb = $ext === 'pdf' ? (int) (env('UPLOAD_MAX_PDF_MB') ?: 10) : (int) (env('UPLOAD_MAX_IMAGE_MB') ?: 5);
    if ((int) ($_FILES['archivo']['size'] ?? 0) > $maxMb * 1024 * 1024) {
        Http::fail("El archivo excede el tamaño máximo permitido ({$maxMb} MB).", 400);
    }
    $mime = $permitidas[$ext];
    $clave = substr(hash('sha256', (string) microtime(true)), 0, 12);
    $nombre = Storage::nombreOpaco('adj', (string) $indId, $clave, $ext);
    Storage::for('cobranza')->put('cobranza/' . $nombre, $_FILES['archivo']['tmp_name'], ['mime' => $mime]);
    $id = $ind->agregarAdjunto(
        $indId, (string) $orig, $nombre,
        $mime,
        (int) ($_FILES['archivo']['size'] ?? 0),
        $actor
    );
    Http::ok(['id' => $id], 201);

} elseif ($method === 'DELETE') {
    Idempotencia::begin('cobranza');
    $in = Http::input();
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) Http::fail('Falta el id.');
    $ind->eliminarAdjunto($id, $actor);
    Http::ok(['id' => $id]);
} else {
    Http::fail('Método no permitido', 405);
}