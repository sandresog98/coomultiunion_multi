<?php
declare(strict_types=1);

/**
 * Worker CLI de comunicaciones. Solo CLI (nunca HTTP).
 *
 * Cron Hostinger (cada 15 minutos):
 *   php /home/USUARIO/domains/DOMINIO/core/comunicaciones/worker.php --limit=200
 *
 * Sincronización de estados/costos:
 *   php /home/USUARIO/domains/DOMINIO/core/comunicaciones/worker.php --sync
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por CLI.');
}

require_once __DIR__ . '/ComunicacionesWorker.php';
exit(ComunicacionesWorker::run($argv));
