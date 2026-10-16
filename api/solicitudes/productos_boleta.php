<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

// GET /api/solicitudes/productos_boleta.php → catálogo de productos-boleta (lectura).
Http::requireMethod('GET');

if ($actor['tipo'] === 'asociado') {
    Http::ok(['rows' => $service->productosBoleta()]);
}
Auth::requireModulo('solicitudes');
Http::ok(['rows' => $service->productosBoleta()]);