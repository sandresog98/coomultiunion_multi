<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET lista | POST crear | PUT editar | DELETE eliminar (líneas de crédito)
$m = $_SERVER['REQUEST_METHOD']; $in = Http::input(); $actor = Auth::actor();
if ($m !== 'GET') {
    Idempotencia::begin('creditos.lineas');
    if ($m === 'PUT') {
        Http::crud('creditos.config_lineas', array_key_exists('estado_activo', $in) ? 'toggle' : 'editar');
    } else {
        Http::crud('creditos.config_lineas');
    }
}
if ($m === 'POST') { Http::ok(['id' => $cfg->lineaCrear($in, $actor)], 201); }
elseif ($m === 'PUT') { $id = (int) ($in['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.'); $cfg->lineaEditar($id, $in, $actor); Http::ok(['id' => $id]); }
elseif ($m === 'GET') { Http::ok($cfg->lineas(false, false)); }
else Http::fail('Método no permitido', 405);