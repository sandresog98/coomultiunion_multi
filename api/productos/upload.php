<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Idempotencia.php';
require_once $root . '/core/Auth.php';

Auth::requireModulo('oficina.asociados');
Http::crud('asociados.detalle', 'adjuntar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Http::fail('Método no permitido', 405);
}

Idempotencia::begin('productos');

$file = $_FILES['archivo'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    Http::fail('No se recibió el archivo.');
}

$ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
if (!in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
    Http::fail('Formato no permitido. Use PDF, JPG o PNG.');
}

$max = (int) (getenv('UPLOAD_MAX_PDF_MB') ?: 10) * 1024 * 1024;
if (($file['size'] ?? 0) > $max) {
    Http::fail('Archivo demasiado grande. Máximo ' . (int)(getenv('UPLOAD_MAX_PDF_MB') ?: 10) . ' MB.');
}

$base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
if ($base[0] !== '/') { $base = dirname(__DIR__, 2) . '/' . $base; }

$rel = 'productos/' . date('Y/m');
$dirAbs = rtrim($base, '/') . '/' . ltrim($rel, '/');
if (!is_dir($dirAbs)) { @mkdir($dirAbs, 0775, true); }

$nombre = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destino = $dirAbs . '/' . $nombre;

if (!move_uploaded_file($file['tmp_name'], $destino)) {
    Http::fail('Error al guardar el archivo.');
}

$ruta = $rel . '/' . $nombre;
Http::ok(['ruta' => $ruta, 'nombre' => $nombre]);
