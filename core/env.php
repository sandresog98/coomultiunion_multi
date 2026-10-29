<?php
declare(strict_types=1);

/**
 * Cargador de variables de entorno desde el archivo .env de la raíz del proyecto.
 * Uso: require_once __DIR__.'/env.php';  $v = env('DB_HOST', '127.0.0.1');
 */

if (!function_exists('env')) {

    function env_load(string $path): void
    {
        static $loaded = [];
        if (isset($loaded[$path]) || !is_file($path)) {
            return;
        }
        $loaded[$path] = true;

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $val = trim(substr($line, $pos + 1));
            // quita comillas envolventes y comentario en línea sin comillas
            if ($val !== '' && ($val[0] === '"' || $val[0] === "'")) {
                $val = trim($val, "\"'");
            } else {
                $hash = strpos($val, ' #');
                if ($hash !== false) {
                    $val = rtrim(substr($val, 0, $hash));
                }
            }
            if (getenv($key) === false) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
            }
        }
    }

    function env(string $key, ?string $default = null): ?string
    {
        env_load(dirname(__DIR__) . '/.env');
        $val = getenv($key);
        return $val === false ? $default : $val;
    }

    /**
     * Entorno de la aplicación: 'local' | 'test' | 'production'.
     * Se usa para distinguir visualmente el entorno (p. ej. el sidebar en pruebas).
     */
    function app_env(): string
    {
        return (string) env('APP_ENV', 'production');
    }

    /**
     * Etiqueta visible del entorno (vacío en producción). Ej: 'PRUEBAS', 'LOCAL'.
     */
    function env_label(): string
    {
        return ['test' => 'PRUEBAS', 'local' => 'LOCAL'][app_env()] ?? '';
    }

    /**
     * Clase CSS del entorno para el <body> (p. ej. 'env-local', 'env-test').
     * Evita repetir la sanitización en cada superficie.
     */
    function env_body_class(): string
    {
        return 'env-' . preg_replace('/[^a-z0-9_-]/i', '', app_env());
    }
}
