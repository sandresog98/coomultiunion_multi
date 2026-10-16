<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Reversiones de Tienda (perfil tienda.reversiones)
Auth::requireModulo('tienda.reversiones');

function rvEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function rvTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }

$action = trim((string) ($_GET['action'] ?? ''));

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($tienda->listarReversiones(rvTexto('q'), rvTexto('fecha_desde'), rvTexto('fecha_hasta'),
            rvTexto('order', 'id'), rvTexto('dir', 'DESC'), min(200, max(1, rvEntero('limit', 20))), max(0, rvEntero('offset'))));

    case 'registrar':
        Http::requireMethod('POST');
        Http::crud('tienda.reversiones', 'revertir');
        Idempotencia::begin('tienda.reversiones');
        $data = Http::input();
        Http::ok($tienda->registrarReversion((int) ($data['venta_detalle_id'] ?? 0),
            (string) ($data['motivo'] ?? ''), Auth::actor()), 201);
}

Http::fail('Acción no soportada.', 400);