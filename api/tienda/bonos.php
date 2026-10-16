<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Bonos de Tienda (perfil tienda.reversiones — gestión de bonos)
Auth::requireModulo('tienda.reversiones');

function bnEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function bnTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }

$action = trim((string) ($_GET['action'] ?? ''));

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($tienda->listarBonos(bnTexto('q'), bnTexto('estado'), bnTexto('order', 'id'), bnTexto('dir', 'DESC'),
            min(200, max(1, bnEntero('limit', 20))), max(0, bnEntero('offset'))));

    case 'obtener':
        Http::requireMethod('GET');
        Http::ok($tienda->obtenerBono(bnEntero('id')) ?: Http::fail('Bono no encontrado.', 404));

    case 'disponibles':
        Http::requireMethod('GET');
        Http::ok($tienda->bonosDisponiblesPara(
            bnTexto('cedula') !== '' ? bnTexto('cedula') : null,
            bnEntero('cliente_id') > 0 ? bnEntero('cliente_id') : null
        ));
}

Http::fail('Acción no soportada.', 400);