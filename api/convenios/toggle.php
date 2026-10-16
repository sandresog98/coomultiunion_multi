<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('POST');
Http::crud('convenios.index', 'toggle');
$actor = Auth::actor();
Idempotencia::begin('convenios');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
$ent = trim((string) ($in['ent'] ?? 'convenio'));

Http::ok($ent === 'producto' ? $cv->toggleProducto($id, $actor) : $cv->toggleConvenio($id, $actor));