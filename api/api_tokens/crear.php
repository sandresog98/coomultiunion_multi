<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/api_tokens/crear.php
//   { nombre, actor_id, scopes:[...], dias?=180 }
// Devuelve el secreto en claro UNA sola vez.
Http::requireMethod('POST');
Http::crud('api.authorized_endpoints', 'crear');
Idempotencia::begin('api.tokens');

$in       = Http::input();
$nombre   = trim((string) ($in['nombre'] ?? ''));
$actorId  = (int) ($in['actor_id'] ?? 0);
$scopesIn = $in['scopes'] ?? [];
$dias     = (int) ($in['dias'] ?? (int) (env('API_TOKEN_DIAS_DEFAULT', '180') ?: 180));

if ($nombre === '') {
    Http::fail('El nombre del token es obligatorio');
}
if ($actorId <= 0) {
    Http::fail('Seleccione el usuario al que pertenece el token');
}
if (!is_array($scopesIn) || !$scopesIn) {
    Http::fail('Seleccione al menos un endpoint autorizado');
}

// El usuario debe existir y estar activo.
$u = (new UsuarioRepository(db()))->porId($actorId);
if (!$u || (int) $u['estado_activo'] !== 1) {
    Http::fail('Usuario inválido o inactivo');
}

// Los scopes deben ser un subconjunto de la allowlist global activa.
$allowed = db()->query('SELECT clave FROM api_endpoints_autorizados WHERE activo = 1')->fetchAll();
$allowed = array_map(static fn($r) => (string) $r['clave'], $allowed);
$scopes  = [];
foreach ($scopesIn as $s) {
    $s = trim((string) $s);
    if ($s !== '' && in_array($s, $allowed, true)) {
        $scopes[$s] = true;
    }
}
$scopes = array_keys($scopes);
if (!$scopes) {
    Http::fail('Ninguno de los scopes indicados está autorizado para API');
}

$gen = TokenAuth::generar($dias);

db()->prepare(
    'INSERT INTO api_tokens (nombre, prefijo, token_hash, actor_id, scopes, activo, expires_at, created_by)
     VALUES (?,?,?,?,?,1,?,?)'
)->execute([
    $nombre,
    $gen['prefijo'],
    $gen['hash'],
    $actorId,
    json_encode($scopes, JSON_UNESCAPED_UNICODE),
    $gen['expires_at'],
    (int) $staff['id'],
]);

$id = (int) db()->lastInsertId();
Logger::log('crear', 'api.tokens', (string) $staff['id'], 'Token creado: ' . $nombre . ' (' . $gen['prefijo'] . ')');

Http::ok([
    'id'         => $id,
    'nombre'     => $nombre,
    'prefijo'    => $gen['prefijo'],
    'secreto'    => $gen['secreto'],   // mostrar UNA sola vez
    'scopes'     => $scopes,
    'expires_at' => $gen['expires_at'],
    'dias'       => $gen['dias'],
], 201);
