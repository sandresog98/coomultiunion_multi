<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/notificaciones.php?limit= — feed "Mis avisos" (cédula de sesión).
Http::requireMethod('GET');
$limit = max(1, (int) ($_GET['limit'] ?? 50));
Http::ok($portal->notificaciones($actor['cedula'], $limit));