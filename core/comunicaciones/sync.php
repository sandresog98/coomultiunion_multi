<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo por CLI.');
}

require_once __DIR__ . '/ComunicacionesWorker.php';
exit(ComunicacionesWorker::run(array_merge(['worker', '--sync'], array_slice($argv, 1))));
