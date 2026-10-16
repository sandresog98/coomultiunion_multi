<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('POST');
Http::crud('convenios.stock', 'vender');
$actor = Auth::actor();
Idempotencia::begin('boletas');
$in = Http::input();
$id = (int) ($in['id'] ?? 0);
if ($id <= 0) Http::fail('Falta el id.');
$cedula = trim((string) ($in['cedula'] ?? ''));
$metodo = trim((string) ($in['metodo_venta'] ?? 'credito'));

$financiero = null;
if (isset($in['financiero']) && is_array($in['financiero'])) {
    $financiero = $in['financiero'];
} else {
    $tipo = trim((string) ($in['financiero_tipo'] ?? ''));
    if ($tipo !== '') {
        $financiero = ['tipo' => $tipo, 'numero' => trim((string) ($in['sifone_numero'] ?? ''))];
    }
}

Http::ok($stock->vender($id, $cedula, $metodo, $financiero, $actor));