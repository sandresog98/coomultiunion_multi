<?php
declare(strict_types=1);

/**
 * Front controller del portal CX: traduce URLs amigables (/cx/<slug>) al
 * archivo real definido en cx/routes.php e lo incluye.
 *
 * Solo se invoca desde la regla de .htaccess para rutas que NO corresponden a
 * un archivo o carpeta existentes, por lo que no interfiere con los archivos
 * reales, los assets ni la API. Las URLs largas antiguas siguen sirviéndose
 * como archivos físicos por la regla genérica de .htaccess.
 */

require_once __DIR__ . '/_env.php';

$uri  = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$pos  = strpos($uri, '/cx/');
$slug = $pos !== false ? substr($uri, $pos + 4) : '';
$slug = trim(rawurldecode($slug), '/');

$rel = cx_route($slug);

// Si no hay coincidencia exacta, probar rutas dinámicas (p. ej. solicitudes/{id}).
if ($rel === null) {
    [$rel, $params] = cx_match($slug);
    foreach ($params as $k => $v) {
        $_GET[$k] = $v;
    }
}

if ($rel === null || !is_file(__DIR__ . '/' . $rel)) {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    $home = rtrim((string) $CX, '/') . '/';
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Página no encontrada</title></head>'
        . '<body style="font-family:Poppins,system-ui,Arial,sans-serif;background:#f4f7fb;margin:0">'
        . '<div style="max-width:520px;margin:12vh auto;padding:32px;text-align:center">'
        . '<h1 style="font-size:3.5rem;margin:0;color:#11468A">404</h1>'
        . '<p style="color:#555">La página solicitada no existe.</p>'
        . '<p><a href="' . htmlspecialchars($home, ENT_QUOTES) . '" '
        . 'style="color:#1882CD;text-decoration:none">Ir al inicio</a></p>'
        . '</div></body></html>';
    exit;
}

require __DIR__ . '/' . $rel;
