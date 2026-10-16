<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET  /api/portal/documentos.php?id=&etapa=&tipo_solicitante= → config de documentos requeridos
// POST multipart → subir documento (solicitud_id, etapa, tipo_documento, archivo)
$m = $_SERVER['REQUEST_METHOD'];
if ($m === 'GET') {
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) Http::fail('Falta la solicitud.');
    $d = $portal->solicitudDetalle($id, $actor['cedula']);
    $docs = $portal->documentosRequeridos('creacion', (string) ($d['tipo_solicitante'] ?? ''));
    Http::ok(['solicitud' => $d, 'documentos' => $docs]);
} elseif ($m === 'POST') {
    Idempotencia::begin('portal.solicitudes');
    $sid = (int) ($_POST['solicitud_id'] ?? 0);
    if ($sid <= 0) Http::fail('Falta la solicitud.');
    $etapa = trim((string) ($_POST['etapa'] ?? 'creacion'));
    $tipo  = trim((string) ($_POST['tipo_documento'] ?? ''));
    if ($tipo === '') Http::fail('Falta tipo_documento.');
    if (empty($_FILES['archivo']) || ($_FILES['archivo']['error'] ?? 1) !== UPLOAD_ERR_OK) {
        Http::fail('Archivo no recibido.');
    }
    $r = $portal->documentoGuardar($sid, $etapa, $tipo, $_FILES['archivo'], $actor['cedula']);
    Http::ok($r, 201);
} else {
    Http::fail('Método no permitido', 405);
}