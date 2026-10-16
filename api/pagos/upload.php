<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';
// POST /api/pagos/upload  (multipart: archivo)
// Guarda en el storage del módulo pagos (R2 o Local) bajo pagos/YYYY/MM/ y devuelve {ruta}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { Http::fail('Método no permitido', 405); }
Http::crud('pagos', 'upload');

Idempotencia::begin('pagos');

$file = $_FILES['archivo'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    Http::fail('No se recibió el archivo.');
}

$ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
    Http::fail('Formato no permitido. Use PDF, JPG o PNG.');
}

$max = (int) (getenv('UPLOAD_MAX_IMAGE_MB') ?: 5) * 1024 * 1024;
if (($file['size'] ?? 0) > $max) {
    Http::fail('Archivo demasiado grande.');
}

$rel = 'pagos/' . date('Y/m');
$nombre = Storage::nombreOpaco('pago', date('Ym'), bin2hex(random_bytes(8)), $ext);
$key = $rel . '/' . $nombre;

Storage::for('pagos')->put($key, $file['tmp_name'], ['mime' => $file['type'] ?? 'application/octet-stream']);

$actor = Auth::actor();
Logger::log('subir', 'pagos.comprobante', $actor['id'] ?? null, "Subido comprobante de pago ($ext, " . number_format((int) $file['size']) . " B) → $key");
Http::ok(['ruta' => date('Y/m') . '/' . $nombre]);
