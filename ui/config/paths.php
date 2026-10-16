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
    return projectBaseUrl() . 'assets/' . ltrim($path, '/');
}
