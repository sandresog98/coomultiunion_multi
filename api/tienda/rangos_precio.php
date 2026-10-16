<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Rangos de precio de Tienda (perfil tienda.rangos_precio)
Auth::requireModulo('tienda.rangos_precio');

function rgEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }

$action = trim((string) ($_GET['action'] ?? ''));
if (in_array($action, ['guardar', 'eliminar'], true)) {
    Http::crud('tienda.rangos_precio', match ($action) {
        'guardar'  => (int) (Http::input()['id'] ?? 0) > 0 ? 'editar' : 'crear',
        'eliminar' => 'eliminar',
    });
}

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($cat->listarRangosPrecio());

    case 'guardar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.rangos_precio');
        Http::ok($cat->guardarRangoPrecio(Http::input(), Auth::actor()), 201);

    case 'eliminar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.rangos_precio');
        $cat->eliminarRangoPrecio(rgEntero('id'), Auth::actor());
        Http::ok(['id' => rgEntero('id')]);
}

Http::fail('Acción no soportada.', 400);