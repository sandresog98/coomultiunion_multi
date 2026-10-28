<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// POST /api/tienda/carga/commit { confirmar: true }
Http::requireMethod('POST');
Http::crud('tienda.catalogo', 'carga');
Idempotencia::begin('tienda.catalogo.carga');
$in = Http::input();

// Refuerzo de acciones específicas según lo que realmente va a hacer la carga.
$resumen = $carga->resumenCommit();
if (($resumen['crear'] ?? 0) > 0 || !empty($resumen['nuevas_categorias']) || !empty($resumen['nuevas_marcas'])) {
    Auth::requireAccion('tienda.catalogo.crear');
}
if (($resumen['actualizar'] ?? 0) > 0) {
    Auth::requireAccion('tienda.catalogo.editar');
}

Http::ok($carga->commit(!empty($in['confirmar']), $actor), 201);
