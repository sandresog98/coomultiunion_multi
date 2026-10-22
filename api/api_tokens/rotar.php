<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/api_tokens/rotar.php  { id, dias? }
// Revoca el token y emite uno nuevo con el mismo nombre, usuario y scopes.
// El secreto nuevo se devuelve UNA sola vez.
Http::requireMethod('POST');
Http::crud('api.authorized_endpoints', 'rotar');
Idempotencia::begin('api.tokens');

$in   = Http::input();
$id   = (int) ($in['id'] ?? 0);
$dias = (int) ($in['dias'] ?? (int) (env('API_TOKEN_DIAS_DEFAULT', '180') ?: 180));
if ($id <= 0) {
    Http::fail('ID de token inválido');
}

$st = db()->prepare('SELECT * FROM api_tokens WHERE id = ? AND activo = 1 LIMIT 1');
$st->execute([$id]);
$t = $st->fetch();
if (!$t) {
    Http::fail('Token no encontrado o ya revocado', 404);
}

$gen = TokenAuth::generar($dias);
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->prepare('UPDATE api_tokens SET activo = 0, revoked_at = NOW() WHERE id = ?')->execute([$id]);
    $pdo->prepare(
        'INSERT INTO api_tokens (nombre, prefijo, token_hash, actor_id, scopes, activo, expires_at, created_by)
         VALUES (?,?,?,?,?,1,?,?)'
    )->execute([
        (string) $t['nombre'],
        $gen['prefijo'],
        $gen['hash'],
        (int) $t['actor_id'],
        $t['scopes'],
        $gen['expires_at'],
        (int) $staff['id'],
    ]);
    $newId = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

Logger::log('editar', 'api.tokens', (string) $staff['id'], 'Token rotado id=' . $id . ' → #' . $newId . ' (' . $gen['prefijo'] . ')');

Http::ok([
    'id'         => $newId,
    'nombre'     => (string) $t['nombre'],
    'prefijo'    => $gen['prefijo'],
    'secreto'    => $gen['secreto'],
    'expires_at' => $gen['expires_at'],
    'dias'       => $gen['dias'],
], 201);
