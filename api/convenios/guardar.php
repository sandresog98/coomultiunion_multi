<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('POST');
$actor = Auth::actor();
Idempotencia::begin('convenios');
$ent = trim((string) ($_POST['ent'] ?? ($_SERVER['HTTP_X_ENT'] ?? '')));
if ($ent === '') {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    $ent = trim((string) ($raw['ent'] ?? ($_POST['ent'] ?? 'convenio')));
}

if ($ent === 'producto') {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    $data = is_array($raw) ? array_merge($_POST, $raw) : $_POST;
    Http::crud('convenios.index', (int) ($data['producto_id'] ?? $data['id'] ?? 0) > 0 ? 'editar' : 'crear');
    $file = $_FILES['imagen'] ?? null;
    $id = $cv->guardarProducto($data, $actor, $file);
    Http::ok($id, 201);
}

$file = $_FILES['imagen'] ?? null;
$raw = json_decode((string) file_get_contents('php://input'), true);
$data = is_array($raw) ? array_merge($_POST, $raw) : $_POST;
Http::crud('convenios.index', (int) ($data['id'] ?? 0) > 0 ? 'editar' : 'crear');
$id = $cv->guardarConvenio($data, $actor, $file);
Http::ok($id, 201);