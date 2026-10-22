<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/ai/ChatService.php';
// POST /api/portal/chat_enviar.php  { sesion_id?, mensaje }
// Núcleo del asistente: valida límites, consulta la IA y persiste el turno.
Http::requireMethod('POST');
Idempotencia::begin('portal.chat');

$in       = Http::input();
$mensaje  = (string) ($in['mensaje'] ?? '');
$sesionId = (isset($in['sesion_id']) && $in['sesion_id'] !== '' && $in['sesion_id'] !== null)
    ? (int) $in['sesion_id']
    : null;

$chat = new ChatService();
try {
    Http::ok($chat->enviar($actor['cedula'], $sesionId, $mensaje), 201);
} catch (ChatLimitException $e) {
    Http::fail($e->getMessage(), 429);
} catch (SesionCerradaException $e) {
    // Conversación cerrada por inactividad: el frontend ofrece abrir una nueva.
    Http::json(['ok' => false, 'error' => $e->getMessage(), 'code' => 'sesion_cerrada'], 409);
} catch (RuntimeException $e) {
    // Proveedor caído o respuesta inválida: nunca se expone el detalle interno.
    Http::fail('El asistente no está disponible en este momento. Intenta más tarde.', 503);
} catch (DomainException $e) {
    Http::fail($e->getMessage(), 400);
}
