<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';

// POST /api/solicitudes/adjuntar.php
//   Multipart (form-data): { id, contexto, tipo_documento?, file }  — guarda el archivo.
//   JSON: { id, contexto, tipo?, ruta_archivo, nombre_original?, tipo_mime? } — registra ruta externa.
Http::requireMethod('POST');
if (($actor['tipo'] ?? '') === 'interno') {
    Http::crud('solicitudes.gestion', 'adjuntar');
}
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}

$adj = [
    'contexto' => $in['contexto'] ?? 'solicitud',
    'tipo'     => $in['tipo'] ?? 'imagen',
];

// Caso archivo subido (multipart).
if (isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
    $f = $_FILES['file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: '';
    // Whitelist estricta (deny-by-default): solo PDF e imágenes seguras. Evita subir HTML/SVG/scripts.
    $permitidas = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg',
                   'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    if (!isset($permitidas[$ext])) {
        Http::fail('Tipo de archivo no permitido. Use PDF o imagen (png/jpg/jpeg/gif/webp).', 400);
    }
    // Límite de tamaño por entorno.
    $maxMb = $ext === 'pdf' ? (int) (env('UPLOAD_MAX_PDF_MB') ?: 10) : (int) (env('UPLOAD_MAX_IMAGE_MB') ?: 5);
    if ((int) $f['size'] > $maxMb * 1024 * 1024) {
        Http::fail("El archivo excede el tamaño máximo permitido ({$maxMb} MB).", 400);
    }
    $mime    = $permitidas[$ext];
    $tipoAdj = $ext === 'pdf' ? 'pdf' : 'imagen';

    $subdir = date('Y/m');
    $clave = substr(md5((string) $id . uniqid('', true)), 0, 12);
    $nombre = Storage::nombreOpaco('sol', (string) $id, $clave, $ext);
    $key = 'solicitudes/' . $subdir . '/' . $nombre;
    // MIME derivado de la extensión (nunca del cliente) → evita XSS vía tipo_mime.
    Storage::for('solicitudes')->put($key, $f['tmp_name'], ['mime' => $mime]);
    $adj['tipo']            = $tipoAdj;
    $adj['tipo_documento']  = $in['tipo_documento'] ?? null;
    $adj['nombre_original'] = $f['name'];
    $adj['nombre_archivo']  = $nombre;
    $adj['ruta_archivo']    = 'solicitudes/' . $subdir . '/' . $nombre;
    $adj['tamano_archivo']  = $f['size'];
    $adj['tipo_mime']       = $mime;
} else {
    // Registro de ruta externa (enlace o documento ya generado).
    $ruta = trim((string) ($in['ruta_archivo'] ?? ''));
    if ($ruta === '') {
        Http::fail('Falta file o ruta_archivo');
    }
    $adj['tipo']            = $in['tipo'] ?? 'enlace';
    $adj['tipo_documento']  = $in['tipo_documento'] ?? null;
    $adj['nombre_original'] = $in['nombre_original'] ?? basename($ruta);
    $adj['ruta_archivo']    = $ruta;
    $adj['tipo_mime']       = $in['tipo_mime'] ?? null;
}

Http::ok($service->adjuntar($id, $actor, $adj), 201);