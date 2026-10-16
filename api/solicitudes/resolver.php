<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/solicitudes/resolver.php   { id, resultado: "aprobada"|"rechazada", comentario, ruta_archivo? }
// Responde una solicitud en gestión. El comentario es obligatorio (requiere_comentario=1).
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
if ($actor['tipo'] !== 'interno') {
    Http::fail('Solo personal interno', 403);
}
Auth::requireModulo('solicitudes');
Http::crud('solicitudes.gestion', 'acciones');
$in = Http::input();

$id        = (int) ($in['id'] ?? 0);
$resultado = (string) ($in['resultado'] ?? '');
if ($id <= 0 || !in_array($resultado, ['aprobada', 'rechazada'], true)) {
    Http::fail('Faltan id o resultado (aprobada/rechazada)');
}
$comentario = trim((string) ($in['comentario'] ?? ''));
if ($comentario === '') {
    Http::fail('El comentario es obligatorio para resolver');
}

$res = $service->transicionar($id, $resultado, $actor, $comentario, [
    'resultado'    => $resultado,
    'ruta_archivo' => $in['ruta_archivo'] ?? null,
]);
// Resolución (aprobada/rechazada) → aviso al asociado.
EmailCola::notificarSolicitudResuelta($res, $comentario);
Http::ok($res);