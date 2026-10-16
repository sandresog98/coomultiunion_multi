<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST (crear) o PUT (editar). contentType JSON. codigo read-only al editar.
if (!in_array(Http::method(), ['POST', 'PUT'], true)) {
    Http::fail('Método no permitido', 405);
}
$actor = Auth::actor();
Idempotencia::begin('fondo_bienestar');
$in = Http::input();
$in['id'] = (int) ($in['id'] ?? 0);
Http::crud('fondo_bienestar.convenios', $in['id'] > 0 ? 'editar' : 'crear');
$res = $convenios->guardarConvenio($in, $actor);
Http::ok($res, $in['id'] > 0 ? 200 : 201);