<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/portal/aportes/retiro_solicitar.php (multipart)
//   tipo, monto_solicitado?, pdf_retiro (file), pdf_certificado (file)
Http::requireMethod('POST');
Idempotencia::begin('portal.retiro_aporte');
$data = Http::input();
$flow = $retiro->solicitar($cedula, $data, $_FILES['pdf_retiro'] ?? null, $_FILES['pdf_certificado'] ?? null);

$d = $retiro->detalle($cedula, $flow);
Http::ok(['id' => $flow, 'radicado' => $d['solicitud']['numero_radicado']], 201);