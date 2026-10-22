<?php
declare(strict_types=1);

require_once __DIR__ . '/StorageDriver.php';
require_once __DIR__ . '/LocalDriver.php';
require_once __DIR__ . '/R2Driver.php';
require_once dirname(__DIR__) . '/env.php';

/**
 * Storage — fábrica de drivers por módulo.
 *
 * Lee STORAGE_DRIVERS (env, p.ej. "tienda:local,cargas:local,convenios:r2,creditos:r2,…")
 * y R2_DISABLE. Si R2_DISABLE=1 → TODO vuelve a Local (rollback instantáneo).
 *
 * Almacena instancias en caché static (un driver por módulo).
 */
final class Storage
{
    /**
     * Nombre de archivo OPACO único en formato §2.3 (Apéndice A):
     *   "<base>_<codigo>_<clave>.<ext>"
     * Recomendado para guardar archivos subidos, de modo que el nombre del usuario
     * NUNCA quede en BD y los archivos no se reemplacen entre sí.
     *
     * Ejemplos:
     *   Storage::nombreOpaco('cred', '61', $clave, 'pdf') → 'cred_61_<clave>.pdf'
     *   Storage::nombreOpaco('solicitud', $id, hash('sha256', microtime(true)), 'jpeg')
     *
     * @param string $base   Prefijo de módulo/entidad (p.ej. 'cred', 'pago', 'adj', 'sol', 'boleta').
     * @param string $codigo Identificador de la entidad (p.ej. id de solicitud, cédula, id de tabla).
     * @param string $clave  Llave única (hash). Se sanea el patrón alfanumérico.
     * @param string $ext    Extensión original sin punto (se sanea a [a-z0-9] y se agrega el punto).
     */
    public static function nombreOpaco(string $base, string $codigo, string $clave, string $ext): string
    {
        $base   = trim($base);
        $codigo = trim($codigo);
        $clave  = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $clave) ?: 'x';
        $ext    = trim($ext, " \t\n\r\0\x0B.");
        $ext    = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $ext));
        $ext    = $ext !== '' ? '.' . $ext : '';
        return $base . '_' . $codigo . '_' . $clave . $ext;
    }

    private const DEFAULT_DRIVERS = [
        'tienda' => 'local',
        'cargas' => 'local',
        'convenios' => 'local',
        'creditos' => 'r2',
        'pagos' => 'r2',
        'boletas' => 'r2',
        'solicitudes' => 'r2',
        'cobranza' => 'r2',
        'fondo' => 'r2',
        'comunicaciones' => 'local',
    ];

    /** @var array<string, StorageDriver> */
    private static array $cache = [];

    public static function for(string $modulo): StorageDriver
    {
        $modulo = strtolower($modulo);
        if (isset(self::$cache[$modulo])) {
            return self::$cache[$modulo];
        }

        $driverName = self::driverFor($modulo);
        if ($driverName === 'r2' && !self::r2Configured($modulo)) {
            // R2 pedido pero sin R2_* en .env (o sin bucket público para convenios) → Local.
            error_log("[Storage] $modulo quiere r2 pero faltan credenciales R2_* en .env; usando Local.");
            $driverName = 'local';
        }
        $driver = $driverName === 'r2'
            ? self::buildR2($modulo)
            : new LocalDriver(self::uploadsRoot());

        self::$cache[$modulo] = $driver;
        return $driver;
    }

    /** Nombre de driver ('r2'|'local') para el módulo, con rollback R2_DISABLE. */
    private static function driverFor(string $modulo): string
    {
        if (getenv('R2_DISABLE') === '1') {
            return 'local';
        }
        $map = self::docsMap();
        return $map[$modulo] ?? 'local';
    }

    /** Parse de STORAGE_DRIVERS mediante env(). */
    private static function docsMap(): array
    {
        $raw = env('STORAGE_DRIVERS', '');
        if ($raw === '' || $raw === null) {
            return self::DEFAULT_DRIVERS;
        }
        $map = [];
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') { continue; }
            [$m, $d] = array_pad(explode(':', $pair, 2), 2, '');
            if ($m !== '' && ($d === 'r2' || $d === 'local')) {
                $map[trim($m)] = $d;
            }
        }
        // módulos no listados se quedan en local (conservador).
        foreach (self::DEFAULT_DRIVERS as $m => $d) {
            $map += [$m => $d];
        }
        return $map;
    }

    private static function buildR2(string $modulo): R2Driver
    {
        $account   = (string) (env('R2_ACCOUNT_ID') ?? '');
        $endpoint  = (string) (env('R2_ENDPOINT') ?? '');
        if ($endpoint === '' && $account !== '') {
            $endpoint = "https://{$account}.r2.cloudflarestorage.com";
        }

        $public = self::isPublicModule($modulo);
        $bucket = $public
            ? (string) (env('R2_BUCKET_PUBLIC') ?? '')
            : (string) (env('R2_BUCKET') ?? '');

        return new R2Driver(
            $endpoint,
            (string) (env('R2_ACCESS_KEY_ID') ?? ''),
            (string) (env('R2_SECRET_ACCESS_KEY') ?? ''),
            $bucket,
            $public,
            env('R2_PUBLIC_URL')
        );
    }

    /** Módulos cuyo bucket es PÚBLICO (imágenes servidas sin auth). Por ahora ninguno. */
    private static function isPublicModule(string $modulo): bool
    {
        return false;
    }

    /** ¿Hay credenciales R2 suficientes en .env para instanciar R2Driver? (por módulo). */
    private static function r2Configured(string $modulo): bool
    {
        $endpoint = (string) (env('R2_ENDPOINT') ?? '');
        $account  = (string) (env('R2_ACCOUNT_ID') ?? '');
        if ($endpoint === '' && $account === '') {
            return false;
        }
        $key    = (string) (env('R2_ACCESS_KEY_ID') ?? '');
        $secret = (string) (env('R2_SECRET_ACCESS_KEY') ?? '');
        if ($key === '' || $secret === '') {
            return false;
        }
        // Los módulos PÚBLICOS (convenios) necesitan su bucket público; el resto, el privado.
        $bucket = self::isPublicModule($modulo)
            ? (string) (env('R2_BUCKET_PUBLIC') ?? '')
            : (string) (env('R2_BUCKET') ?? '');
        return $bucket !== '';
    }

    private static function uploadsRoot(): string
    {
        $root = (string) (env('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads'));
        if ($root !== '' && $root[0] !== '/') {
            $root = dirname(__DIR__, 2) . '/' . $root;
        }
        return rtrim($root, '/');
    }
}