<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST multipart (con PDF obligatorio) o JSON. Crea solicitud a nombre del asociado.
Http::requireMethod('POST');
Idempotencia::begin('portal.fondo');
$data = Http::input();
if (isset($data['convenio_id'])) $data['convenio_id'] = (int) $data['convenio_id'];
$file = $_FILES['archivo'] ?? null;
$id = $fondo->solicitar($cedula, $data, $file);
Http::ok(['id' => $id], 201);