<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
Http::ok($svc->historial([
    'q' => $_GET['q'] ?? '',
    'canal' => $_GET['canal'] ?? '',
    'estado' => $_GET['estado'] ?? '',
    'plan_id' => $_GET['plan_id'] ?? 0,
    'desde' => $_GET['desde'] ?? '',
    'hasta' => $_GET['hasta'] ?? '',
    'page' => $_GET['page'] ?? 0,
    'size' => $_GET['size'] ?? 20,
    'order' => $_GET['order'] ?? 'fecha_creacion',
    'dir' => $_GET['dir'] ?? 'DESC',
]));
