<?php
declare(strict_types=1);
/**
 * Público de Tienda (B15 / B8) — SIN autenticación (precedente: api/convenios_publico.php).
 * GET /api/tienda_publico?action=catalogo         → catálogo activo (informativo)
 * GET /api/tienda_publico?action=producto&id=N    → detalle de un producto activo
 * GET /api/tienda_publico?action=categorias       → categorías activas
 * GET /api/tienda_publico?action=marcas           → marcas activas
 * GET /api/tienda_publico?action=imagen&id=N      → foto (whitelist + anti-traversal)
 */
require_once __DIR__ . '/../core/database.php';
require_once __DIR__ . '/../core/tienda/TiendaPublicoService.php';

header('Content-Type: application/json; charset=utf-8');

function pubJsonOut(array $d): void { echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }

$action   = trim((string) ($_GET['action'] ?? 'catalogo'));
$service  = new TiendaPublicoService();

// base del proyecto (raíz absoluta SIN '/api/', porque fotoUrl() antepone 'api/…')
$selfBase = (function (): string {
    $script = $_SERVER['SCRIPT_NAME'] ?? '/';
    $pos = strpos($script, '/api/');
    $base = $pos !== false ? substr($script, 0, $pos) : rtrim(dirname($script), '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $base . '/';
})();

function fotoUrl(string $base, int $id): string { return $base . 'api/tienda_publico.php?action=imagen&id=' . $id; }

if ($action === 'imagen') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) pubJsonOut(['ok' => false, 'error' => 'Falta el id.']);
    $p = $service->producto($id);
    if (!$p || empty($p['foto_path'])) {
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="100%" height="100%" fill="#e8eef5"/><text x="50%" y="50%" font-size="72" text-anchor="middle" fill="#b6c2cf">📦</text></svg>';
        exit;
    }
    $imagen = (string) $p['foto_path'];
    if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) {
        header('Location: ' . $imagen);
        exit;
    }
    // Whitelist + anti-traversal: solo basename opaco.
    if (!preg_match('/^[A-Za-z0-9_.-]+$/', $imagen)) pubJsonOut(['ok' => false, 'error' => 'Archivo no válido.']);
    $path = $service->uploadsTiendaDir() . '/' . $imagen;
    if (!is_file($path)) {
        header('Content-Type: image/svg+xml');
        echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600"><rect width="100%" height="100%" fill="#e8eef5"/><text x="50%" y="50%" font-size="72" text-anchor="middle" fill="#b6c2cf">📦</text></svg>';
        exit;
    }
    $mime = mime_content_type($path) ?: 'image/jpeg';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: public, max-age=3600');
    readfile($path);
    exit;
}

if ($action === 'producto') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) pubJsonOut(['ok' => false, 'error' => 'Falta el id.']);
    $p = $service->producto($id);
    if (!$p) pubJsonOut(['ok' => false, 'error' => 'Producto no encontrado.']);
    $p['foto_url'] = !empty($p['foto_path']) ? fotoUrl($selfBase, (int) $p['id']) : null;
    pubJsonOut(['ok' => true, 'data' => $p]);
}

if ($action === 'categorias') {
    pubJsonOut(['ok' => true, 'data' => $service->categorias()]);
}
if ($action === 'marcas') {
    pubJsonOut(['ok' => true, 'data' => $service->marcas()]);
}

// catalogo (listado paginado, informativo)
$categoriaId = (int) ($_GET['categoria_id'] ?? 0);
$marcaId     = (int) ($_GET['marca_id'] ?? 0);
$nombre      = trim((string) ($_GET['nombre'] ?? ''));
$min         = (float) ($_GET['precio_min'] ?? 0);
$max         = (float) ($_GET['precio_max'] ?? 0);
$orden       = trim((string) ($_GET['orden'] ?? 'destacado'));
$dir         = strtoupper(trim((string) ($_GET['direccion'] ?? 'asc'))) === 'DESC' ? 'desc' : 'asc';
$pagina      = max(1, (int) ($_GET['pagina'] ?? 1));
$porPagina   = min(50, max(1, (int) ($_GET['por_pagina'] ?? 12)));

$result = $service->catalogo($categoriaId, $marcaId, $nombre, $min, $max, $orden, $dir, $porPagina, ($pagina - 1) * $porPagina);
foreach ($result['rows'] as &$r) {
    $r['foto_url'] = !empty($r['foto_path']) ? fotoUrl($selfBase, (int) $r['id']) : null;
    $r['precio_venta_format'] = '$' . number_format((float) $r['precio_venta'], 0, ',', '.');
    $r['precio_multiunion_format'] = $r['precio_multiunion'] !== null ? '$' . number_format((float) $r['precio_multiunion'], 0, ',', '.') : null;
    $r['precio_descuento_format'] = $r['precio_descuento'] !== null ? '$' . number_format((float) $r['precio_descuento'], 0, ',', '.') : null;
}
pubJsonOut(['ok' => true, 'data' => [
    'rows' => $result['rows'],
    'total' => $result['total'],
    'pagina' => $pagina,
    'por_pagina' => $porPagina,
]]);