<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/ai/ChatService.php';
// /api/portal/chat_sesiones.php — conversaciones del asociado.
//   GET  → lista de sesiones activas.
//   POST → crea una sesión { titulo? }.
$chat = new ChatService();

if (Http::method() === 'GET') {
    Http::ok($chat->sesiones($actor['cedula']));
}

Http::requireMethod('POST');
Idempotencia::begin('portal.chat');
$in = Http::input();
$titulo = trim((string) ($in['titulo'] ?? ''));
Http::ok(['id' => $chat->crearSesion($actor['cedula'], $titulo !== '' ? $titulo : null)], 201);
