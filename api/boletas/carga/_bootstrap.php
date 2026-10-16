<?php
declare(strict_types=1);
// Reutiliza el bootstrap de boletas (permiso convenios.boletas + handler de excepciones + $stock).
require_once dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__, 3) . '/core/convenios/CargaMasivaService.php';
require_once dirname(__DIR__, 3) . '/core/Idempotencia.php';

$carga = new CargaMasivaService();