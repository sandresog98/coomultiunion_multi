<?php
declare(strict_types=1);

/**
 * Migración idempotente LOCAL → R2.
 *
 * Uso (desde la raíz del proyecto):
 *   /opt/lampp/bin/php scripts/migrar_r2.php [--dry-run] [--modulo=creditos] [--limite=N]
 *
 *   --dry-run   solo simula (no sube nada).
 *   --modulo=X  restringe a un solo módulo.
 *   --limite=N  detiene cada módulo al migrar N archivos (pruebas acotadas, NO sube el lote).
 *
 * Para cada módulo cuyo driver sea 'r2' (según STORAGE_DRIVERS, salvo R2_DISABLE=1):
 *   - Recorre uploads/<modulo>/** (recursivo).
 *   - La clave del objeto = ruta relativa a la raíz de uploads (ej. 'creditos/cred1_x.pdf',
 *     'pagos/2026/09/pago_x.pdf') — la MISMA que se guarda en la BD.
 *   - SKIP si el objeto ya existe en R2 (idempotente, no duplica).
 *   - Antes de subir, guarda una copia local en uploads/.backup_r2/ como respaldo.
 *   - Tras subir, verifica exists() y marca "migrado".
 *
 * El archivo local original NO se borra (permite rollback con R2_DISABLE=1).
 */
require_once __DIR__ . '/../core/env.php';
require_once __DIR__ . '/../core/Storage/Storage.php';

$dryRun   = in_array('--dry-run', $argv, true);
$moduloF  = null;
$limite   = null; // --limite=N → máx. archivos a MIGRAR por módulo (pruebas acotadas)
foreach ($argv as $i => $a) {
    if (str_starts_with((string) $a, '--modulo=')) {
        $moduloF = substr((string) $a, strlen('--modulo='));
    }
    if (str_starts_with((string) $a, '--limite=')) {
        $limite = max(1, (int) substr((string) $a, strlen('--limite=')));
    }
}

$root   = dirname(__DIR__);
$uploads = rtrim((string) (env('UPLOAD_DIR') ?: $root . '/uploads'), '/');
if (str_contains($uploads, 'http') || str_starts_with($uploads, '/') === false) {
    if ($uploads !== '' && $uploads[0] !== '/') { $uploads = $root . '/' . $uploads; }
}
$backupDir = $uploads . '/.backup_r2';

$drivers = drivers();
$modulos = array_keys($drivers);

$total = ['files' => 0, 'migrado' => 0, 'skip' => 0, 'backup' => 0, 'fail' => 0];
echo "Migración local→R2 (" . ($dryRun ? 'DRY-RUN' : 'REAL') . ")\n";
echo "Raíz: $uploads\n";

foreach ($modulos as $mod) {
    $driverName = $drivers[$mod];
    if ($driverName !== 'r2') {
        echo "[skip módulo] $mod → local (no se migra)\n";
        continue;
    }
    if ($moduloF !== null && $mod !== $moduloF) {
        continue;
    }
    $modDir = $uploads . '/' . $mod;
    if (!is_dir($modDir)) {
        echo "[ok módulo] $mod → sin archivos locales\n";
        continue;
    }

    echo "[módulo] $mod → r2\n";
    $storage = null;
    if (!$dryRun) {
        $storage = Storage::for($mod);
    }
    $iterator = recurseFiles($modDir);
    $modCount = ['migrado' => 0, 'skip' => 0, 'fail' => 0];

    foreach ($iterator as $abs) {
        $rel = substr($abs, strlen($uploads) + 1); // 'modulo/...'
        // Normaliza separadores de Windows: la clave R2 debe usar '/' siempre.
        $key = ltrim(str_replace('\\', '/', $rel), '/');
        if ($key === '' || str_starts_with($key, '.backup_r2')) {
            continue;
        }
        $total['files']++;
        $modCount['total'] = ($modCount['total'] ?? 0) + 1;

        // Idempotencia: si ya está, no lo tocamos.
        if ($dryRun) {
            $exists = false;
        } else {
            try { $exists = $storage->exists($key); } catch (Throwable $e) { $exists = false; }
        }
        if ($exists) {
            echo "  [skip] $key\n";
            $total['skip']++;
            $modCount['skip']++;
            continue;
        }

        if ($dryRun) {
            echo "  [pendiente] $key\n";
            $total['migrado']++;
            $modCount['migrado']++;
            continue;
        }

        try {
            // Backups local (respaldo) antes de subir.
            $backTarget = $backupDir . '/' . $key;
            if (!is_dir(dirname($backTarget))) { @mkdir(dirname($backTarget), 0777, true); }
            if (!is_file($backTarget)) { @copy($abs, $backTarget); $total['backup']++; }

            $storage->put($key, $abs, ['mime' => mime_content_type($abs) ?: 'application/octet-stream']);
            if ($storage->exists($key)) {
                echo "  [ok] $key\n";
                $total['migrado']++;
                $modCount['migrado']++;
            } else {
                echo "  [!] sin verificar tras subir: $key\n";
                $total['fail']++;
                $modCount['fail']++;
            }
        } catch (Throwable $e) {
            echo "  [fail] $key — " . $e->getMessage() . "\n";
            $total['fail']++;
            $modCount['fail']++;
        }

        // --limite=N: detener este módulo al alcanzar N archivos MIGRADOS.
        if ($limite !== null && ($modCount['migrado'] ?? 0) >= $limite) {
            echo "  …(límite --limite=$limite alcanzado, se detiene este módulo)\n";
            break;
        }
    }
    printf("  → %s: migrado %d, skip %d, fail %d\n", $mod,
        $modCount['migrado'] ?? 0, $modCount['skip'] ?? 0, $modCount['fail'] ?? 0);
    echo "\n";
}

echo "\n═ TOTAL ═\n";
printf("archivos vistos: %d · migrados: %d · skips: %d · backups: %d · fallos: %d\n",
    $total['files'], $total['migrado'] - (int) ($dryRun ? 0 : 0), $total['skip'], $total['backup'], $total['fail']);
echo ($dryRun ? "(dry-run: no se subió nada)\n" : "fin.\n");

/* ───────── utilidades ───────── */

function recurseFiles(string $dir): Generator
{
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile()) {
            yield $file->getPathname();
        }
    }
}

/** Mapa módulo→driver desde Storage (mismos valores que usa Storage::for). */
function drivers(): array
{
    $raw = (string) env('STORAGE_DRIVERS', '');
    $map = ['tienda'=>'local','cargas'=>'local','convenios'=>'local','creditos'=>'r2','pagos'=>'r2','boletas'=>'r2','solicitudes'=>'r2','cobranza'=>'r2','fondo'=>'r2'];
    if ($raw !== '') {
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') { continue; }
            [$m, $d] = array_pad(explode(':', $pair, 2), 2, '');
            if (isset($map[$m]) && ($d === 'r2' || $d === 'local')) {
                $map[$m] = $d;
            }
        }
    }
    if (getenv('R2_DISABLE') === '1') {
        foreach ($map as $m => &$d) { $d = 'local'; }
        unset($d);
    }
    return $map;
}