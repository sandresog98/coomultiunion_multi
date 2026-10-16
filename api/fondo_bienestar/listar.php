<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
Http::ok($convenios->listarConvenios(
    trim((string) ($_GET['q'] ?? '')),
    trim((string) ($_GET['estado'] ?? '')),
    trim((string) ($_GET['order'] ?? 'orden')),
    trim((string) ($_GET['dir'] ?? 'ASC')),
    min(200, max(1, (int) ($_GET['limit'] ?? 20))),
    max(0, (int) ($_GET['offset'] ?? 0))
));