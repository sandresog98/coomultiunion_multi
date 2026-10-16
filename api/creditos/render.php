<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/creditos/render?seccion=<ocasion>&canal=staff&tipo_solicitante=&modo=ocasion|codigo
Http::requireMethod('GET');
$ref = trim((string) ($_GET['seccion'] ?? 'creacion'));
$canal = trim((string) ($_GET['canal'] ?? 'staff'));
$tipo = trim((string) ($_GET['tipo_solicitante'] ?? '')) ?: null;
$modo = trim((string) ($_GET['modo'] ?? 'ocasion'));

if ($ref === 'all' || $ref === '*') {
    Http::ok(['secciones' => $cfg->renderSeccionesAll($canal, $tipo), 'aceptaciones' => $cfg->renderAceptaciones($canal), 'lineas' => $cfg->lineas(true, true)]);
}

Http::ok(['secciones' => $cfg->renderSeccion($ref, $canal, $tipo, $modo), 'aceptaciones' => $cfg->renderAceptaciones($canal), 'lineas' => $cfg->lineas(true, true)]);