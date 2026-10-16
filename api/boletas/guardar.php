<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('POST');
Http::crud('convenios.stock', 'crear');
$actor = Auth::actor();
Idempotencia::begin('boletas');
$file = $_FILES['archivo'] ?? null;
Http::ok($stock->crear($_POST, $actor, $file), 201);