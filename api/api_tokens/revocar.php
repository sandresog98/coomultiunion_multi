<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/api_tokens/revocar.php  { id }
Http::requireMethod('POST');
Http::crud('api.authorized_endpoints', 'revocar');

$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) {
    Http::fail('ID de token inválido');
}

$st = db()->prepare('UPDATE api_tokens SET activo = 0, revoked_at = NOW() WHERE id = ? AND activo = 1');
$st->execute([$id]);
if ($st->rowCount() === 0) {
    Http::fail('Token no encontrado o ya revocado', 404);
}

Logger::log('eliminar', 'api.tokens', (string) $staff['id'], 'Token revocado id=' . $id);
Http::ok(['id' => $id]);
