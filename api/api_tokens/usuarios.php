<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/api_tokens/usuarios.php — usuarios staff para el selector de creación.
Http::requireMethod('GET');

$rows = (new UsuarioRepository(db()))->listar();
$out = array_map(static fn($u) => [
    'id'              => (int) $u['id'],
    'usuario'         => (string) $u['usuario'],
    'nombre_completo' => (string) ($u['nombre_completo'] ?? ''),
    'rol'             => (string) ($u['rol'] ?? ''),
], $rows);

Http::ok($out);
