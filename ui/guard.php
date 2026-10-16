<?php
declare(strict_types=1);

/**
 * Guard de páginas de la interfaz ui.
 * - Exige sesión de staff (si no, redirige a login).
 * - Si la contraseña requiere cambio, fuerza la pantalla de cambio.
 * - Opcional: exige un permiso de módulo.
 *
 * Uso al inicio de una page:  $staff = ui_guard('usuarios.gestion');
 */
require_once __DIR__ . '/config/paths.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/Permisos.php';

function ui_guard(?string $modulo = null): array
{
    $staff = Auth::staff();
    if ($staff === null) {
        header('Location: ' . uiBaseUrl() . 'login');
        exit;
    }
    // Cambio de contraseña forzado (excepto en la propia pantalla).
    $current = basename($_SERVER['SCRIPT_NAME'] ?? '');   // sigue siendo cambiar_password.php (nombre real del archivo)
    if (!empty($staff['must_change']) && $current !== 'cambiar_password.php') {
        header('Location: ' . uiBaseUrl() . 'cambiar_password');
        exit;
    }
    if ($modulo !== null && !Permisos::puede($staff['rol'], $modulo)) {
        http_response_code(403);
        echo 'Acceso denegado';
        exit;
    }
    return $staff;
}

/**
 * ¿El rol autenticado puede VER una sección de la página actual? (visibilidad UI).
 * El rol se toma de la sesión salvo que se pase explícito.
 */
function ui_seccion(string $pageKey, string $section, ?string $rol = null): bool
{
    return Permisos::seccion($rol ?? (string) ($_SESSION['staff']['rol'] ?? ''), $pageKey, $section);
}

/**
 * Mapa 'clave' => bool de las secciones declaradas de la página (para inyectar al JS:
 * emite `const SEC = {...}` y permite apagar los fetches de secciones ocultas).
 */
function ui_secciones(string $pageKey, ?string $rol = null): array
{
    return Permisos::seccionesDeRol($rol ?? (string) ($_SESSION['staff']['rol'] ?? ''), $pageKey);
}

/**
 * ¿El rol autenticado puede EJECUTAR una operación ('<pagina>.<operacion>')?
 * Gatéa los BOTONES CRUD en la UI. Deny-by-default (mismo cheque que el API).
 */
function ui_accion(string $pageKey, string $op, ?string $rol = null): bool
{
    return Permisos::accion($rol ?? (string) ($_SESSION['staff']['rol'] ?? ''), $pageKey . '.' . $op);
}

/** Mapa de operaciones de una página para inyectar al JS (const ACC = {...}). */
function ui_acciones(string $pageKey, ?string $rol = null): array
{
    return Permisos::accionesDeRol($rol ?? (string) ($_SESSION['staff']['rol'] ?? ''), $pageKey);
}
