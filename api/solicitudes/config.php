<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/solicitudes/config.php → catálogo para la UI (tipos + estados + categorías de ticket).
Http::requireMethod('GET');

if ($actor['tipo'] !== 'interno') {
    Http::fail('Solo personal interno', 403);
}
Auth::requireModulo('solicitudes');

$tipos = $service->tipos();
$estados = [];
foreach ($tipos as $t) {
    $estados[$t['codigo']] = $service->estadosDelTipo($t['codigo']);
}

Http::ok([
    'tipos'    => $tipos,
    'estados'  => $estados,
    'categorias' => $service->ticketCategorias(true),
    'responsables' => $service->responsables(),
]);