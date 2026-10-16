<?php
declare(strict_types=1);
$root = dirname(__DIR__, 2);
require_once $root . '/core/Http.php';
require_once $root . '/core/Auth.php';

// POST /api/auth/logout.php
Http::requireMethod('POST');
Auth::logout();
Http::ok(['loggedOut' => true]);
