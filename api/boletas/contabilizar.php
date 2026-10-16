<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('POST');
Http::crud('convenios.stock', 'contabilizar');
$actor = Auth::actor();
Idempotencia::begin('boletas');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
$stock->contabilizar($id, trim((string) ($in['comprobante'] ?? '')), $actor);
Http::ok(['id' => $id]);