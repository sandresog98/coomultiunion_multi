<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/creditos/detalle?id=
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
Http::ok($cred->detalle($id));