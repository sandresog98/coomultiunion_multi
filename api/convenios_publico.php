<?php
declare(strict_types=1);
/**
 * Público de convenios (B15 / B10) — SIN autenticación (precedente: api/simulacion.php).
 * GET /api/convenios_publico?action=listado   [q=&tipo=todas|con_productos|enlace&orden=nombre|productos&direccion=asc|desc&por_pagina=&pagina=]
 *   → convenios activos (búsqueda + tipo + orden, paginado; cada uno con imagen_url y total_productos)
 * GET /api/convenios_publico?action=detalle&id=N [q=&precio_min=&precio_max=&orden=nombre|precio&direccion=&por_pagina=&pagina=]
 *   → convenio + beneficios (búsqueda/rango de precio/orden, paginado)
 * GET /api/convenios_publico?action=imagen&id=N[&ent=producto] → imagen (whitelist + anti-traversal)
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/convenios/ConveniosPublicoService.php';
require_once __DIR__ . '/../core/Storage/Storage.php';

header('Content-Type: application/json; charset=utf-8');

$action = trim((string) ($_GET['action'] ?? 'listado'));
$svc = new ConveniosPublicoService();

function jsonOut(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

/** URL absoluta de la imagen servida por este endpoint público. */
function imagenUrl(string $base, int $id, string $ent): ?string
{
    return $base . 'api/convenios_publico.php?action=imagen&ent=' . $ent . '&id=' . $id;
}

// base del proyecto (raíz, SIN '/api/'): imagenUrl antepone 'api/convenios_publico.php'
$selfBase = (function (): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $pos = strpos($script, '/api/');
    $base = $pos !== false ? substr($script, 0, $pos + 1) : rtrim(dirname($script), '/') . '/';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $base;
})();

if ($action === 'imagen') {
    $ent = trim((string) ($_GET['ent'] ?? 'convenio'));
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonOut(['ok' => false, 'error' => 'Falta el id.']);
    $st = $ent === 'producto'
        ? db()->prepare("SELECT imagen FROM convenios_productos WHERE id = ? AND activo = 1")
        : db()->prepare("SELECT imagen FROM convenios WHERE id = ? AND activo = 1");
    $st->execute([$id]);
    $imagen = $st->fetchColumn();
    if (!$imagen) jsonOut(['ok' => false, 'error' => 'Sin imagen.']);
    $imagen = (string) $imagen;

    if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) {
        header('Location: ' . $imagen);
        exit;
    }
    // Whitelist + anti-traversal: solo basename opaco (sin /, sin ..).
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $imagen)) jsonOut(['ok' => false, 'error' => 'Archivo no válido.']);

    // Solo se sirve contenido del módulo PÚBLICO convenios (bucket público/local), NUNCA
    // objetos del bucket privado. Storage::for('convenios') aísla ese bucket.
    $key = 'convenios/' . $imagen;
    $storage = Storage::for('convenios');
    $publicUrl = $storage->url($key);
    if ($publicUrl !== null) {
        header('Location: ' . $publicUrl);
        exit;
    }
    try {
        header('X-Content-Type-Options: nosniff');
        $storage->serve($key, ['cache_control' => 'public, max-age=3600']);
        exit;
    } catch (Throwable $e) {
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="340"><rect width="100%" height="100%" fill="#e8eef5"/><text x="50%" y="50%" font-size="64" text-anchor="middle" fill="#b6c2cf">🤝</text></svg>';
        exit;
    }
}

$pagina = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina = min(50, max(1, (int) ($_GET['por_pagina'] ?? 12)));
$dir = strtoupper((string) ($_GET['direccion'] ?? 'asc')) === 'DESC' ? 'DESC' : 'ASC';

if ($action === 'detalle') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) jsonOut(['ok' => false, 'error' => 'Falta el id.']);
    $c = $svc->convenio($id);
    if (!$c) jsonOut(['ok' => false, 'error' => 'Convenio no encontrado.']);
    $c['imagen_url'] = !empty($c['imagen']) ? imagenUrl($selfBase, $id, 'convenio') : null;

    $q = trim((string) ($_GET['q'] ?? ''));
    $min = (float) ($_GET['precio_min'] ?? 0);
    $max = (float) ($_GET['precio_max'] ?? 0);
    $orden = trim((string) ($_GET['orden'] ?? 'nombre'));
    $r = $svc->productos($id, $q, $min, $max, $orden, $dir, $porPagina, ($pagina - 1) * $porPagina);

    $productos = array_map(function ($p) use ($selfBase) {
        $p['imagen_url'] = !empty($p['imagen']) ? imagenUrl($selfBase, (int) $p['id'], 'producto') : null;
        $p['valor_format'] = '$' . number_format((float) $p['valor'], 0, ',', '.');
        $p['valor_general_format'] = ($p['valor_general'] !== null && (float) $p['valor_general'] > 0)
            ? '$' . number_format((float) $p['valor_general'], 0, ',', '.') : null;
        return $p;
    }, $r['rows']);

    jsonOut(['ok' => true, 'data' => array_merge($c, [
        'productos' => $productos,
        'total_productos' => (int) $r['total'],
        'pagina' => $pagina,
        'por_pagina' => $porPagina,
    ])]);
}

// listado
$q = trim((string) ($_GET['q'] ?? ''));
$tipo = trim((string) ($_GET['tipo'] ?? 'todas'));
$orden = trim((string) ($_GET['orden'] ?? 'nombre'));
$r = $svc->listado($q, $orden, $dir, $tipo, $porPagina, ($pagina - 1) * $porPagina);

$items = array_map(function ($c) use ($selfBase) {
    $c['imagen_url'] = !empty($c['imagen']) ? imagenUrl($selfBase, (int) $c['id'], 'convenio') : null;
    $c['total_productos'] = (int) $c['total_productos'];
    return $c;
}, $r['rows']);

jsonOut(['ok' => true, 'data' => [
    'rows' => $items,
    'total' => (int) $r['total'],
    'pagina' => $pagina,
    'por_pagina' => $porPagina,
]]);