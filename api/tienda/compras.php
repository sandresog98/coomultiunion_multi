<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Compras de Tienda (perfil tienda.compras)
Auth::requireModulo('tienda.compras');

function coEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function coTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }

$action = trim((string) ($_GET['action'] ?? ''));
if (in_array($action, ['guardar', 'editar', 'eliminar'], true)) {
    Http::crud('tienda.compras', $action === 'guardar' ? 'crear' : $action);
}

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($tienda->listarCompras(coTexto('q'), coTexto('fecha_desde'), coTexto('fecha_hasta'),
            coTexto('order', 'id'), coTexto('dir', 'DESC'), min(200, max(1, coEntero('limit', 20))), max(0, coEntero('offset'))));

    case 'detalle':
        Http::requireMethod('GET');
        Http::ok($tienda->obtenerCompra(coEntero('id')) ?: Http::fail('Compra no encontrada.', 404));

    case 'guardar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.compras');
        Http::ok($tienda->guardarCompra(Http::input(), Auth::actor()), 201);

    case 'editar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.compras');
        Http::ok($tienda->editarCompra(coEntero('id'), Http::input(), Auth::actor()));

    case 'eliminar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.compras');
        $tienda->eliminarCompra(coEntero('id'), Auth::actor());
        Http::ok(['id' => coEntero('id')]);
}

Http::fail('Acción no soportada.', 400);