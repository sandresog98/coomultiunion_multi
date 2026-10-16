<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/convenios.php → convenios activos con sus productos-boleta (maneja_stock=1).
Http::requireMethod('GET');
Http::ok($portal->convenios());