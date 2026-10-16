<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
$c = $convenios->obtenerConvenio($id);
if (!$c) Http::fail('Convenio no encontrado.', 404);
Http::ok($c);