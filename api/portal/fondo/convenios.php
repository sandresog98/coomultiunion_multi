<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
Http::ok($fondo->listarConveniosConElegibilidad($cedula));