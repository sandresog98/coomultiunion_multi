<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
// GET /api/portal/perfiles.php → mi información
Http::requireMethod('GET');
Http::ok($portal->perfil($actor['cedula']));