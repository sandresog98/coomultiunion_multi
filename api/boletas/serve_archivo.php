<?php
declare(strict_types=1);
// Endpoint abierto en navegador: sin sesión → redirige a la raíz (no JSON).
$GLOBALS['AUTH_REDIRECT_UNATH'] = '../../';
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/Storage/Storage.php';

// POST /api/boletas/serve_archivo (multipart/form  o  json)  → archivo con re-auth por contraseña.
Http::requireMethod('POST');
$in = Http::input();
$boletaId = (int) ($in['boleta_id'] ?? 0);
$password = (string) ($in['password'] ?? '');
$action = (($in['action'] ?? 'view') === 'download') ? 'download' : 'view';
if ($boletaId <= 0 || $password === '') Http::fail('Datos incompletos.');

// Re-autenticación por contraseña (nunca confiar solo en la sesión).
$staff = Auth::staff();
$st = db()->prepare("SELECT password FROM usuarios WHERE id = ? AND estado_activo = 1");
$st->execute([$staff['id']]);
$hash = $st->fetchColumn();
if (!$hash || !password_verify($password, (string) $hash)) Http::fail('Contraseña incorrecta.', 401);

$boleta = null;
try { $boleta = $stock->obtener($boletaId); } catch (Throwable $e) { $boleta = null; }
if (!$boleta) Http::fail('Boleta no encontrada o sin archivo.', 404);
if (empty($boleta['archivo_ruta'])) Http::fail('La boleta no tiene archivo adjunto.', 404);
if (!in_array($boleta['estado'], ['vendida', 'contabilizada'], true)) {
    Http::fail('El documento solo está disponible después de vender la boleta.', 403);
}

$nombre = basename((string) $boleta['archivo_ruta']);
if (!preg_match('/^[A-Za-z0-9_.-]+$/', $nombre)) Http::fail('Archivo no válido.', 404);

// Auditoría + evento de descarga.
Logger::log('descargar', 'boletas.archivos', $staff['id'],
    'Descarga de boleta #' . $boletaId . ' (Serial: ' . $boleta['serial'] . ', ' . $action . ')');
try {
    $stock->registrarEventoDescarga($boletaId, (int) $staff['id'], $action);
} catch (Throwable $ignored) {}

$ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'pdf' => 'application/pdf',
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    default => 'application/octet-stream',
};

try {
    Storage::for('boletas')->serve('boletas/' . $nombre, [
        'mime'         => $mime,
        'disposition'  => ($action === 'download' ? 'attachment' : 'inline'),
        'filename'     => $nombre,
        'no_store'     => true,
    ]);
} catch (Throwable $e) {
    Http::fail('El archivo no se encuentra en el servidor.', 404);
}
exit;