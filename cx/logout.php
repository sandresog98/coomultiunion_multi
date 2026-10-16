<?php
declare(strict_types=1);
require_once __DIR__ . '/_env.php';
PortalAuth::logout();
header('Location: ' . $CX . '/login');
exit;