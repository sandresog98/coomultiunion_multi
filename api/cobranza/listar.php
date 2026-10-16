<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/cobranza/listar?tipo_mora=&q=&banda=&rango=&solo_remitidos=&compromiso=&order=&dir=&limit=&offset=
Http::requireMethod('GET');
Http::ok($cob->listar(
    trim((string) ($_GET['tipo_mora'] ?? '')),
    trim((string) ($_GET['q'] ?? '')),
    trim((string) ($_GET['banda'] ?? '')),
    trim((string) ($_GET['rango'] ?? '')),
    (($_GET['solo_remitidos'] ?? '') === '1' || ($_GET['solo_remitidos'] ?? '') === 'true'),
    trim((string) ($_GET['compromiso'] ?? '')),
    trim((string) ($_GET['order'] ?? 'dias')),
    trim((string) ($_GET['dir'] ?? 'DESC')),
    min(200, max(1, (int) ($_GET['limit'] ?? 20))),
    max(0, (int) ($_GET['offset'] ?? 0))
));