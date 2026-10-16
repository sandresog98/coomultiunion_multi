<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/solicitudes/AdjuntoArchivo.php';

// POST /api/solicitudes/comentar.php
//   JSON:      { id, mensaje }
//   Multipart: { id, mensaje, files[] }  → comentario con uno o varios adjuntos.
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
$in    = Http::input();

$id      = (int) ($in['id'] ?? 0);
$mensaje = trim((string) ($in['mensaje'] ?? ''));
if ($id <= 0 || $mensaje === '') {
    Http::fail('Faltan id o mensaje');
}

// Aislamiento: un asociado solo comenta en sus solicitudes.
if ($actor['tipo'] === 'asociado') {
    $d = $service->detalle($id);
    if ((string) $d['solicitud']['cedula_solicitante'] !== (string) $actor['id']) {
        Http::fail('No autorizado', 403);
    }
} else {
    Auth::requireModulo('solicitudes');
    Http::crud('solicitudes.gestion', 'comentar');
}

// Adjuntos opcionales (solo llegan por multipart). El personal interno requiere permiso de adjuntar.
$adjuntos = [];
if (!empty($_FILES['files'])) {
    if (($actor['tipo'] ?? '') === 'interno') {
        Http::crud('solicitudes.gestion', 'adjuntar');
    }
    foreach (AdjuntoArchivo::normalizar($_FILES['files']) as $f) {
        $meta = AdjuntoArchivo::guardar($f, $id);
        if ($meta !== null) {
            $adjuntos[] = $meta;
        }
    }
}

Http::ok($service->comentar($id, $actor, $mensaje, $adjuntos));
