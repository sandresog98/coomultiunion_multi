<?php
declare(strict_types=1);
/** POST /api/portal/auth_logout.php → cierra sesión del portal */
require_once dirname(__DIR__, 2) . '/core/Http.php';
require_once dirname(__DIR__, 2) . '/core/portal/PortalAuth.php';

PortalAuth::logout();
$_SESSION = [];
session_destroy();
Http::ok(['ok' => true]);