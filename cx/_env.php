<?php
declare(strict_types=1);
/** Entorno UI del portal cx/: rutas base + sesión. Se incluye desde layouts y páginas. */
require_once dirname(__DIR__) . '/core/Http.php';
require_once dirname(__DIR__) . '/core/portal/PortalAuth.php';

$s = (string) ($_SERVER['SCRIPT_NAME'] ?? '/');
$p = strpos($s, '/cx');
$APP = $p !== false ? rtrim(substr($s, 0, $p), '/') : '';
$CX  = $APP . '/cx';
$API = $APP . '/api/portal';

function cx_money($v): string
{
    $n = (float) $v;
    return '$' . number_format(abs($n), 0, ',', '.') . ($n < 0 ? ' (cr)' : '');
}
function cx_fecha(?string $f): string
{
    if (!$f) return '—';
    $t = strtotime((string) $f);
    return $t ? date('d/m/Y', $t) : (string) $f;
}