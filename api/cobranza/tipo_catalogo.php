<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST crear | PUT editar | DELETE eliminar  (catálogo tipos de comunicación)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST')   Http::crud('cobranza_tipos', 'crear');
if ($method === 'PUT')    Http::crud('cobranza_tipos', 'editar');
if ($method === 'DELETE') Http::crud('cobranza_tipos', 'eliminar');
$in = Http::input();
$actor = Auth::actor();
if ($method === 'POST') {
    Idempotencia::begin('cobranza.tipos');
    $id = $tip->crear($in, $actor);
    Http::ok(['id' => $id], 201);
} elseif ($method === 'PUT') {
    Idempotencia::begin('cobranza.tipos');
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) Http::fail('Falta el id.');
    $tip->editar($id, $in, $actor);
    Http::ok(['id' => $id]);
} elseif ($method === 'DELETE') {
    Idempotencia::begin('cobranza.tipos');
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) Http::fail('Falta el id.');
    $tip->eliminar($id, $actor);
    Http::ok(['id' => $id]);
} else {
    Http::fail('Método no permitido', 405);
}