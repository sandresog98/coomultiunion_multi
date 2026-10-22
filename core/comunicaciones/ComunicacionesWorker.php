<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once __DIR__ . '/EnvioService.php';

/**
 * Orquestador CLI del módulo. Se invoca desde cron cada 15 minutos:
 *   php core/comunicaciones/worker.php --limit=200
 *   php core/comunicaciones/worker.php --sync
 *   php core/comunicaciones/worker.php --plan=123 --limit=50
 */
final class ComunicacionesWorker
{
    public static function run(array $argv): int
    {
        date_default_timezone_set('UTC');
        try { db()->exec("SET time_zone = '+00:00'"); } catch (Throwable $e) { /* noop */ }
        $opts = self::parse($argv);
        $lockFile = sys_get_temp_dir() . '/multi_comunicaciones_worker.lock';
        $fh = @fopen($lockFile, 'c');
        if (!$fh) {
            fwrite(STDERR, "[comunicaciones] No se pudo abrir el lock.\n");
            return 1;
        }
        if (!flock($fh, LOCK_EX | LOCK_NB)) {
            fwrite(STDOUT, "[comunicaciones] Ya hay un worker en ejecución; se omite este ciclo.\n");
            fclose($fh);
            return 0;
        }

        try {
            $svc = new EnvioService();
            if (($opts['sync'] ?? false) === true || (string) ($opts['sync'] ?? '') === '1') {
                $r = $svc->sincronizarEstados((int) ($opts['limit'] ?? 100));
                fwrite(STDOUT, '[comunicaciones] sync: ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n");
                return 0;
            }

            $planId = isset($opts['plan']) ? (int) $opts['plan'] : null;
            $r = $svc->procesarPendientes((int) ($opts['limit'] ?? 200), $planId);
            fwrite(STDOUT, '[comunicaciones] envío: ' . json_encode($r, JSON_UNESCAPED_UNICODE) . "\n");

            // Conciliación oportunista: no bloquea el envío si Twilio falla.
            try {
                $s = $svc->sincronizarEstados(100);
                fwrite(STDOUT, '[comunicaciones] sync: ' . json_encode($s, JSON_UNESCAPED_UNICODE) . "\n");
            } catch (Throwable $e) {
                fwrite(STDERR, '[comunicaciones] sync omitido: ' . $e->getMessage() . "\n");
            }
            return 0;
        } catch (Throwable $e) {
            fwrite(STDERR, '[comunicaciones] ERROR: ' . $e->getMessage() . "\n");
            error_log('[comunicaciones-worker] ' . $e->getMessage());
            return 1;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    /** @return array<string,mixed> */
    private static function parse(array $argv): array
    {
        $out = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$k, $v] = explode('=', $arg, 2);
                    $out[$k] = $v;
                } else {
                    $out[$arg] = true;
                }
            }
        }
        return $out;
    }
}
