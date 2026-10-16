<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
$ent = trim((string) ($_GET['ent'] ?? 'convenios'));

if ($ent === 'convenios') {
    Http::ok($cv->listarConvenios(
        trim((string) ($_GET['q'] ?? '')),
        trim((string) ($_GET['estado'] ?? '')),
        trim((string) ($_GET['order'] ?? 'nombre')),
        trim((string) ($_GET['dir'] ?? 'ASC')),
        min(200, max(1, (int) ($_GET['limit'] ?? 20))),
        max(0, (int) ($_GET['offset'] ?? 0))
    ));
}

// productos (por convenio o todos)
Http::ok($cv->listarProductos(
    (int) ($_GET['convenio_id'] ?? 0),
    trim((string) ($_GET['q'] ?? '')),
    trim((string) ($_GET['tipo'] ?? '')),
    trim((string) ($_GET['order'] ?? 'nombre')),
    trim((string) ($_GET['dir'] ?? 'ASC')),
    min(200, max(1, (int) ($_GET['limit'] ?? 20))),
    max(0, (int) ($_GET['offset'] ?? 0))
));