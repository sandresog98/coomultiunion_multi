<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET    /api/comunicaciones/planes?...          → listado paginado
 * POST   /api/comunicaciones/planes              → crear plan (JSON o multipart con archivo)
 * PUT    /api/comunicaciones/planes              → editar plan (solo borrador/programado)
 * DELETE /api/comunicaciones/planes?id=          → eliminar borrador/cancelado
 */
$method = Http::method();

if ($method === 'GET') {
    Http::ok($svc->listarPlanes([
        'q' => $_GET['q'] ?? '',
        'canal' => $_GET['canal'] ?? '',
        'estado' => $_GET['estado'] ?? '',
        'tipo' => $_GET['tipo'] ?? '',
        'desde' => $_GET['desde'] ?? '',
        'hasta' => $_GET['hasta'] ?? '',
        'page' => $_GET['page'] ?? 0,
        'size' => $_GET['size'] ?? 20,
        'order' => $_GET['order'] ?? 'id',
        'dir' => $_GET['dir'] ?? 'DESC',
    ]));
}

if ($method === 'POST') {
    Http::crud('comunicaciones.nueva', 'crear');
    Idempotencia::begin('comunicaciones');

    $in = Http::input();
    $canal = trim((string) ($in['canal'] ?? ''));
    if (!in_array($canal, ['sms', 'whatsapp', 'email'], true)) {
        Http::fail('Canal inválido.');
    }

    $rows = [];
    $resumen = [];
    if (!empty($_FILES['archivo']) && ($_FILES['archivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $file = $_FILES['archivo'];
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $maxMb = 10;
        if ((int) $file['size'] > $maxMb * 1024 * 1024) {
            Http::fail("El archivo excede el máximo permitido ({$maxMb} MB).");
        }
        $lectura = $importador->leerArchivo((string) $file['tmp_name'], $ext);
        $validacion = $importador->validar($lectura['rows'], $canal, 2000);
        $rows = $validacion['rows']; // ya vienen completas (hasta el límite del archivo)
        // Para archivos grandes, validar() devuelve solo el detalle; volvemos a
        // validar para obtener todas las filas válidas al insertar.
        if (count($lectura['rows']) > count($rows)) {
            $todas = $importador->validar($lectura['rows'], $canal, 2000);
            $rows = $todas['rows'];
            // Si supera el detalle, se procesa desde la lectura original.
            if (count($lectura['rows']) > 2000) {
                $rows = [];
                foreach ($lectura['rows'] as $i => $r) {
                    $rows[] = $importador->validarFila($r, $canal);
                }
            }
        }
        $resumen = $validacion['resumen'];
    } else {
        $destinatarios = $in['destinatarios'] ?? [];
        if (is_string($destinatarios)) {
            $destinatarios = json_decode($destinatarios, true);
        }
        if (!is_array($destinatarios) || !$destinatarios) {
            Http::fail('Debe adjuntar una base o indicar al menos un destinatario.');
        }
        $resumen = ['total' => count($destinatarios), 'ok' => 0, 'error' => 0, 'omitido' => 0];
        foreach ($destinatarios as $r) {
            if (!is_array($r)) { continue; }
            $row = $importador->validarFila($r, $canal);
            $rows[] = $row;
            $resumen[$row['estado']] = ($resumen[$row['estado']] ?? 0) + 1;
            if ($row['estado'] === 'omitido') {
                $resumen[$row['motivo']] = ($resumen[$row['motivo']] ?? 0) + 1;
            }
        }
    }

    $id = $svc->crearPlan($in, $rows, $resumen, $actor);
    $detalle = $svc->detallePlan($id);

    $procesando = null;
    $programarAhora = (int) ($in['programar_ahora'] ?? 0) === 1 || (string) ($in['modo_envio'] ?? '') === 'ahora';
    if ($programarAhora) {
        $procesando = $envio->procesarPlan($id, 25, true);
        $detalle = $svc->detallePlan($id);
    }

    Http::ok(['id' => $id, 'plan' => $detalle['plan'], 'procesando' => $procesando], 201);
}

if ($method === 'PUT') {
    Http::crud('comunicaciones.planes', 'editar');
    Idempotencia::begin('comunicaciones');
    $in = Http::input();
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) { Http::fail('Falta el id del plan.'); }
    $svc->actualizarPlan($id, $in, $actor);
    Http::ok($svc->detallePlan($id));
}

if ($method === 'DELETE') {
    Http::crud('comunicaciones.planes', 'eliminar');
    Idempotencia::begin('comunicaciones');
    $in = Http::input();
    $id = (int) ($in['id'] ?? 0);
    if ($id <= 0) { Http::fail('Falta el id del plan.'); }
    $svc->eliminarPlan($id, $actor);
    Http::ok(['id' => $id]);
}

Http::fail('Método no permitido', 405);
