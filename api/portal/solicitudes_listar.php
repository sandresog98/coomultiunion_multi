<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/solicitudes_listar.php?limit=&offset= → SOLO las solicitudes del asociado logueado
Http::requireMethod('GET');
$limit  = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
$offset = max(0, (int) ($_GET['offset'] ?? 0));
Http::ok($portal->solicitudesDe($actor['cedula'], $limit, $offset));