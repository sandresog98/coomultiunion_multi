<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';

Http::requireMethod('GET');
$q = trim((string) ($_GET['q'] ?? ''));
if ($q === '') {
    Http::ok(['asociados' => [], 'contactos' => []]);
}
Http::ok([
    'asociados' => $contactos->buscarAsociados($q, 25),
    'contactos' => $contactos->buscarContactos($q, 25),
]);
