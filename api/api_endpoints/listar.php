<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/api_endpoints/listar.php
// Devuelve el catálogo de claves (acciones + módulos de secciones.php) con su
// estado actual en la allowlist global.
Http::requireMethod('GET');

$catalogo = [];
foreach (Permisos::catalogoAcciones() as $k) {
    $catalogo[$k] = 'accion';
}
foreach (Permisos::catalogoModulos() as $k) {
    if (!isset($catalogo[$k])) {
        $catalogo[$k] = 'modulo';
    }
}
foreach (Permisos::catalogoScopesEspeciales() as $k) {
    if (!isset($catalogo[$k])) {
        $catalogo[$k] = 'especial';
    }
}

$estado = [];
foreach (db()->query('SELECT clave, descripcion, activo FROM api_endpoints_autorizados')->fetchAll() as $r) {
    $estado[(string) $r['clave']] = [
        'descripcion' => (string) ($r['descripcion'] ?? ''),
        'activo'      => (int) $r['activo'],
    ];
}

// Autorizados huérfanos (en tabla pero ya no en el catálogo): se incluyen igual.
foreach ($estado as $k => $e) {
    if (!isset($catalogo[$k])) {
        $catalogo[$k] = 'desconocido';
    }
}

$rows = [];
foreach ($catalogo as $clave => $tipo) {
    $rows[] = [
        'clave'       => $clave,
        'tipo'        => $tipo,
        'descripcion' => $estado[$clave]['descripcion'] ?? '',
        'autorizado'  => isset($estado[$clave]) && $estado[$clave]['activo'] === 1,
    ];
}
usort($rows, static fn($a, $b) => strcmp($a['clave'], $b['clave']));

Http::ok(['rows' => $rows]);
