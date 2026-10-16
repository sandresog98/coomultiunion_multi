<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Ventas de Tienda (perfil tienda.ventas)
Auth::requireModulo('tienda.ventas');

function veEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function veTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }

$action = trim((string) ($_GET['action'] ?? ''));

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($tienda->listarVentas(veTexto('q'), veTexto('tipo_cliente'), veTexto('fecha_desde'), veTexto('fecha_hasta'),
            veTexto('ceco'), veTexto('order', 'id'), veTexto('dir', 'DESC'), min(200, max(1, veEntero('limit', 20))), max(0, veEntero('offset'))));

    case 'detalle':
        Http::requireMethod('GET');
        Http::ok($tienda->obtenerVenta(veEntero('id')) ?: Http::fail('Venta no encontrada.', 404));

    case 'guardar':
        Http::requireMethod('POST');
        Http::crud('tienda.ventas', 'crear');
        Idempotencia::begin('tienda.ventas');
        Http::ok($tienda->guardarVenta(Http::input(), Auth::actor()), 201);
}

Http::fail('Acción no soportada.', 400);