<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// API Clientes de Tienda (perfil tienda.clientes)
Auth::requireModulo('tienda.clientes');

function cliEntero(string $k, int $d = 0): int { return (int) ($_GET[$k] ?? $d); }
function cliTexto(string $k, string $d = ''): string { return trim((string) ($_GET[$k] ?? $d)); }

$action = trim((string) ($_GET['action'] ?? ''));
if (in_array($action, ['guardar', 'toggle', 'eliminar'], true)) {
    Http::crud('tienda.clientes', match ($action) {
        'guardar'  => (int) (Http::input()['id'] ?? 0) > 0 ? 'editar' : 'crear',
        'toggle'   => 'toggle',
        'eliminar' => 'eliminar',
    });
}

switch ($action) {
    case 'listar':
        Http::requireMethod('GET');
        Http::ok($cli->listar(cliTexto('q'), cliTexto('tipo'), cliTexto('estado'), cliTexto('order', 'nombre'),
            cliTexto('dir', 'ASC'), min(200, max(1, cliEntero('limit', 20))), max(0, cliEntero('offset'))));

    case 'obtener':
        Http::requireMethod('GET');
        Http::ok($cli->obtener(cliEntero('id')) ?: Http::fail('Cliente no encontrado.', 404));

    case 'guardar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.clientes');
        Http::ok($cli->guardar(Http::input(), Auth::actor()), 201);

    case 'toggle':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.clientes');
        Http::ok($cli->toggleEstado(cliEntero('id'), Auth::actor()));

    case 'eliminar':
        Http::requireMethod('POST');
        Idempotencia::begin('tienda.clientes');
        $cli->eliminar(cliEntero('id'), Auth::actor());
        Http::ok(['id' => cliEntero('id')]);

    case 'externos':
        Http::requireMethod('GET');
        Http::ok($cli->listarExternosActivos());

    case 'buscar_asociados':
        Http::requireMethod('GET');
        Http::ok($cli->buscarAsociados(cliTexto('q'), min(50, max(1, cliEntero('limit', 20)))));
}

Http::fail('Acción no soportada.', 400);