<?php
declare(strict_types=1);

/**
 * Ingesta de la base de conocimiento del Asistente IA.
 *
 * Lee archivos .md/.txt de una carpeta, los trocea por encabezados de nivel 2
 * (## Título) y los carga en `chat_conocimiento` (RAG ligero por FULLTEXT).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Uso (desde la raíz del proyecto):
 *   php scripts/ingest_conocimiento.php                 # carga docs/chat/
 *   php scripts/ingest_conocimiento.php --dir=docs/faq  # otra carpeta
 *   php scripts/ingest_conocimiento.php --limpiar       # borra TODO antes de cargar
 *
 * Idempotente: por cada archivo reemplaza sus filas (WHERE fuente = archivo).
 * Se ignoran los ficheros que empiezan por "_" o "." (p. ej. _LEEME.md).
 */

require_once __DIR__ . '/../core/database.php';

$argvStr = implode(' ', $argv ?? []);
$limpiar = strpos($argvStr, '--limpiar') !== false;
$dir = dirname(__DIR__) . '/docs/chat';
if (preg_match('/--dir=([^\s]+)/', $argvStr, $m)) {
    $dir = rtrim($m[1], '/');
    if ($dir !== '' && $dir[0] !== '/' && !preg_match('#^[A-Za-z]:#', $dir)) {
        $dir = dirname(__DIR__) . '/' . $dir;
    }
}

if (!is_dir($dir)) {
    fwrite(STDERR, "Carpeta no encontrada: {$dir}\n");
    exit(1);
}

/**
 * Trocea un documento en fragmentos usando los encabezados "## ".
 * @return array<int,array{titulo:string,contenido:string}>
 */
function trocear(string $texto): array
{
    $texto = str_replace(["\r\n", "\r"], "\n", $texto);
    $partes = preg_split('/^##\s+/m', $texto) ?: [];
    $chunks = [];
    foreach ($partes as $parte) {
        $parte = trim($parte);
        if ($parte === '') {
            continue;
        }
        $lineas = explode("\n", $parte, 2);
        $titulo = trim($lineas[0], "# \t");
        $cuerpo = trim($lineas[1] ?? '');
        if ($cuerpo === '') {
            continue; // encabezado sin contenido
        }
        $chunks[] = ['titulo' => $titulo, 'contenido' => $cuerpo];
    }
    if (!$chunks) {
        $chunks[] = ['titulo' => '', 'contenido' => trim($texto)];
    }
    return $chunks;
}

$archivos = [];
foreach (scandir($dir) ?: [] as $f) {
    if ($f === '' || $f[0] === '.' || $f[0] === '_') {
        continue;
    }
    $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
    if (in_array($ext, ['md', 'txt'], true)) {
        $archivos[] = $dir . '/' . $f;
    }
}
sort($archivos);

$pdo = db();
$total = 0;

if ($limpiar) {
    $pdo->exec('DELETE FROM chat_conocimiento');
    echo "· Base de conocimiento vaciada.\n";
}

foreach ($archivos as $path) {
    $fuente = str_replace('\\', '/', substr($path, strpos($path, 'docs/') !== false ? strpos($path, 'docs/') : strlen($path)));
    $contenido = (string) file_get_contents($path);
    $chunks = trocear($contenido);
    if (!$chunks) {
        continue;
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM chat_conocimiento WHERE fuente = ?')->execute([$fuente]);
        $ins = $pdo->prepare(
            'INSERT INTO chat_conocimiento (categoria, titulo, palabras_clave, contenido, fuente, estado, version)
             VALUES (NULL, ?, ?, ?, ?, 1, 1)'
        );
        foreach ($chunks as $c) {
            $titulo = $c['titulo'] !== '' ? mb_substr($c['titulo'], 0, 255) : basename($fuente);
            $ins->execute([$titulo, mb_substr($titulo, 0, 255), $c['contenido'], $fuente]);
            $total++;
        }
        $pdo->commit();
        echo '· ' . basename($fuente) . ': ' . count($chunks) . " fragmento(s)\n";
    } catch (Throwable $e) {
        $pdo->rollBack();
        fwrite(STDERR, '  ERROR en ' . basename($fuente) . ': ' . $e->getMessage() . "\n");
    }
}

echo "\nListo. Fragmentos cargados: {$total} (de " . count($archivos) . " archivo(s)).\n";
