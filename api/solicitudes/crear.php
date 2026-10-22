<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/solicitudes/AdjuntoArchivo.php';
require_once $root . '/core/portal/PortalService.php';

// POST /api/solicitudes/crear.php
//   JSON:      { tipo, detalle:{...}, cedula_solicitante?, responsable_id? }
//   Multipart: { tipo, detalle:<json string>, cedula_solicitante?, files[] }  (adjunta en la misma creación)
Http::requireMethod('POST');
Idempotencia::begin('solicitudes');
$in = Http::input();

$tipo = (string) ($in['tipo'] ?? '');
if ($tipo === '') {
    Http::fail('Falta el tipo de solicitud');
}
$detalle = $in['detalle'] ?? [];
if (is_string($detalle)) {
    $decoded = json_decode($detalle, true);
    $detalle = is_array($decoded) ? $decoded : [];
} elseif (!is_array($detalle)) {
    $detalle = [];
}

$archivos = !empty($_FILES['files']) ? AdjuntoArchivo::normalizar($_FILES['files']) : [];

// Asociado (portal): crea SOLO para sí mismo. Staff: puede crear a nombre de un asociado o tickets internos.
if ($actor['tipo'] === 'asociado') {
    $cedula = (string) $actor['id'];
    $ctx = [
        'origen'           => 'portal',
        'solicitante_tipo' => 'asociado',
        'actor_tipo'       => 'asociado',
        'actor_id'         => (string) $actor['id'],
    ];
    // El portal no crea tickets (tipo interno UI→UI) ni con responsable.
    if ($tipo === 'ticket') {
        Http::fail('No autorizado', 403);
    }
    unset($detalle['responsable_id']);
} else {
    // Staff: requiere permiso solicitudes.*
    Auth::requireModulo('solicitudes');
    $cedula = (string) ($in['cedula_solicitante'] ?? '');
    if ($cedula === '') {
        Http::fail('Falta la cédula del solicitante');
    }
    $ctx = [
        'origen'           => 'ui',
        'creado_por'       => (int) $actor['id'],
        'actor_tipo'       => 'interno',
        'actor_id'         => (string) $actor['id'],
    ];
    if ($tipo === 'ticket') {
        Http::crud('solicitudes.gestion', 'crear_ticket');
        // Ticket interno: solicitante es un usuario (id) y requiere responsable.
        $ctx['solicitante_tipo'] = 'interno';
        $ctx['creado_por']       = (int) $actor['id'];
        $ctx['responsable_id']   = (int) (($in['responsable_id'] ?? $detalle['responsable_id'] ?? 0) ?: 0) ?: null;
        if ($ctx['responsable_id'] === null) {
            Http::fail('El ticket requiere un responsable_id');
        }
    } else {
        $ctx['solicitante_tipo'] = 'asociado';
    }
}

// ── Método de pago (boletas / convenios) ─────────────────────────────────
if ($tipo === 'boleta') {
    $metodo = strtolower(trim((string) ($detalle['metodo_pago'] ?? '')));
    if (!in_array($metodo, ['qr', 'credito'], true)) {
        Http::fail('Seleccione el método de pago.');
    }
    if ($metodo === 'qr') {
        $hay = false;
        foreach ($archivos as $f) {
            if ((int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) { $hay = true; break; }
        }
        if (!$hay) {
            Http::fail('Adjunte el comprobante del pago por QR.');
        }
    } else { // credito
        $elig = (new PortalService())->elegibleCreditoBoleta($cedula);
        if (!$elig['apto']) {
            Http::fail('No cumple los requisitos del crédito cooperativa (6 meses de antigüedad o 6 pagos de aportes).');
        }
        // Total del carrito con precios del catálogo (no se confía en el cliente).
        $precios = [];
        foreach ($service->productosBoleta() as $p) {
            $precios[(int) $p['id']] = (float) $p['precio_venta'];
        }
        $total = 0.0;
        foreach ((array) ($detalle['items'] ?? []) as $it) {
            $pid  = (int) ($it['producto_id'] ?? 0);
            $cant = max(0, (int) ($it['cantidad'] ?? 0));
            if (isset($precios[$pid])) {
                $total += $precios[$pid] * $cant;
            }
        }
        if ($total <= 0) {
            Http::fail('El carrito no tiene productos válidos.');
        }
        if ($total > (float) $elig['limite']) {
            Http::fail('El total supera el límite del crédito (' . number_format((float) $elig['limite'], 0, ',', '.') . ').');
        }
    }
    $detalle['metodo_pago'] = $metodo;
}

// Valida los archivos ANTES de crear (evita crear sin comprobante si el archivo no es válido).
foreach ($archivos as $f) {
    if ((int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        AdjuntoArchivo::validar($f);
    }
}

$res = $service->crear($tipo, $cedula, $detalle, $ctx);

// Adjuntos en la misma creación (p. ej. comprobante del pago por QR).
$id = (int) ($res['solicitud']['id'] ?? 0);
if ($id > 0 && $archivos) {
    foreach ($archivos as $f) {
        $meta = AdjuntoArchivo::guardar($f, $id);
        if ($meta !== null) {
            $meta['contexto'] = 'solicitud';
            $service->adjuntar($id, $actor, $meta);
        }
    }
    $res = $service->detalle($id);
}

// Aviso al responsable (solo si la solicitud quedó con responsable asignado, p.ej. tickets).
EmailCola::notificarSolicitudCreada($res);
Http::ok($res, 201);
