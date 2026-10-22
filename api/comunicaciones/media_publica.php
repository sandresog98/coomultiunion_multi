<?php
declare(strict_types=1);
require __DIR__ . '/_public.php';

if (Http::method() !== 'GET') {
    http_response_code(405);
    exit;
}

$token = (string) ($_GET['t'] ?? '');
$data = FirmaService::verify($token);
if (!$data) {
    http_response_code(403);
    exit('Token inválido o vencido.');
}
$id = (int) ($data['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

try {
    $mediaSvc = new MediaService();
    $mediaSvc->serve($id);
} catch (Throwable $e) {
    error_log('[comunicaciones][media] ' . $e->getMessage());
    http_response_code(404);
    exit('Archivo no disponible.');
}
