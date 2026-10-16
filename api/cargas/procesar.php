<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST multipart /api/cargas/procesar.php  (campos: tipo, archivo)
Http::requireMethod('POST');
Http::crud('cargas', 'procesar');
Idempotencia::begin('cargas');
$tipo = (string) ($_POST['tipo'] ?? '');
if ($tipo === '' || !isset($_FILES['archivo'])) {
    Http::fail('Faltan el tipo o el archivo');
}
$stats = $service->procesarSubida($tipo, $_FILES['archivo'], (int) $staff['id']);
Http::ok($stats, 201);
