<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/solicitudes/marcar_recibido.php   { id, responsable_id? }
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
if ($actor['tipo'] !== 'interno') {
    Http::fail('Solo personal interno', 403);
}
Auth::requireModulo('solicitudes');
Http::crud('solicitudes.gestion', 'acciones');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta id');
}
// PQRS: pendiente → recibido. Si se indica responsable, se asigna en el paso siguiente.
Http::ok($service->transicionar($id, 'recibido', $actor, 'Marcada como recibida'));