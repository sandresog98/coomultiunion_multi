<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Catálogo de Tienda — ent: productos|categorias|marcas (perfil tienda.catalogo)
Auth::requireModulo('tienda.catalogo');

$action = trim((string) ($_GET['action'] ?? ''));
$ent    = trim((string) ($_GET['ent'] ?? 'productos'));

if (in_array($action, ['guardar', 'toggle', 'toggle_agotado', 'eliminar'], true)) {
    Idempotencia::begin('tienda.catalogo');
    $op = match ($action) {
        'guardar'         => (int) (Http::input()['id'] ?? 0) > 0 ? 'editar' : 'crear',
        'toggle', 'toggle_agotado' => 'toggle',
        'eliminar'        => 'eliminar',
    };
    Http::crud('tienda.catalogo', $op);
}

function tEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function tTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }
function tDataInput(): array { return Http::input(); }

switch ($ent) {
    case 'categorias':
        switch ($action) {
            case 'listar':
                Http::requireMethod('GET');
                Http::ok($cat->listarCategorias(tTexto('q'), tTexto('estado'), tTexto('order', 'nombre'),
                    tTexto('dir', 'ASC'), min(200, max(1, tEntero('limit', 20))), max(0, tEntero('offset'))));
            case 'obtener':
                Http::requireMethod('GET');
                Http::ok($cat->obtenerCategoria(tEntero('id')) ?: Http::fail('Categoría no encontrada.', 404));
            case 'guardar':
                Http::requireMethod('POST');
                Http::ok($cat->guardarCategoria(tDataInput(), Auth::actor()), 201);
            case 'toggle':
                Http::requireMethod('POST');
                Http::ok($cat->toggleCategoria(tEntero('id'), Auth::actor()));
            case 'eliminar':
                Http::requireMethod('POST');
                $cat->eliminarCategoria(tEntero('id'), Auth::actor());
                Http::ok(['id' => tEntero('id')]);
            case 'activas':
                Http::requireMethod('GET');
                Http::ok($cat->listarCategoriasActivas());
        }
        break;

    case 'marcas':
        switch ($action) {
            case 'listar':
                Http::requireMethod('GET');
                Http::ok($cat->listarMarcas(tTexto('q'), tTexto('estado'), tTexto('order', 'nombre'),
                    tTexto('dir', 'ASC'), min(200, max(1, tEntero('limit', 20))), max(0, tEntero('offset'))));
            case 'obtener':
                Http::requireMethod('GET');
                Http::ok($cat->obtenerMarca(tEntero('id')) ?: Http::fail('Marca no encontrada.', 404));
            case 'guardar':
                Http::requireMethod('POST');
                Http::ok($cat->guardarMarca(tDataInput(), Auth::actor()), 201);
            case 'toggle':
                Http::requireMethod('POST');
                Http::ok($cat->toggleMarca(tEntero('id'), Auth::actor()));
            case 'eliminar':
                Http::requireMethod('POST');
                $cat->eliminarMarca(tEntero('id'), Auth::actor());
                Http::ok(['id' => tEntero('id')]);
            case 'activas':
                Http::requireMethod('GET');
                Http::ok($cat->listarMarcasActivas());
        }
        break;

    default: // productos
        switch ($action) {
            case 'listar':
                Http::requireMethod('GET');
                Http::ok($cat->listarProductos(tTexto('q'), tEntero('categoria_id'), tEntero('marca_id'), tTexto('estado'),
                    tTexto('agotado'), tTexto('order', 'nombre'), tTexto('dir', 'ASC'), min(200, max(1, tEntero('limit', 20))), max(0, tEntero('offset'))));
            case 'obtener':
                Http::requireMethod('GET');
                Http::ok($cat->obtenerProducto(tEntero('id')) ?: Http::fail('Producto no encontrado.', 404));
            case 'guardar':
                Http::requireMethod('POST');
                $data = tDataInput();
                $file = ($_FILES['foto'] ?? null);
                Http::ok($cat->guardarProducto($data, Auth::actor(), $file), 201);
            case 'toggle':
                Http::requireMethod('POST');
                Http::ok($cat->toggleProducto(tEntero('id'), Auth::actor()));
            case 'toggle_agotado':
                Http::requireMethod('POST');
                Http::ok($cat->toggleAgotado(tEntero('id'), Auth::actor()));
            case 'eliminar':
                Http::requireMethod('POST');
                $cat->eliminarProducto(tEntero('id'), Auth::actor());
                Http::ok(['id' => tEntero('id')]);
        }
}

Http::fail('Acción no soportada.', 400);