<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/api_endpoints/toggle.php  { clave, activo:0|1, descripcion? }
// Autoriza / desautoriza una clave para poder invocarse por token.
Http::requireMethod('POST');
Http::crud('api.authorized_endpoints', 'toggle');
Idempotencia::begin('api.endpoints');

$in     = Http::input();
$clave  = trim((string) ($in['clave'] ?? ''));
$activo = (int) ($in['activo'] ?? 0) === 1 ? 1 : 0;
$desc   = trim((string) ($in['descripcion'] ?? ''));

if ($clave === '') {
    Http::fail('Clave requerida');
}
$valid = array_merge(
    Permisos::catalogoAcciones(),
    Permisos::catalogoModulos(),
    Permisos::catalogoScopesEspeciales()
);
if (!in_array($clave, $valid, true)) {
    Http::fail('La clave no existe en el catálogo de permisos');
}

if ($activo === 1) {
    // Si no se envía descripción, se conserva la existente (COALESCE sobre el
    // valor previo de la columna).
    db()->prepare(
        'INSERT INTO api_endpoints_autorizados (clave, descripcion, activo, fecha_actualizacion)
         VALUES (?,?,1,NOW())
         ON DUPLICATE KEY UPDATE
            descripcion = COALESCE(?, descripcion),
            activo = 1,
            fecha_actualizacion = NOW()'
    )->execute([$clave, $desc !== '' ? $desc : null, $desc !== '' ? $desc : null]);
} else {
    db()->prepare(
        'UPDATE api_endpoints_autorizados SET activo = 0, fecha_actualizacion = NOW() WHERE clave = ?'
    )->execute([$clave]);
}

Logger::log(
    $activo === 1 ? 'crear' : 'eliminar',
    'api.endpoints',
    (string) $staff['id'],
    ($activo === 1 ? 'Autorizado' : 'Desautorizado') . ' endpoint: ' . $clave
);

Http::ok(['clave' => $clave, 'activo' => $activo]);
