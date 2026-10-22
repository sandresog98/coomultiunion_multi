<?php
declare(strict_types=1);

/**
 * Valida la coherencia de `api-docs/openapi.yaml` contra la FUENTE ÚNICA de permisos
 * (`ui/config/secciones.php`, vía `core/Permisos.php`).
 *
 * Comprueba:
 *   1. Que cada `x-required-scopes` del spec exista en el catálogo de permisos
 *      (acciones + módulos + scopes especiales).
 *   2. Que cada `x-module-gate` del spec exista en ese mismo catálogo.
 *   3. Que cada ruta documentada tenga su endpoint real en `api/`.
 *
 * Uso (desde la raíz del proyecto):
 *   php scripts/validate_api_docs.php        # Windows/XAMPP:  C:\xampp\php\php.exe
 *   /opt/lampp/bin/php scripts/validate_api_docs.php
 *
 * Exit code: 0 = coherente · 1 = hay hallazgos · 2 = error de entorno.
 */

require_once __DIR__ . '/../core/Permisos.php';

$root     = dirname(__DIR__);
$specPath = $root . '/api-docs/openapi.yaml';
if (!is_file($specPath)) {
    fwrite(STDERR, "No existe el spec: $specPath\n");
    exit(2);
}

$lines = preg_split('/\r\n|\r|\n/', (string) file_get_contents($specPath));

// Catálogo de claves válidas (fuente única de permisos).
$catalogo = array_fill_keys(array_merge(
    Permisos::catalogoAcciones(),
    Permisos::catalogoModulos(),
    Permisos::catalogoScopesEspeciales()
), true);

$scopes = [];
$gates  = [];
$paths  = [];
$n      = count($lines);

for ($i = 0; $i < $n; $i++) {
    $line = $lines[$i];

    if (preg_match('#^  (/\S+):\s*$#', $line, $m)) {
        $paths[$m[1]] = true;
    }
    if (preg_match('#^\s*x-module-gate:\s*(\S+)\s*$#', $line, $m)) {
        $gates[$m[1]] = true;
    }
    if (preg_match('#^\s*x-required-scopes:\s*\[(.*)\]\s*$#', $line, $m)) {
        foreach (explode(',', $m[1]) as $s) {
            $s = trim($s);
            if ($s !== '') {
                $scopes[$s] = true;
            }
        }
    } elseif (preg_match('#^\s*x-required-scopes:\s*$#', $line)) {
        for ($j = $i + 1; $j < $n; $j++) {
            if (preg_match('#^\s*-\s*(\S+)\s*$#', $lines[$j], $mm)) {
                $scopes[$mm[1]] = true;
            } else {
                break;
            }
        }
    }
}

$problemas = [];
foreach (array_keys($scopes) as $s) {
    if (!isset($catalogo[$s])) {
        $problemas[] = "Scope en el spec que NO existe en el catálogo de permisos: $s";
    }
}
foreach (array_keys($gates) as $g) {
    if (!isset($catalogo[$g])) {
        $problemas[] = "x-module-gate en el spec que NO existe en el catálogo de permisos: $g";
    }
}
foreach (array_keys($paths) as $p) {
    // /solicitudes/crear.php  →  api/solicitudes/crear.php
    if (!is_file($root . '/api' . $p)) {
        $problemas[] = "Ruta documentada sin endpoint en api/: $p";
    }
}

if ($problemas === []) {
    printf(
        "OK: openapi.yaml coherente con el catálogo de permisos (%d scopes, %d module-gates, %d rutas).\n",
        count($scopes),
        count($gates),
        count($paths)
    );
    exit(0);
}

echo 'Hallazgos (' . count($problemas) . "):\n";
foreach ($problemas as $p) {
    echo ' - ' . $p . "\n";
}
exit(1);
