<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/ai/ChatService.php';
// GET /api/portal/chat_mensajes.php?sesion_id= — mensajes de una conversación propia.
Http::requireMethod('GET');
$sesionId = (int) ($_GET['sesion_id'] ?? 0);
Http::ok((new ChatService())->mensajes($sesionId, $actor['cedula']));
