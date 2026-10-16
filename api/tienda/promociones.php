<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Promociones de Tienda (perfil tienda.promociones)
Auth::requireModulo('tienda.promociones');

function prEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function prTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }

$action = trim((string) ($_GET['action'] ?? ''));
if (in_array($action, ['guardar', 'toggle', 'eliminar'], true)) {
    Http::crud('tienda.promociones', match ($action) {
        'guardar'  => (int) (Http::input()['id'] ?? 0) > 0 ? 'editar' : 'crear',
        'toggle'   => 'toggle',
        'eliminar' => 'eliminar',
    });
}

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($tienda->listarPromociones(prTexto('q'), prEntero('producto_id'), prTexto('estado'), prTexto('solo_vigentes'),
            prTexto('order', 'id'), prTexto('dir', 'DESC'), min(200, max(1, prEntero('limit', 20))), max(0, prEntero('offset'))));

    case 'obtener':
        Http::requireMethod('GET');
        Http::ok($tienda->obtenerPromocion(prEntero('id')) ?: Http::fail('Promoción no encontrada.', 404));

    case 'guardar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.promociones');
        Http::ok($tienda->guardarPromocion(Http::input(), Auth::actor()), 201);

    case 'toggle':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.promociones');
        Http::ok($tienda->togglePromocion(prEntero('id'), Auth::actor()));

    case 'eliminar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.promociones');
        $tienda->eliminarPromocion(prEntero('id'), Auth::actor());
        Http::ok(['id' => prEntero('id')]);
}

Http::fail('Acción no soportada.', 400);