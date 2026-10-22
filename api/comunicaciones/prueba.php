<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * POST /api/comunicaciones/prueba
 * Crea un plan tipo=prueba con un destinatario y lo envía (respeta opt-in).
 * Body: {canal, remitente_id, destino, nombre, cedula, opt_in, asunto, cuerpo,
 *        plantilla_id, modo_contenido, media_ids, variables_map}
 */
Http::requireMethod('POST');
Http::crud('comunicaciones.nueva', 'prueba');
Idempotencia::begin('comunicaciones');

$in = Http::input();
$canal = trim((string) ($in['canal'] ?? ''));
if (!in_array($canal, ['sms', 'whatsapp', 'email'], true)) {
    Http::fail('Canal inválido.');
}
if ((int) ($in['opt_in'] ?? 0) !== 1) {
    Http::fail('Debe confirmar la autorización (opt-in) para enviar la prueba.');
}
$destinoRaw = trim((string) ($in['destino'] ?? ''));
$fila = [
    'cedula' => (string) ($in['cedula'] ?? ''),
    'nombre' => (string) ($in['nombre'] ?? 'Prueba'),
    'telefono' => $canal === 'email' ? '' : $destinoRaw,
    'email' => $canal === 'email' ? $destinoRaw : '',
    'opt_in' => 1,
];
$row = $importador->validarFila($fila, $canal);
if ($row['estado'] !== 'ok') {
    Http::fail('Destinatario de prueba inválido: ' . ($row['motivo'] ?: $row['estado']));
}
// La prueba registra el opt-in explícito del operador.
$contactos->registrarOptIn($canal, $row['destino'], 'manual', 'Envío de prueba', $actor);

$data = $in;
$data['tipo'] = 'prueba';
$data['nombre'] = 'Prueba · ' . date('Y-m-d H:i');
$data['origen'] = 'prueba';
$data['programar_ahora'] = 1;
$id = $svc->crearPlan($data, [$row], ['total' => 1, 'ok' => 1, 'error' => 0, 'omitido' => 0], $actor);
$proc = $envio->procesarPlan($id, 5, true);
Http::ok(['id' => $id, 'plan' => $svc->detallePlan($id), 'procesando' => $proc], 201);
