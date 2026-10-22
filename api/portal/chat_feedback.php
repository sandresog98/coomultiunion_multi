<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once $root . '/core/ai/ChatService.php';
// POST /api/portal/chat_feedback.php  { mensaje_id, util, comentario? }
Http::requireMethod('POST');
Idempotencia::begin('portal.chat');

$in = Http::input();
(new ChatService())->feedback(
    (int) ($in['mensaje_id'] ?? 0),
    $actor['cedula'],
    (bool) ($in['util'] ?? false),
    isset($in['comentario']) ? (string) $in['comentario'] : null
);
Http::ok(['ok' => true]);
