<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET  /api/solicitudes/ticket_categorias.php            → lista categorías.
// POST /api/solicitudes/ticket_categorias.php  (staff)   → guardar { id?, nombre, descripcion, estado_activo? }.
// DELETE via ?eliminar=ID (staff)                        → eliminar categoría.
if (Http::method() === 'GET') {
    $soloActivas = !isset($_GET['todas']) ? true : false;
    Http::ok(['rows' => $service->ticketCategorias($soloActivas)]);
}

if ($actor['tipo'] !== 'interno') {
    Http::fail('Solo personal interno', 403);
}
Auth::requireModulo('solicitudes');

if (Http::method() === 'DELETE') {
    Http::crud('solicitudes.ticket_categorias', 'eliminar');
    Idempotencia::begin('solicitudes.categorias');
    $id = (int) ($_GET['eliminar'] ?? 0);
    if ($id <= 0) {
        Http::fail('Falta id');
    }
    $service->eliminarTicketCategoria($id, (int) $actor['id']);
    Http::ok(['ok' => true]);
}

if (Http::method() === 'POST') {
    $in = Http::input();
    Http::crud('solicitudes.ticket_categorias', (!empty($in['id'] ?? null)) ? 'editar' : 'crear');
    Idempotencia::begin('solicitudes.categorias');
    $nombre = trim((string) ($in['nombre'] ?? ''));
    if ($nombre === '') {
        Http::fail('Falta el nombre de la categoría');
    }
    $id = $service->guardarTicketCategoria([
        'id' => $in['id'] ?? null, 'nombre' => $nombre,
        'descripcion' => $in['descripcion'] ?? null, 'estado_activo' => $in['estado_activo'] ?? 1,
    ], (int) $actor['id']);
    Http::ok(['id' => $id], 201);
}

Http::fail('Método no permitido', 405);