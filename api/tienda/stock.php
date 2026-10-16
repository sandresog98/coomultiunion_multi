<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Stock / Inventario de Tienda (perfil tienda.inventario)
Auth::requireModulo('tienda.inventario');

function stEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function stTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }

$action = trim((string) ($_GET['action'] ?? ''));

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($tienda->listarStock(stTexto('q'), stEntero('categoria_id'), stEntero('marca_id'), stTexto('estado'),
            stTexto('order', 'nombre'), stTexto('dir', 'ASC'), min(200, max(1, stEntero('limit', 20))), max(0, stEntero('offset'))));

    case 'detalle_producto':
        Http::requireMethod('GET');
        Http::ok($tienda->detalleProductoStock(stEntero('id')) ?: Http::fail('Producto no encontrado.', 404));

    case 'series':
        Http::requireMethod('GET');
        Http::ok($tienda->listarSeriesDisponibles(stEntero('producto_id'), stTexto('q')));

    case 'catalogo_venta':
        Http::requireMethod('GET');
        Http::ok($tienda->catalogoVenta(stTexto('q'), stEntero('categoria_id'), stEntero('marca_id')));
}

Http::fail('Acción no soportada.', 400);