<?php
declare(strict_types=1);

/**
 * Gestión de rutas/URLs de la interfaz ui.
 * Deriva la URL base del proyecto a partir del script actual.
 */

function projectBaseUrl(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';           // /projects/multi_app_v2/ui/login.php
    $base = preg_replace('#/ui(/.*)?$#', '', $script);  // /projects/multi_app_v2
    return rtrim((string) $base, '/') . '/';
}

function uiBaseUrl(): string
{
    return projectBaseUrl() . 'ui/';
}

function apiUrl(string $path = ''): string
{
    return projectBaseUrl() . 'api/' . ltrim($path, '/');
}

function assetUrl(string $path = ''): string
{
    $path = ltrim($path, '/');
    $url  = projectBaseUrl() . 'assets/' . $path;

    // Cache-busting por fecha de modificación: evita que el navegador/CDN siga
    // sirviendo un JS/CSS viejo después de un despliegue.
    $file = dirname(__DIR__, 2) . '/assets/' . (string) parse_url($path, PHP_URL_PATH);
    if ($path !== '' && is_file($file)) {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($file);
    }

    return $url;
}
