<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/chat/mensajes?id=<sesion_id> — conversación completa (visor de staff, solo lectura).
Http::requireMethod('GET');
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Http::fail('Falta el id de la conversación.');
}
Http::ok($service->mensajes($id));
