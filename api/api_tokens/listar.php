<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/api_tokens/listar.php — tokens emitidos (sin el secreto).
Http::requireMethod('GET');

$rows = db()->query(
    'SELECT t.id, t.nombre, t.prefijo, t.actor_id, t.scopes, t.activo, t.expires_at,
            t.last_used_at, t.created_at, t.revoked_at,
            u.usuario, u.nombre_completo, u.rol
       FROM api_tokens t
       LEFT JOIN usuarios u ON u.id = t.actor_id
      ORDER BY t.id DESC'
)->fetchAll();

$now = time();
foreach ($rows as &$r) {
    $r['id']       = (int) $r['id'];
    $r['actor_id'] = (int) $r['actor_id'];
    $r['activo']   = (int) $r['activo'];
    $sc = json_decode((string) ($r['scopes'] ?? '[]'), true);
    $r['scopes']   = is_array($sc) ? array_values($sc) : [];
    $r['expirado'] = strtotime((string) $r['expires_at']) < $now;
}
unset($r);

Http::ok($rows);
