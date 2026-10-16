<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/configuracion/guardar { valores: { clave: valor, ... } }
Http::requireMethod('POST');
Http::crud('configuracion', 'editar');
Idempotencia::begin('configuracion');

$in      = Http::input();
$valores = is_array($in['valores'] ?? null) ? $in['valores'] : $in;

$permitidas = ['boleta_credito_meses_min', 'boleta_credito_aportes_min', 'boleta_credito_limite'];
foreach ($permitidas as $k) {
    if (!array_key_exists($k, $valores)) {
        continue;
    }
    $v = $valores[$k];
    if (!is_numeric($v) || (int) $v < 0) {
        Http::fail("Valor inválido para {$k}.");
    }
    $service->set($k, (string) ((int) $v));
}

Http::ok(['config' => $service->all()]);
