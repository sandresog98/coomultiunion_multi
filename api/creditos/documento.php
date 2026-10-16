<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/creditos/documento (multipart: solicitud_id, etapa, tipo_documento, archivo) → sube
// DELETE { id } → elimina
$m = $_SERVER['REQUEST_METHOD']; $actor = Auth::actor();
Http::crud('creditos.gestionar_solicitud', 'adjuntos');
if ($m === 'POST') {
    Idempotencia::begin('creditos.documento');
    $sid = (int) ($_POST['solicitud_id'] ?? 0); if ($sid <= 0) Http::fail('Falta la solicitud');
    $etapa = trim((string) ($_POST['etapa'] ?? 'creacion'));
    $tipo = trim((string) ($_POST['tipo_documento'] ?? '')); if ($tipo === '') Http::fail('Falta tipo_documento');
    if (empty($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? 1) !== UPLOAD_ERR_OK) Http::fail('Archivo no recibido.');
    $r = $cred->documentoGuardar($sid, $etapa, $tipo, $_FILES['archivo'], $actor);
    Http::ok($r, 201);
} elseif ($m === 'DELETE') {
    Idempotencia::begin('creditos.documento');
    $in = Http::input(); $id = (int) ($in['id'] ?? 0); if ($id <= 0) Http::fail('Falta el id.');
    $cred->documentoEliminar($id, $actor);
    Http::ok(['id' => $id]);
} else Http::fail('Método no permitido', 405);