<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// POST /api/cobranza/remision { cedula } → remitir ; DELETE { cedula } → desremitir
$method = $_SERVER['REQUEST_METHOD'];
Http::crud('cobranza.lista', 'remitir_juridica');
$in = Http::input();
$actor = Auth::actor();
$ced = trim((string) ($in['cedula'] ?? ''));
if ($ced === '') Http::fail('Falta cédula.');
if ($method === 'POST') {
    Idempotencia::begin('cobranza');
    $cob->remitir($ced, $actor);
    Http::ok(['remitido' => true]);
} elseif ($method === 'DELETE') {
    Idempotencia::begin('cobranza');
    $cob->desremitir($ced, $actor);
    Http::ok(['remitido' => false]);
} else {
    Http::fail('Método no permitido', 405);
}