<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/convenios/ProductoStockService.php';
require_once dirname(__DIR__) . '/import/ExcelReader.php';

/**
 * Carga masiva de boletas (B9) — plantilla Excel + vista commit.
 * Flujo (igual que el legacy tienda|catalogo_carga_masiva):
 *   analizar() → parsea+valida filas y las deja en $_SESSION['carga_boletas']
 *   pendientes() → expone las filas pendientes para la vista de validación
 *   commit() → re-valida TODO en servidor (autoritativo), inserta en una
 *              transacción con evento 'crear' por boleta; con ≥1 error NADA se inserta.
 *   cancelar() → descarta sesión + archivo temporal.
 */
final class CargaMasivaService
{
    private const SESSION_KEY = 'carga_boletas';
    private const MAX_ROWS = 2000;

    public function __construct(private ?PDO $db = null, private ?ProductoStockService $stock = null)
    {
        $this->db = $db ?? db();
        $this->stock = $stock ?? new ProductoStockService($this->db);
    }

    /** Filas (con headers + ejemplo + bloque de productos de referencia) para la plantilla Excel. */
    public function plantillaDatos(): array
    {
        $headers = ['PRODUCTO_ID', 'PRODUCTO', 'SERIAL', 'NUMERO_FACTURA', 'PRECIO_COMPRA', 'PRECIO_VENTA', 'FECHA_VENCIMIENTO'];
        $rows = [$headers];
        $first = $this->stock->productosConStock();
        if (!empty($first)) {
            $p = $first[0];
            $rows[] = [$p['id'], $p['nombre'], 'EJEMPLO-0001', 'FAC-00001', '',
                $p['precio_venta'] ?? '', date('Y-m-d', strtotime('+6 months'))];
        } else {
            $rows[] = [70, 'CINECOLOMBIA 2D', 'EJEMPLO-0001', 'FAC-00001', '', 12990, date('Y-m-d', strtotime('+6 months'))];
        }
        $rows[] = []; // fin de los datos (el parser se detiene cuando SERIAL está vacío)
        $rows[] = ['PRODUCTOS DISPONIBLES (referencia — use PRODUCTO_ID o el PRODUCTO)', '', '', '', '', '', ''];
        $rows[] = ['ID', 'PRODUCTO', 'CONVENIO', '', '', '', ''];
        foreach ($first as $p) {
            $rows[] = [$p['id'], $p['nombre'], $p['convenio_nombre'], '', '', '', ''];
        }
        return $rows;
    }

    /** Sube el Excel, lo parsea y valida fila a fila; deja el resultado en la sesión del staff. */
    public function analizar(array $file, ?array $actor = null): array
    {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'], true)) throw new DomainException('Solo se admiten archivos .xls o .xlsx.');
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new DomainException('Error al subir el archivo.');
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) throw new DomainException('El archivo supera los 5MB.');
        if (($file['size'] ?? 0) <= 0) throw new DomainException('El archivo está vacío.');

        $dir = $this->_tempDir();
        $nombre = 'carga_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $dir . '/' . $nombre;
        $ok = move_uploaded_file($file['tmp_name'], $destino);
        if (!$ok && is_file($file['tmp_name'])) {
            $ok = rename($file['tmp_name'], $destino); // respaldo para pruebas CLI (sin HTTP upload)
        }
        if (!$ok) {
            throw new DomainException('No se pudo guardar el archivo temporal.');
        }

        $reader = new ExcelReader();
        if (!$reader->load($dir . '/' . $nombre)) {
            @unlink($dir . '/' . $nombre);
            throw new DomainException('No se pudo leer el Excel: ' . $reader->getError());
        }
        $hdr = $reader->detectHeaderRowByColumns(['PRODUCTO_ID', 'SERIAL', 'NUMERO_FACTURA'], 100);
        if ($hdr === null) {
            @unlink($dir . '/' . $nombre);
            throw new DomainException('No se encontró la fila de cabecera. Se necesitan las columnas: PRODUCTO_ID (o PRODUCTO), SERIAL y NUMERO_FACTURA.');
        }
        $rows = $reader->rowsWithHeadersStopAtEmpty($hdr, 'SERIAL');
        if (empty($rows)) {
            @unlink($dir . '/' . $nombre);
            throw new DomainException('El archivo no tiene filas de datos.');
        }
        if (count($rows) > self::MAX_ROWS) {
            @unlink($dir . '/' . $nombre);
            throw new DomainException('El archivo supera las ' . self::MAX_ROWS . ' filas permitidas.');
        }

        $productos = $this->_productosStockById();
        $filas = [];
        $seriales = [];
        foreach ($rows as $i => $r) {
            $filas[] = $this->_validarFila($this->_extraer($r), $i, $productos, $seriales);
        }

        $this->_guardarSesion([
            'filas' => $filas,
            'archivo' => $file['name'] ?? 'archivo.xlsx',
            'path' => $dir . '/' . $nombre,
            'ts' => time(),
        ]);

        $validas = count(array_filter($filas, fn ($f) => $f['estado'] === 'ok'));
        $errores = count($filas) - $validas;
        Logger::log('crear', 'boletas.carga', $actor['id'] ?? null,
            "Analizada carga masiva: $validas válida(s), $errores con error ({$file['name']})");
        return ['total' => count($filas), 'validas' => $validas, 'errores' => $errores, 'archivo' => $file['name'] ?? ''];
    }

    /** Filas pendientes de la carga en curso (para la vista commit). */
    public function pendientes(): array
    {
        $s = $this->_sesion();
        if (!$s || empty($s['filas'])) return ['activa' => false, 'rows' => []];
        return ['activa' => true, 'archivo' => $s['archivo'] ?? '', 'total' => count($s['filas']), 'rows' => $s['filas']];
    }

    /**
     * Commit: re-valida TODAS las filas contra la BD (autoritativo). Con ≥1 error
     * lanza DomainException y NO se inserta nada. Si todo ok, inserta en una transacción.
     */
    public function commit(array $rows, ?array $actor = null): array
    {
        if (!is_array($rows) || empty($rows)) throw new DomainException('No hay filas para cargar.');

        $productos = $this->_productosStockById();
        $seriales = [];
        $validados = [];
        $errores = 0;
        foreach ($rows as $i => $r) {
            if (!is_array($r)) $r = [];
            $v = $this->_validarFila($this->_extraer([
                'producto_id' => $r['producto_id'] ?? '',
                'producto_nombre' => $r['producto_nombre'] ?? '',
                'serial' => $r['serial'] ?? '',
                'numero_factura' => $r['numero_factura'] ?? '',
                'precio_compra' => $r['precio_compra'] ?? '',
                'precio_venta' => $r['precio_venta'] ?? '',
                'fecha_vencimiento' => $r['fecha_vencimiento'] ?? '',
            ]), (int) $i, $productos, $seriales);
            if ($v['estado'] === 'error') $errores++;
            $validados[] = $v;
        }
        if ($errores > 0) {
            throw new DomainException("La carga tiene $errores fila(s) con error. Corrija antes de cargar (no se insertó nada).");
        }

        $items = array_map(function ($v) {
            return [
                'producto_id' => $v['producto_id'],
                'serial' => $v['serial'],
                'numero_factura' => $v['numero_factura'],
                'precio_compra' => $v['precio_compra'],
                'precio_venta' => $v['precio_venta'],
                'fecha_vencimiento' => $v['fecha_vencimiento'],
            ];
        }, $validados);

        $creadas = $this->stock->crearBatch($items, $actor);

        $s = $this->_sesion();
        $path = $s['path'] ?? null;
        $archivo = $s['archivo'] ?? '';
        $this->_limpiarSesion();
        if ($path && is_file($path)) {
            @unlink($path);
            @rmdir(dirname((string) $path)); // dir temporal único por carga
        }

        return ['creadas' => $creadas, 'archivo' => $archivo];
    }

    /** Descarta la carga pendiente (sesión + archivo temporal). */
    public function cancelar(?int $actorId = null): void
    {
        $s = $this->_sesion();
        $path = $s['path'] ?? null;
        $this->_limpiarSesion();
        if ($path && is_file($path)) {
            @unlink($path);
            @rmdir(dirname((string) $path)); // dir temporal único por carga
        }
        Logger::log('cancelar', 'boletas.carga', $actorId !== null ? (string) $actorId : null, "Carga masiva cancelada (descarte de " . ($s['total'] ?? 0) . " filas en sesión)");
    }

    private function _extraer(array $r): array
    {
        return [
            'producto_id' => trim((string) ($r['PRODUCTO_ID'] ?? $r['producto_id'] ?? '')),
            'producto_nombre' => trim((string) ($r['PRODUCTO'] ?? $r['PRODUCTO_NOMBRE'] ?? $r['producto_nombre'] ?? '')),
            'serial' => $r['SERIAL'] ?? $r['serial'] ?? '',
            'numero_factura' => $r['NUMERO_FACTURA'] ?? $r['numero_factura'] ?? '',
            'precio_compra' => $r['PRECIO_COMPRA'] ?? $r['precio_compra'] ?? '',
            'precio_venta' => $r['PRECIO_VENTA'] ?? $r['precio_venta'] ?? '',
            'fecha_vencimiento' => $r['FECHA_VENCIMIENTO'] ?? $r['fecha_vencimiento'] ?? '',
        ];
    }

    private function _validarFila(array $raw, int $indice, array $productos, array &$seriales): array
    {
        $row = [
            'indice' => $indice,
            'producto_id' => 0,
            'producto_nombre' => '',
            'serial' => '',
            'numero_factura' => '',
            'precio_compra' => 0,
            'precio_venta' => 0,
            'fecha_vencimiento' => null,
            'estado' => 'ok',
            'errores' => [],
        ];
        $errores = [];

        // Producto (id preferido; nombre como fallback).
        $producto = null;
        $pidRaw = $raw['producto_id'];
        $pname = $raw['producto_nombre'];
        if ($pidRaw !== '') {
            if (!preg_match('/^\d+$/', $pidRaw) || !isset($productos[(int) $pidRaw])) {
                $errores['producto'] = preg_match('/^\d+$/', $pidRaw)
                    ? 'No existe un producto de stock con id ' . $pidRaw . '.'
                    : 'PRODUCTO_ID inválido (use el id numérico del listado).';
            } else {
                $producto = $productos[(int) $pidRaw];
                $row['producto_id'] = (int) $pidRaw;
            }
        } elseif ($pname !== '') {
            $found = null;
            foreach ($productos as $id => $p) {
                if (mb_strtolower($p['nombre']) === mb_strtolower($pname)) {
                    $found = $p;
                    $row['producto_id'] = $id;
                    break;
                }
            }
            if ($found === null) $errores['producto'] = 'No existe un producto de stock con nombre "' . $pname . '".';
            else $producto = $found;
        } else {
            $errores['producto'] = 'Debe indicar PRODUCTO_ID o el nombre del PRODUCTO.';
        }
        $row['producto_nombre'] = $producto ? $producto['nombre'] : $pname;

        // Serial (obligatorio, único por producto en BD y dentro del archivo).
        $serial = mb_strtoupper(trim((string) $raw['serial']));
        if ($serial === '') {
            $errores['serial'] = 'Serial requerido.';
        } elseif (mb_strlen($serial) > 64) {
            $errores['serial'] = 'Serial mayor a 64 caracteres.';
        } else {
            $row['serial'] = $serial;
            $key = $row['producto_id'] . '|' . $serial;
            if (isset($seriales[$key])) {
                $errores['serial'] = 'Serial duplicado en el archivo.';
            } elseif ($row['producto_id'] > 0 && $this->stock->existeSerial($row['producto_id'], $serial)) {
                $errores['serial'] = 'El serial ya existe para ese producto.';
            } else {
                $seriales[$key] = true;
            }
        }

        // Nº factura.
        $factura = trim((string) $raw['numero_factura']);
        if ($factura === '') $errores['numero_factura'] = 'Número de factura requerido.';
        else $row['numero_factura'] = $factura;

        // Precios (vacío → precio del producto; soporta formato ES 12.345,67).
        $pc = $this->_normalizarNumero($raw['precio_compra']);
        if (($raw['precio_compra'] ?? '') !== '' && $pc === null) {
            $errores['precio_compra'] = 'Precio de compra inválido.';
        } else {
            $pc = $pc ?? (float) ($producto['precio_compra'] ?? 0);
            if ($pc < 0) $errores['precio_compra'] = 'Precio de compra no puede ser negativo.';
            else $row['precio_compra'] = $pc;
        }
        $pv = $this->_normalizarNumero($raw['precio_venta']);
        if (($raw['precio_venta'] ?? '') !== '' && $pv === null) {
            $errores['precio_venta'] = 'Precio de venta inválido.';
        } else {
            $pv = $pv ?? (float) ($producto['precio_venta'] ?? 0);
            if ($pv <= 0) $errores['precio_venta'] = 'Precio de venta debe ser mayor a $0.';
            else $row['precio_venta'] = $pv;
        }

        // Fecha de vencimiento (opcional; YYYY-MM-DD, dd/mm/aaaa o serial de Excel).
        if (trim((string) $raw['fecha_vencimiento']) !== '') {
            $f = $this->_normalizarFecha($raw['fecha_vencimiento']);
            if ($f['ok']) $row['fecha_vencimiento'] = $f['valor'];
            else $errores['fecha_vencimiento'] = 'Fecha de vencimiento inválida (use YYYY-MM-DD).';
        }

        if (!empty($errores)) {
            $row['estado'] = 'error';
            $row['errores'] = $errores;
        }
        return $row;
    }

    private function _productosStockById(): array
    {
        $out = [];
        foreach ($this->stock->productosConStock() as $p) {
            $out[(int) $p['id']] = $p;
        }
        return $out;
    }

    private static function _normalizarNumero(mixed $v): ?float
    {
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (float) $v;
        $s = trim((string) $v);
        if ($s === '') return null;
        // Formato español '12.345,67' (coma decimal, punto de miles)
        if (str_contains($s, ',') && str_contains($s, '.')) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);       // 12.345,67 → 12345.67
            } else {
                $s = str_replace(',', '', $s);        // 12,345.67 → 12345.67 (formato US)
            }
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);           // 12990,5 → 12990.5
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);            // 1.234.567 (miles, sin decimales)
        }
        if (!preg_match('/^-?\d*\.?\d+$/', $s)) return null;
        return (float) $s;
    }

    private static function _normalizarFecha(mixed $v): array
    {
        if (is_int($v) || is_float($v)) {
            if ($v <= 0) return ['ok' => false, 'valor' => null];
            $unix = (int) (($v - 25569) * 86400); // serial Excel (1900)
            return ['ok' => true, 'valor' => gmdate('Y-m-d', $unix)];
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)) {
            return checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                ? ['ok' => true, 'valor' => $s]
                : ['ok' => false, 'valor' => null];
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})$#', $s, $m)) {
            $val = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            return checkdate((int) $m[2], (int) $m[1], (int) $m[3])
                ? ['ok' => true, 'valor' => $val]
                : ['ok' => false, 'valor' => null];
        }
        return ['ok' => false, 'valor' => null];
    }

    private function _tempDir(): string
    {
        // Archivo EFÍMERO de la carga: fuera del webroot, en un directorio ÚNICO por
        // llamada creado por el proceso actual (evita colisiones de permisos entre
        // usuarios: en Apache el PHP corre como 'daemon' y un dir creado por otro
        // usuario (p.ej. andres) con 0700 no sería escribible).
        $base = rtrim((string) sys_get_temp_dir(), '/');
        $dir = $base . '/cargaboleteria_' . bin2hex(random_bytes(6));
        @mkdir($dir, 0700, true);
        return $dir;
    }

    private function _sesion(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        return $_SESSION[self::SESSION_KEY] ?? null;
    }

    private function _guardarSesion(array $d): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION[self::SESSION_KEY] = $d;
    }

    private function _limpiarSesion(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        unset($_SESSION[self::SESSION_KEY]);
    }
}