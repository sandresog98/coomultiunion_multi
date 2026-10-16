<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('POST');
Http::crud('fondo_bienestar.convenios', 'toggle');
$actor = Auth::actor();
Idempotencia::begin('fondo_bienestar');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
Http::ok($convenios->toggleConvenio($id, $actor));