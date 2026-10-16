<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST crear | PUT editar | DELETE eliminar  (comunicación de cobranza)
$method = $_SERVER['REQUEST_METHOD'];
Http::crud('cobranza.lista', 'comunicar');
$in = Http::input();
$actor = Auth::actor();
if ($method === 'POST') {
    Idempotencia::begin('cobranza');
    $id = $com->crear($in, $actor);
    Http::ok(['id' => $id], 201);
} elseif ($method === 'PUT') {
    Idempotencia::begin('cobranza');
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) Http::fail('Falta el id.');
    $com->editar($id, $in, $actor);
    Http::ok(['id' => $id]);
} elseif ($method === 'DELETE') {
    Idempotencia::begin('cobranza');
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) Http::fail('Falta el id.');
    $com->eliminar($id, $actor);
    Http::ok(['id' => $id]);
} else {
    Http::fail('Método no permitido', 405);
}