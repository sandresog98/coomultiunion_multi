<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/ai/ChatService.php';
// DELETE /api/portal/chat_eliminar.php  { sesion_id } — archiva una conversación propia.
Http::requireMethod('DELETE');
Idempotencia::begin('portal.chat');

$in       = Http::input();
$sesionId = (int) ($in['sesion_id'] ?? ($_GET['sesion_id'] ?? 0));
(new ChatService())->eliminar($sesionId, $actor['cedula']);
Http::ok(['ok' => true]);
