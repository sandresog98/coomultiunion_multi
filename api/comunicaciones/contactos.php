<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

/**
 * GET  /api/comunicaciones/contactos?canal=&direccion=  → consulta
 * POST /api/comunicaciones/contactos {canal,direccion,opt_in,opt_out,...}
 */
if (Http::method() === 'GET') {
    $canal = trim((string) ($_GET['canal'] ?? ''));
    $dir = trim((string) ($_GET['direccion'] ?? ''));
    if (!in_array($canal, ['sms', 'whatsapp', 'email'], true) || $dir === '') {
        Http::fail('Canal y dirección son obligatorios.');
    }
    Http::ok(['contacto' => $contactos->obtener($canal, $contactos->normalizarDireccion($canal, $dir))]);
}

Http::requireMethod('POST');
$in = Http::input();
$canal = trim((string) ($in['canal'] ?? ''));
$dir = trim((string) ($in['direccion'] ?? ''));
if (!in_array($canal, ['sms', 'whatsapp', 'email'], true) || $dir === '') {
    Http::fail('Canal y dirección son obligatorios.');
}
$optIn = (int) ($in['opt_in'] ?? 0) === 1;
$optOut = (int) ($in['opt_out'] ?? 0) === 1;
if ($optIn && $optOut) {
    Http::fail('No se puede marcar opt-in y opt-out a la vez.');
}
Http::crud('comunicaciones.contactos', $optOut ? 'opt_out' : 'opt_in');
Idempotencia::begin('comunicaciones');

if ($optOut) {
    $contactos->registrarOptOut($canal, $dir, 'manual', trim((string) ($in['motivo'] ?? '')) ?: null, $actor);
} else {
    $contactos->registrarOptIn($canal, $dir, 'manual', trim((string) ($in['evidencia'] ?? '')) ?: null, $actor);
}
Http::ok(['contacto' => $contactos->obtener($canal, $contactos->normalizarDireccion($canal, $dir))]);
