<?php
declare(strict_types=1);

/**
 * Permisos de la interfaz ui/ — FUENTE ÚNICA: ui/config/secciones.php.
 * Incluye permisos por PREFIJO de módulo (para gates de página, menú y API),
 * permisos de SECCIÓN por página (visibilidad UI, deny-by-default en páginas 'strict')
 * y OPERACIONES CRUD por rol (deny-by-default, reforzadas en api/* con Http::crud).
 *
 * 'admin' es super-rol DECLARADO en la config ('*' en modulos/secciones/acciones):
 * no hay hardcode de admin en este archivo. Un permiso 'oficina' habilita 'oficina.*'.
 */
final class Permisos
{
    private static ?array $cfg = null;

    /** Carga ui/config/secciones.php una sola vez. */
    private static function cfg(): array
    {
        if (self::$cfg === null) {
            $path = dirname(__DIR__) . '/ui/config/secciones.php';
            self::$cfg = is_file($path) ? (require $path) : [];
        }
        return self::$cfg;
    }

    private static function roles(): array
    {
        return self::cfg()['modulos'] ?? [];
    }

    /** ¿El rol tiene acceso al módulo (coincidencia exacta o por prefijo)? */
    public static function puede(string $rol, string $modulo): bool
    {
        $modulos = self::roles()[$rol] ?? [];
        foreach ($modulos as $permiso) {
            if ($permiso === '*' || $permiso === $modulo) {
                return true;
            }
            // 'oficina' habilita 'oficina.asociados' (prefijo)
            if (str_starts_with($modulo, $permiso . '.')) {
                return true;
            }
        }
        return false;
    }

    /**
     * ¿El rol puede VER la sección de una página? (visibilidad UI).
     * Semántica:
     *  - override explícito en 'secciones[$rol]["$pageKey.$section"]' → ese valor.
     *  - comodines en 'secciones[$rol]': '*' (todas) y "<pageKey>.*" (toda la página).
     *  - página 'strict' (deny-by-default) sin override → false.
     *  - página no strict → hereda del módulo que gatea la página.
     *  - página no declarada en el catálogo → false + aviso en log (error de desarrollo).
     */
    public static function seccion(string $rol, string $pageKey, string $section): bool
    {
        $cfg    = self::cfg();
        $pagina = $cfg['pantallas'][$pageKey] ?? null;
        if ($pagina === null) {
            error_log('[Permisos] página no declarada en secciones.php: ' . $pageKey);
            return false;
        }
        $lista = $cfg['secciones'][$rol] ?? [];
        $clave = $pageKey . '.' . $section;
        if (array_key_exists($clave, $lista)) {
            return (bool) $lista[$clave];
        }
        if (array_key_exists('*', $lista)) {
            return (bool) $lista['*'];
        }
        if (array_key_exists($pageKey . '.*', $lista)) {
            return (bool) $lista[$pageKey . '.*'];
        }
        if (!empty($pagina['strict'])) {
            return false; // deny-by-default
        }
        return self::puede($rol, (string) ($pagina['modulo'] ?? $pageKey));
    }

    /** Mapa 'clave' => bool con todas las secciones declaradas de la página (para el JS). */
    public static function seccionesDeRol(string $rol, string $pageKey): array
    {
        $out = [];
        $pagina = self::cfg()['pantallas'][$pageKey] ?? null;
        if ($pagina === null) {
            return $out;
        }
        foreach (array_keys($pagina['secciones'] ?? []) as $sec) {
            $out[$pageKey . '.' . $sec] = self::seccion($rol, $pageKey, $sec);
        }
        return $out;
    }

    /** Roles declarados (para el selector de usuarios). Incluye 'admin' (declarado en config). */
    public static function listaRoles(): array
    {
        return array_keys(self::roles());
    }

    /**
     * ¿El rol puede EJECUTAR una operación CRUD ('<pagina>.<operacion>')? (deny-by-default).
     * - override exacto en 'acciones[$rol][$clave]' → ese valor.
     * - comodines en 'acciones[$rol]': '*' (todas) y "<pagina>.*" (todas las operaciones de la página).
     * - si no está declarada → false.
     */
    public static function accion(string $rol, string $clave): bool
    {
        $acciones = self::cfg()['acciones'][$rol] ?? [];
        if (array_key_exists($clave, $acciones)) {
            return (bool) $acciones[$clave];
        }
        if (array_key_exists('*', $acciones)) {
            return (bool) $acciones['*'];
        }
        $pos = strrpos($clave, '.');
        if ($pos !== false) {
            $pref = substr($clave, 0, $pos) . '.*';
            if (array_key_exists($pref, $acciones)) {
                return (bool) $acciones[$pref];
            }
        }
        return false; // deny-by-default
    }

    /**
     * Mapa 'clave' => bool de operaciones de una página (para inyectar al JS, const ACC).
     * Deriva el conjunto de claves de la UNIÓN de todas las roles declaradas en 'acciones'
     * (prefijo "$pageKey." o comodín '*'), resolviendo cada una con accion($rol, clave):
     * así 'ACC' queda completo para cualquier rol (p. ej. admin con '*') sin depender de
     * que su usuario esté literalmente listado.
     */
    public static function accionesDeRol(string $rol, string $pageKey): array
    {
        $out = [];
        $keys = [];
        foreach (self::cfg()['acciones'] as $map) {
            foreach ($map as $clave => $_v) {
                if ($clave === '*' || str_starts_with($clave, $pageKey . '.')) {
                    $keys[$clave] = true;
                }
            }
        }
        foreach (array_keys($keys) as $clave) {
            $out[$clave] = self::accion($rol, $clave);
        }
        return $out;
    }

    /**
     * Catálogo único de claves de 'acciones' (todas las operaciones declaradas).
     * Lo usa la UI de API para ofrecer qué endpoints se pueden autorizar por token.
     * @return string[]
     */
    public static function catalogoAcciones(): array
    {
        $out = [];
        foreach (self::cfg()['acciones'] ?? [] as $map) {
            foreach (array_keys($map) as $clave) {
                if ($clave !== '*') {
                    $out[$clave] = true;
                }
            }
        }
        $keys = array_keys($out);
        sort($keys);
        return $keys;
    }

    /**
     * Catálogo único de módulos declarados (para scopes de solo lectura).
     * @return string[]
     */
    public static function catalogoModulos(): array
    {
        $out = [];
        foreach (self::roles() as $lista) {
            foreach ($lista as $m) {
                if ($m !== '*') {
                    $out[$m] = true;
                }
            }
        }
        $keys = array_keys($out);
        sort($keys);
        return $keys;
    }

    /**
     * Módulo-gate que un token debe resolver para ejecutar una operación cuyo prefijo
     * NO coincide textualmente con el módulo del endpoint (mapa explícito).
     * Ej.: 'asociados.detalle.asignar' → 'oficina.asociados'. Si no aplica, null
     * (el propio prefijo de la acción ya cubre el módulo en la comprobación de scopes).
     *
     * Nota: es la única correspondencia que no se puede derivar por prefijo; si se
     * agrega una operación de este tipo, añadirla aquí.
     *
     * @return string[] mapa prefijo-acción => módulo-gate
     */
    public static function mapaModuloRequerido(): array
    {
        return [
            'asociados.detalle' => 'oficina.asociados',
            'pagos'             => 'oficina.transacciones',
            'movimientos'       => 'oficina.transacciones',
            'convenios.index'   => 'convenios.admin',
            'convenios.stock'   => 'convenios.boletas',
        ];
    }

    /**
     * Scopes ESPECIALES de token (no son gates de módulo/acción; se comprueban
     * explícitamente en el endpoint). Deben autorizarse en la allowlist para poder
     * asignarse a un token.
     * @return string[]
     */
    public static function catalogoScopesEspeciales(): array
    {
        return [
            'portal.actuar',   // actuar en nombre de una cédula (X-Acting-As-Cedula)
        ];
    }

    /** Módulo-gate requerido por una operación (o null si el prefijo ya lo cubre). */
    public static function moduloRequerido(string $accion): ?string
    {
        foreach (self::mapaModuloRequerido() as $prefijo => $modulo) {
            if ($accion === $prefijo || str_starts_with($accion, $prefijo . '.')) {
                return $modulo;
            }
        }
        return null;
    }
}