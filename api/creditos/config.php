<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// CRUD de catálogos de configuración de créditos.
// ent = secciones | campos | documentos | aceptaciones
$m = $_SERVER['REQUEST_METHOD']; $in = Http::input(); $actor = Auth::actor();
$ent = trim((string) ($_GET['ent'] ?? $in['ent'] ?? 'secciones'));
$id = (int) ($in['id'] ?? 0);
$seccionId = (int) ($_GET['seccion_id'] ?? $in['seccion_id'] ?? 0);

// Autorización por operación (acciones deny-by-default; el módulo ya lo exige _bootstrap).
if ($m !== 'GET') {
    if (in_array($ent, ['campos', 'documentos'], true)) {
        Http::crud('creditos.config_secciones', 'items');
    } else {
        Http::crud($ent === 'aceptaciones' ? 'creditos.config_aceptaciones' : 'creditos.config_secciones');
    }
}

if ($m !== 'GET') {
    Idempotencia::begin('creditos.config');
}

if ($ent === 'secciones') {
    if ($m === 'GET') Http::ok($cfg->secciones());
    elseif ($m === 'POST') Http::ok(['id' => $cfg->seccionCrear($in, $actor)], 201);
    elseif ($m === 'PUT') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->seccionEditar($id, $in, $actor); Http::ok(['id' => $id]); }
    elseif ($m === 'DELETE') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->seccionEliminar($id, $actor); Http::ok(['id' => $id]); }
} elseif ($ent === 'campos') {
    if ($m === 'GET') { if ($seccionId <= 0) Http::fail('Falta seccion_id.'); Http::ok($cfg->campos($seccionId)); }
    elseif ($m === 'POST') { if ($seccionId <= 0) Http::fail('Falta seccion_id.'); Http::ok(['id' => $cfg->campoCrear($seccionId, $in, $actor)], 201); }
    elseif ($m === 'PUT') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->campoEditar($id, $in, $actor); Http::ok(['id' => $id]); }
    elseif ($m === 'DELETE') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->campoEliminar($id, $actor); Http::ok(['id' => $id]); }
} elseif ($ent === 'documentos') {
    if ($m === 'GET') { if ($seccionId <= 0) Http::fail('Falta seccion_id.'); Http::ok($cfg->documentos($seccionId)); }
    elseif ($m === 'POST') { if ($seccionId <= 0) Http::fail('Falta seccion_id.'); Http::ok(['id' => $cfg->documentoCrear($seccionId, $in, $actor)], 201); }
    elseif ($m === 'PUT') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->documentoEditar($id, $in, $actor); Http::ok(['id' => $id]); }
    elseif ($m === 'DELETE') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->documentoEliminar($id, $actor); Http::ok(['id' => $id]); }
} elseif ($ent === 'aceptaciones') {
    if ($m === 'GET') Http::ok($cfg->aceptaciones());
    elseif ($m === 'POST') Http::ok(['id' => $cfg->aceptacionCrear($in, $actor)], 201);
    elseif ($m === 'PUT') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->aceptacionEditar($id, $in, $actor); Http::ok(['id' => $id]); }
    elseif ($m === 'DELETE') { if ($id <= 0) Http::fail('Falta el id.'); $cfg->aceptacionEliminar($id, $actor); Http::ok(['id' => $id]); }
} else Http::fail('Catálogo inválido.');
if (!in_array($m, ['GET', 'POST', 'PUT', 'DELETE'], true)) Http::fail('Método no permitido', 405);