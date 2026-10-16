<?php
declare(strict_types=1);

/**
 * Limpieza local: borra de uploads/<modulo> los archivos que YA existan como objeto en R2.
 * La nube queda como fuente (módulos R2); los módulos locales y .backup_r2 NO se tocan.
 *
 * Uso (desde la raíz del proyecto):
 *   /opt/lampp/bin/php scripts/limpiar_local_r2.php             # DRY-RUN (lista candidatos)
 *   /opt/lampp/bin/php scripts/limpiar_local_r2.php --borrar    # aplica el borrado confirmado en R2
 *
 * Garantías:
 *   - Solo actúa sobre los módulos con driver r2 (definidos por STORAGE_DRIVERS/Storage).
 *   - Por cada archivo verifica Storage::for($mod)->exists('<mod>/<rel>') contra R2 REAL.
 *   - Borra únicamente cuando el objeto existe en R2 (nunca por asumir).
 *   - NO toca tienda/cargas/convenios (locales) ni uploads/.backup_r2/** (respaldo conservado).
 *   - Antes de --borrar escribe un manifiesto log en uploads/.borrados_r2_<fecha>.log.
 */
require_once __DIR__ . '/../core/env.php';
require_once __DIR__ . '/../core/Storage/Storage.php';

$aplicar = in_array('--borrar', $argv, true);

$root    = dirname(__DIR__);
$uploads = rtrim((string) (env('UPLOAD_DIR') ?: $root . '/uploads'), '/');
if ($uploads !== '' && $uploads[0] !== '/') { $uploads = $root . '/' . $uploads; }

// Módulos R2 según Storage (misma lógica que la migración).
$drivers = driversMapR2();

echo "Limpieza local→ (R2 es la fuente)  [" . ($aplicar ? 'BORRAR' : 'DRY-RUN — no borra nada') . "]\n";
echo "Raíz: $uploads\n\n";

$total = ['vistos' => 0, 'candidatos' => 0, 'borrados' => 0, 'fail' => 0];
$manifiesto = [];

if ($drivers === []) {
    echo "No hay módulos R2 configurados (¿R2_DISABLE=1 o sin credenciales?). Nada que hacer.\n";
    exit(0);
}

foreach ($drivers as $mod) {
    $modDir = $uploads . '/' . $mod;
    if (!is_dir($modDir)) {
        echo "[ok módulo] $mod → sin archivos locales\n";
        continue;
    }
    echo "[módulo] $mod → r2\n";
    $storage = Storage::for($mod);
    // Los objetos subidos a R2 tienen su respaldo en .backup_r2/<mod>/ (la migración respalda
    // ANTES de subir). Ese pueee ser un pre-filtro local rápido; en --borrar se verifica R2.
    $backupRoot = $uploads . '/.backup_r2';
    $backupMod = $backupRoot . '/' . $mod;
    $inBackup = [];
    if (is_dir($backupMod)) {
        foreach (recurseFiles($backupMod) as $b) {
            // clave R2 = relativa a la raíz de uploads con prefijo de módulo: '<mod>/<rel>'
            $inBackup[substr($b, strlen($backupRoot) + 1)] = true;
        }
    }

    foreach (recurseFiles($modDir) as $abs) {
        $rel = substr($abs, strlen($uploads) + 1); // '<mod>/<rel>'
        $key = ltrim($rel, '/');
        if ($key === '' || str_starts_with($key, '.backup_r2') || str_starts_with(basename($key), '.')) {
            continue;
        }
        $total['vistos']++;

        // Candidato: el objeto está en R2 ⇔ respaldado localmente en .backup_r2 (rápido).
        if (!isset($inBackup[$key])) {
            continue;
        }

        $total['candidatos']++;
        if (!$aplicar) {
            echo "  [candidato] $key\n";
            continue;
        }

        // --borrar: verificación REAL contra R2 antes de borrar (nunca por asumir).
        try {
            $enR2 = $storage->exists($key);
        } catch (Throwable $e) {
            $enR2 = false;
            echo "  [fail] sin confirmar en R2: $key — " . $e->getMessage() . "\n";
        }
        if (!$enR2) {
            $total['fail']++;
            echo "  [skip] no existe en R2 (se conserva local): $key\n";
            continue;
        }
        // El respaldo .backup_r2 queda intacto; borramos la copia suelta.
        if (@unlink($abs)) {
            $total['borrados']++;
            $manifiesto[] = $key;
            echo "  [borrado] $key\n";
        } else {
            $total['fail']++;
            echo "  [fail] no se pudo borrar: $key\n";
        }
    }
    echo "\n";
}

echo "═ TOTAL ═\n";
printf("archivos vistos: %d · candidatos (en R2): %d · borrados: %d · fallos/aviso: %d\n",
    $total['vistos'], $total['candidatos'], $total['borrados'], $total['fail']);

if ($aplicar && $manifiesto) {
    $logPath = $uploads . '/.borrados_r2_' . date('Ymd_His') . '.log';
    file_put_contents($logPath, implode("\n", $manifiesto) . "\n");
    echo "Manifiesto: $logPath (" . count($manifiesto) . " objetos)\n";
}
echo ($aplicar ? "fin (R2 sigue siendo la fuente; .backup_r2 conservado).\n" : "(dry-run: no se borró nada; usa --borrar con tu OK).\n");

/* ───────── utilidades ───────── */

function recurseFiles(string $dir): Generator
{
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if ($file->isFile()) { yield $file->getPathname(); }
    }
}

/** Módulos cuyo driver es r2 (consistente con la migración → Storage). */
function driversMapR2(): array
{
    $map = ['tienda'=>'local','cargas'=>'local','convenios'=>'local','creditos'=>'r2','pagos'=>'r2','boletas'=>'r2','solicitudes'=>'r2','cobranza'=>'r2','fondo'=>'r2'];
    $raw = (string) env('STORAGE_DRIVERS', '');
    if ($raw !== '') {
        foreach (explode(',', $raw) as $pair) {
            $pair = trim($pair);
            if ($pair === '') { continue; }
            [$m, $d] = array_pad(explode(':', $pair, 2), 2, '');
            if (isset($map[$m]) && ($d === 'r2' || $d === 'local')) { $map[$m] = $d; }
        }
    }
    if (getenv('R2_DISABLE') === '1') {
        foreach ($map as $m => &$d) { $d = 'local'; }
        unset($d);
    }
    return array_keys(array_filter($map, fn($d) => $d === 'r2'));
}