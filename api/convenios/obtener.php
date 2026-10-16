<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
$ent = trim((string) ($_GET['ent'] ?? 'convenio'));
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');

if ($ent === 'producto') {
    $p = $cv->obtenerProducto($id);
    if (!$p) Http::fail('Producto no encontrado.', 404);
    Http::ok($p);
}

$c = $cv->obtenerConvenio($id);
if (!$c) Http::fail('Convenio no encontrado.', 404);
Http::ok($c);