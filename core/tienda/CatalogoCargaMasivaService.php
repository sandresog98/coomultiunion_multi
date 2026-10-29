<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/import/ExcelReader.php';
require_once __DIR__ . '/CatalogoService.php';

/**
 * Carga masiva del catálogo de tienda.
 *
 * Flujo:
 *   analizar()        → sube el Excel, valida por fila y deja el estado en sesión.
 *   actualizarFila()  → edita una fila en pantalla y revalida (sin volver a subir).
 *   resumenCommit()   → conteos y bloqueo si hay errores.
 *   commit()          → crea maestros confirmados y crea/actualiza productos.
 *
 * El usuario trabaja con NOMBRES (categoría, marca, producto), nunca con IDs.
 * Las categorías/marcas inexistentes solo se crean si la fila trae la
 * confirmación explícita (crear_categoria / crear_marca).
 */
final class CatalogoCargaMasivaService
{
    private const SESSION_KEY = 'carga_catalogo_tienda';
    private const MAX_ROWS = 1000;

    private const HEADERS = [
        'CATEGORIA', 'MARCA', 'NOMBRE', 'DESCRIPCION',
        'PRECIO_COMPRA_SUGERIDO', 'PRECIO_VENTA_SUGERIDO', 'ESTADO_ACTIVO', 'AGOTADO',
    ];

    public function __construct(private ?PDO $db = null, private ?CatalogoService $catalogo = null)
    {
        $this->db = $db ?? db();
        $this->catalogo = $catalogo ?? new CatalogoService($this->db);
    }

    /** Plantilla XLSX con el catálogo actual (nombres visibles) y filas vacías. */
    public function plantillaDatos(): array
    {
        $rows = [self::HEADERS];

        $st = $this->db->query(
            "SELECT c.nombre AS categoria, m.nombre AS marca, p.nombre, p.descripcion,
                    p.precio_compra_sugerido, p.precio_venta_sugerido, p.estado_activo, p.agotado
               FROM tienda_productos p
               INNER JOIN tienda_categorias c ON c.id = p.categoria_id
               INNER JOIN tienda_marcas m ON m.id = p.marca_id
              ORDER BY c.nombre, m.nombre, p.nombre"
        );
        foreach ($st->fetchAll() as $p) {
            $rows[] = [
                (string) $p['categoria'],
                (string) $p['marca'],
                (string) $p['nombre'],
                (string) ($p['descripcion'] ?? ''),
                ($p['precio_compra_sugerido'] !== null ? (string) $p['precio_compra_sugerido'] : ''),
                ($p['precio_venta_sugerido'] !== null ? (string) $p['precio_venta_sugerido'] : ''),
                ((int) $p['estado_activo'] === 1 ? 'SI' : 'NO'),
                ((int) $p['agotado'] === 1 ? 'SI' : 'NO'),
            ];
        }

        // Filas vacías de ejemplo para productos nuevos.
        for ($i = 0; $i < 5; $i++) {
            $rows[] = ['', '', '', '', '', '', 'SI', 'NO'];
        }

        return $rows;
    }

    /** Analiza el archivo subido y deja las filas en sesión. */
    public function analizar(array $file, ?array $actor = null): array
    {
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['xls', 'xlsx'], true)) {
            throw new DomainException('Solo se admiten archivos .xls o .xlsx.');
        }
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) {
            throw new DomainException('Error al subir el archivo.');
        }
        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            throw new DomainException('El archivo supera los 5 MB.');
        }
        if (($file['size'] ?? 0) <= 0) {
            throw new DomainException('El archivo está vacío.');
        }

        $dir = $this->_tempDir();
        $nombre = 'catalogo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $dir . '/' . $nombre;
        $ok = move_uploaded_file($file['tmp_name'], $destino);
        if (!$ok && is_file($file['tmp_name'])) {
            $ok = rename($file['tmp_name'], $destino);
        }
        if (!$ok) {
            throw new DomainException('No se pudo guardar el archivo temporal.');
        }

        $reader = new ExcelReader();
        if (!$reader->load($destino)) {
            @unlink($destino);
            throw new DomainException('No se pudo leer el Excel: ' . $reader->getError());
        }

        $hdr = $reader->detectHeaderRowByColumns(['CATEGORIA', 'MARCA', 'NOMBRE'], 100);
        if ($hdr === null) {
            @unlink($destino);
            throw new DomainException('No se encontró la cabecera. Se necesitan las columnas CATEGORIA, MARCA y NOMBRE.');
        }

        $dataRows = $reader->rowsWithHeaders($hdr, 0);
        $filas = [];
        $n = 0;
        foreach ($dataRows as $raw) {
            $datos = $this->_extraer($raw);
            if ($datos['categoria'] === '' && $datos['marca'] === '' && $datos['nombre'] === '') {
                continue;
            }
            $n++;
            if ($n > self::MAX_ROWS) {
                @unlink($destino);
                throw new DomainException('El archivo supera las ' . self::MAX_ROWS . ' filas permitidas.');
            }
            $filaId = 'f' . $n;
            $filas[] = [
                'id' => $filaId,
                'indice' => $n,
                'datos' => $datos,
                'estado' => 'error',
                'errores' => [],
                'categoria_id' => null,
                'marca_id' => null,
                'producto_id' => null,
                'crear_categoria' => false,
                'crear_marca' => false,
            ];
        }

        if (!$filas) {
            @unlink($destino);
            throw new DomainException('El archivo no contiene filas de datos.');
        }

        $resumen = $this->_revalidarTodas($filas);
        $this->_guardarSesion([
            'archivo' => $file['name'] ?? 'catalogo.xlsx',
            'path' => $destino,
            'filas' => $filas,
            'ts' => time(),
        ]);

        Logger::log('crear', 'tienda.catalogo.carga', $actor['id'] ?? null,
            'Analizada carga masiva de catálogo: ' . $resumen['total'] . ' fila(s), ' . $resumen['errores'] . ' con error');

        return ['resumen' => $resumen, 'filas' => $filas, 'archivo' => $file['name'] ?? ''];
    }

    /** Estado actual de la carga. */
    public function pendientes(): array
    {
        $s = $this->_sesion();
        if (!$s || empty($s['filas'])) {
            return ['activa' => false, 'archivo' => '', 'filas' => [], 'resumen' => $this->_resumenVacio()];
        }
        $resumen = $this->_revalidarTodas($s['filas']);
        $this->_guardarSesion($s);
        return ['activa' => true, 'archivo' => $s['archivo'] ?? '', 'filas' => $s['filas'], 'resumen' => $resumen];
    }

    /** Edita una fila y revalida todo el conjunto (para detectar duplicados). */
    public function actualizarFila(string $filaId, array $datos, ?array $actor = null): array
    {
        $s = $this->_sesion();
        if (!$s || empty($s['filas'])) {
            throw new DomainException('No hay una carga activa. Suba un archivo primero.');
        }

        $permitidos = [
            'categoria', 'marca', 'nombre', 'descripcion',
            'precio_compra_sugerido', 'precio_venta_sugerido',
            'estado_activo', 'agotado', 'crear_categoria', 'crear_marca',
        ];
        $encontrada = false;
        foreach ($s['filas'] as &$fila) {
            if ((string) $fila['id'] !== $filaId) {
                continue;
            }
            $encontrada = true;
            $oldCat = trim((string) ($fila['datos']['categoria'] ?? ''));
            $oldMarca = trim((string) ($fila['datos']['marca'] ?? ''));
            $newCat = array_key_exists('categoria', $datos) ? trim((string) $datos['categoria']) : $oldCat;
            $newMarca = array_key_exists('marca', $datos) ? trim((string) $datos['marca']) : $oldMarca;

            foreach ($permitidos as $k) {
                if (!array_key_exists($k, $datos)) {
                    continue;
                }
                if ($k === 'crear_categoria' || $k === 'crear_marca') {
                    $fila[$k] = (bool) $datos[$k];
                } else {
                    $fila['datos'][$k] = $datos[$k];
                }
            }

            // Si cambia el nombre del maestro sin una confirmación explícita en
            // esta misma petición, se pierde la confirmación anterior.
            if ($newCat !== $oldCat && !array_key_exists('crear_categoria', $datos)) {
                $fila['crear_categoria'] = false;
            }
            if ($newMarca !== $oldMarca && !array_key_exists('crear_marca', $datos)) {
                $fila['crear_marca'] = false;
            }
            break;
        }
        unset($fila);

        if (!$encontrada) {
            throw new DomainException('Fila no encontrada en la carga activa.');
        }

        $resumen = $this->_revalidarTodas($s['filas']);
        $s['ts'] = time();
        $this->_guardarSesion($s);

        return ['resumen' => $resumen, 'filas' => $s['filas']];
    }

    /** Conteos y bloqueo para la confirmación final. */
    public function resumenCommit(): array
    {
        $s = $this->_sesion();
        if (!$s || empty($s['filas'])) {
            throw new DomainException('No hay una carga activa. Suba un archivo primero.');
        }
        $resumen = $this->_revalidarTodas($s['filas']);
        $this->_guardarSesion(['archivo' => $s['archivo'] ?? '', 'path' => $s['path'] ?? null, 'filas' => $s['filas'], 'ts' => $s['ts'] ?? time()]);
        return $resumen;
    }

    /** Aplica la carga completa en una transacción. */
    public function commit(bool $confirmar, ?array $actor = null): array
    {
        if (!$confirmar) {
            throw new DomainException('Debe confirmar expresamente la carga.');
        }
        $resumen = $this->resumenCommit();
        if (!empty($resumen['bloqueado'])) {
            throw new DomainException('La carga tiene filas con error o pendientes de confirmación. Corrija antes de cargar.');
        }

        $s = $this->_sesion();
        if (!$s || empty($s['filas'])) {
            throw new DomainException('No hay una carga activa.');
        }

        $actorId = isset($actor['id']) && $actor['id'] !== '' ? (int) $actor['id'] : null;
        $filas = $s['filas'];

        $this->db->beginTransaction();
        try {
            $catMap = $this->_crearCategoriasFaltantes($filas, $actorId);
            $marcaMap = $this->_crearMarcasFaltantes($filas, $actorId);

            $creados = 0;
            $actualizados = 0;
            foreach ($filas as &$fila) {
                if (($fila['estado'] ?? '') === 'error' || ($fila['estado'] ?? '') === 'requiere_confirmacion') {
                    throw new DomainException('La carga contiene filas sin corregir.');
                }
                if (($fila['estado'] ?? '') === 'sin_cambios') {
                    continue;
                }

                $catName = $this->_norm((string) ($fila['datos']['categoria'] ?? ''));
                $marcaName = $this->_norm((string) ($fila['datos']['marca'] ?? ''));
                $catId = (int) ($fila['categoria_id'] ?? 0) ?: (int) ($catMap[$catName] ?? 0);
                $marcaId = (int) ($fila['marca_id'] ?? 0) ?: (int) ($marcaMap[$marcaName] ?? 0);
                if ($catId <= 0 || $marcaId <= 0) {
                    throw new DomainException('No se pudo resolver la categoría o marca de la fila ' . $fila['indice'] . '.');
                }

                $productoId = (int) ($fila['producto_id'] ?? 0);
                $data = [
                    'id' => $productoId > 0 ? $productoId : 0,
                    'categoria_id' => $catId,
                    'marca_id' => $marcaId,
                    'nombre' => trim((string) ($fila['datos']['nombre'] ?? '')),
                    'descripcion' => trim((string) ($fila['datos']['descripcion'] ?? '')),
                    'precio_compra_sugerido' => $fila['datos']['precio_compra_sugerido'] ?? null,
                    'precio_venta_sugerido' => $fila['datos']['precio_venta_sugerido'] ?? null,
                    'estado_activo' => !empty($fila['datos']['estado_activo']) ? 1 : 0,
                    'agotado' => !empty($fila['datos']['agotado']) ? 1 : 0,
                ];
                $this->catalogo->guardarProducto($data, $actor, null);
                if ($productoId > 0) {
                    $actualizados++;
                } else {
                    $creados++;
                }
            }
            unset($fila);

            $this->db->commit();
            $this->_limpiarSesion($s['path'] ?? null);

            Logger::log('crear', 'tienda.catalogo.carga', $actorId,
                "Carga masiva aplicada: $creados creado(s), $actualizados actualizado(s)");

            return [
                'creados' => $creados,
                'actualizados' => $actualizados,
                'categorias_creadas' => count($catMap),
                'marcas_creadas' => count($marcaMap),
                'archivo' => $s['archivo'] ?? '',
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelar(?array $actor = null): void
    {
        $s = $this->_sesion();
        $total = isset($s['filas']) ? count($s['filas']) : 0;
        $this->_limpiarSesion($s['path'] ?? null);
        Logger::log('cancelar', 'tienda.catalogo.carga', $actor['id'] ?? null,
            "Carga masiva de catálogo cancelada ($total filas)");
    }

    // ── Validación ───────────────────────────────────────────────────────

    private function _revalidarTodas(array &$filas): array
    {
        $maps = $this->_mapas();
        $seen = [];

        foreach ($filas as $i => &$fila) {
            $d = $fila['datos'];
            $errores = [];
            $pendientes = [];
            $fila['categoria_id'] = null;
            $fila['marca_id'] = null;
            $fila['producto_id'] = null;
            $fila['puede_crear_categoria'] = false;
            $fila['puede_crear_marca'] = false;

            $catName = trim((string) ($d['categoria'] ?? ''));
            $marcaName = trim((string) ($d['marca'] ?? ''));
            $nombre = trim((string) ($d['nombre'] ?? ''));
            $catKey = $this->_norm($catName);
            $marcaKey = $this->_norm($marcaName);
            $prodKey = $this->_norm($nombre);

            if ($catName === '') $errores['categoria'] = 'Categoría requerida.';
            if ($marcaName === '') $errores['marca'] = 'Marca requerida.';
            if ($nombre === '') $errores['nombre'] = 'Nombre requerido.';

            $cat = $catKey !== '' ? ($maps['categorias'][$catKey] ?? null) : null;
            if ($cat !== null) {
                if ((int) $cat['estado_activo'] !== 1) {
                    $errores['categoria'] = 'La categoría existe pero está inactiva. Actívela primero.';
                } else {
                    $fila['categoria_id'] = (int) $cat['id'];
                    $fila['crear_categoria'] = false;
                }
            } elseif ($catName !== '') {
                if (empty($fila['crear_categoria'])) {
                    $pendientes['categoria'] = 'La categoría no existe. Confirma crearla o corrígela.';
                    $fila['puede_crear_categoria'] = true;
                }
            }

            $marca = $marcaKey !== '' ? ($maps['marcas'][$marcaKey] ?? null) : null;
            if ($marca !== null) {
                if ((int) $marca['estado_activo'] !== 1) {
                    $errores['marca'] = 'La marca existe pero está inactiva. Actívala primero.';
                } else {
                    $fila['marca_id'] = (int) $marca['id'];
                    $fila['crear_marca'] = false;
                }
            } elseif ($marcaName !== '') {
                if (empty($fila['crear_marca'])) {
                    $pendientes['marca'] = 'La marca no existe. Confirma crearla o corrígela.';
                    $fila['puede_crear_marca'] = true;
                }
            }

            $pc = $this->_normalizarNumero($d['precio_compra_sugerido'] ?? '');
            if (($d['precio_compra_sugerido'] ?? '') !== '' && $pc === null) {
                $errores['precio_compra_sugerido'] = 'Precio de compra inválido.';
            } elseif ($pc !== null && $pc < 0) {
                $errores['precio_compra_sugerido'] = 'Precio de compra no puede ser negativo.';
            }
            $pv = $this->_normalizarNumero($d['precio_venta_sugerido'] ?? '');
            if (($d['precio_venta_sugerido'] ?? '') !== '' && $pv === null) {
                $errores['precio_venta_sugerido'] = 'Precio de venta inválido.';
            } elseif ($pv !== null && $pv < 0) {
                $errores['precio_venta_sugerido'] = 'Precio de venta no puede ser negativo.';
            }
            $fila['datos']['precio_compra_sugerido'] = $pc;
            $fila['datos']['precio_venta_sugerido'] = $pv;
            $fila['datos']['estado_activo'] = $this->_parseBool($d['estado_activo'] ?? '', true);
            $fila['datos']['agotado'] = $this->_parseBool($d['agotado'] ?? '', false);

            $producto = null;
            if ($catName !== '' && $marcaName !== '' && $prodKey !== '') {
                $pKey = ($fila['categoria_id'] ?: 'c:' . $catKey) . '|' . ($fila['marca_id'] ?: 'm:' . $marcaKey) . '|' . $prodKey;
                if ($fila['categoria_id'] && $fila['marca_id']) {
                    $producto = $maps['productos'][$pKey] ?? null;
                    if ($producto !== null) {
                        $fila['producto_id'] = (int) $producto['id'];
                    }
                }
                if (isset($seen[$pKey])) {
                    $errores['duplicado'] = 'Duplicado con la fila ' . ($filas[$seen[$pKey]]['indice'] ?? '');
                    $filas[$seen[$pKey]]['errores']['duplicado'] = 'Duplicado con la fila ' . $fila['indice'];
                    $filas[$seen[$pKey]]['estado'] = 'error';
                } else {
                    $seen[$pKey] = $i;
                }
            }

            $accion = 'crear';
            if ($producto !== null) {
                $accion = $this->_sinCambios($fila, $producto) ? 'sin_cambios' : 'actualizar';
            }

            $fila['errores'] = array_merge($errores, $pendientes);
            if ($errores) {
                $fila['estado'] = 'error';
            } elseif ($pendientes) {
                $fila['estado'] = 'requiere_confirmacion';
            } elseif ((!$fila['categoria_id'] && empty($fila['crear_categoria'])) || (!$fila['marca_id'] && empty($fila['crear_marca']))) {
                $fila['estado'] = 'requiere_confirmacion';
            } else {
                $fila['estado'] = $accion;
            }
        }
        unset($fila);

        return $this->_resumen($filas, $maps);
    }

    private function _sinCambios(array $fila, array $producto): bool
    {
        $d = $fila['datos'];
        return trim((string) ($d['descripcion'] ?? '')) === trim((string) ($producto['descripcion'] ?? ''))
            && $this->_numEq($d['precio_compra_sugerido'], $producto['precio_compra_sugerido'])
            && $this->_numEq($d['precio_venta_sugerido'], $producto['precio_venta_sugerido'])
            && ((int) ($d['estado_activo'] ?? 1) === (int) $producto['estado_activo'])
            && ((int) ($d['agotado'] ?? 0) === (int) $producto['agotado']);
    }

    private function _numEq($a, $b): bool
    {
        $aEmpty = ($a === null || $a === '');
        $bEmpty = ($b === null || $b === '');
        if ($aEmpty || $bEmpty) return $aEmpty && $bEmpty;
        return abs((float) $a - (float) $b) < 0.01;
    }

    private function _resumen(array $filas, array $maps): array
    {
        $out = $this->_resumenVacio();
        $out['total'] = count($filas);
        $nuevasCat = [];
        $nuevasMarca = [];

        foreach ($filas as $f) {
            $estado = (string) ($f['estado'] ?? 'error');
            if ($estado === 'crear') $out['crear']++;
            elseif ($estado === 'actualizar') $out['actualizar']++;
            elseif ($estado === 'sin_cambios') $out['sin_cambios']++;
            elseif ($estado === 'requiere_confirmacion') $out['requiere_confirmacion']++;
            else $out['errores']++;

            if (!empty($f['crear_categoria'])) {
                $key = $this->_norm((string) ($f['datos']['categoria'] ?? ''));
                if ($key !== '' && !isset($maps['categorias'][$key])) $nuevasCat[$key] = (string) $f['datos']['categoria'];
            }
            if (!empty($f['crear_marca'])) {
                $key = $this->_norm((string) ($f['datos']['marca'] ?? ''));
                if ($key !== '' && !isset($maps['marcas'][$key])) $nuevasMarca[$key] = (string) $f['datos']['marca'];
            }
        }

        $out['nuevas_categorias'] = array_values($nuevasCat);
        $out['nuevas_marcas'] = array_values($nuevasMarca);
        $out['bloqueado'] = ($out['errores'] > 0 || $out['requiere_confirmacion'] > 0);
        return $out;
    }

    private function _resumenVacio(): array
    {
        return [
            'total' => 0, 'crear' => 0, 'actualizar' => 0, 'sin_cambios' => 0,
            'requiere_confirmacion' => 0, 'errores' => 0,
            'nuevas_categorias' => [], 'nuevas_marcas' => [], 'bloqueado' => true,
        ];
    }

    // ── Maestros ─────────────────────────────────────────────────────────

    private function _crearCategoriasFaltantes(array $filas, ?int $actorId): array
    {
        $map = [];
        foreach ($filas as $f) {
            if (empty($f['crear_categoria'])) continue;
            $nombre = trim((string) ($f['datos']['categoria'] ?? ''));
            $key = $this->_norm($nombre);
            if ($nombre === '' || $key === '' || isset($map[$key])) continue;
            $map[$key] = $this->_crearCategoria($nombre, $actorId);
        }
        return $map;
    }

    private function _crearMarcasFaltantes(array $filas, ?int $actorId): array
    {
        $map = [];
        foreach ($filas as $f) {
            if (empty($f['crear_marca'])) continue;
            $nombre = trim((string) ($f['datos']['marca'] ?? ''));
            $key = $this->_norm($nombre);
            if ($nombre === '' || $key === '' || isset($map[$key])) continue;
            $map[$key] = $this->_crearMarca($nombre, $actorId);
        }
        return $map;
    }

    private function _crearCategoria(string $nombre, ?int $actorId): int
    {
        $st = $this->db->prepare('SELECT id, estado_activo FROM tienda_categorias WHERE nombre = ? LIMIT 1');
        $st->execute([$nombre]);
        $r = $st->fetch();
        if ($r) {
            if ((int) $r['estado_activo'] !== 1) {
                throw new DomainException('La categoría "' . $nombre . '" existe pero está inactiva.');
            }
            return (int) $r['id'];
        }
        $this->db->prepare(
            'INSERT INTO tienda_categorias (nombre, estado_activo, creado_por) VALUES (?,1,?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        )->execute([$nombre, $actorId]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'tienda.catalogo.carga', $actorId !== null ? (string) $actorId : null,
            "Categoría creada desde carga masiva: $nombre (#$id)");
        return $id;
    }

    private function _crearMarca(string $nombre, ?int $actorId): int
    {
        $st = $this->db->prepare('SELECT id, estado_activo FROM tienda_marcas WHERE nombre = ? LIMIT 1');
        $st->execute([$nombre]);
        $r = $st->fetch();
        if ($r) {
            if ((int) $r['estado_activo'] !== 1) {
                throw new DomainException('La marca "' . $nombre . '" existe pero está inactiva.');
            }
            return (int) $r['id'];
        }
        $this->db->prepare(
            'INSERT INTO tienda_marcas (nombre, estado_activo, creado_por) VALUES (?,1,?)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)'
        )->execute([$nombre, $actorId]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'tienda.catalogo.carga', $actorId !== null ? (string) $actorId : null,
            "Marca creada desde carga masiva: $nombre (#$id)");
        return $id;
    }

    // ── Mapas / helpers ──────────────────────────────────────────────────

    private function _mapas(): array
    {
        $categorias = [];
        foreach ($this->db->query('SELECT id, nombre, estado_activo FROM tienda_categorias')->fetchAll() as $r) {
            $categorias[$this->_norm((string) $r['nombre'])] = $r;
        }
        $marcas = [];
        foreach ($this->db->query('SELECT id, nombre, estado_activo FROM tienda_marcas')->fetchAll() as $r) {
            $marcas[$this->_norm((string) $r['nombre'])] = $r;
        }
        $productos = [];
        foreach ($this->db->query(
            'SELECT id, categoria_id, marca_id, nombre, descripcion, precio_compra_sugerido,
                    precio_venta_sugerido, estado_activo, agotado
               FROM tienda_productos'
        )->fetchAll() as $r) {
            $key = $r['categoria_id'] . '|' . $r['marca_id'] . '|' . $this->_norm((string) $r['nombre']);
            $productos[$key] = $r;
        }
        return ['categorias' => $categorias, 'marcas' => $marcas, 'productos' => $productos];
    }

    private function _extraer(array $r): array
    {
        $norm = [];
        foreach ($r as $k => $v) {
            $key = mb_strtoupper(trim((string) $k));
            $key = strtr($key, [
                'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
                'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ü' => 'U', 'ñ' => 'N',
            ]);
            $norm[$key] = $v;
        }
        $get = static fn(string $key): string => (string) ($norm[$key] ?? '');
        return [
            'categoria' => trim($get('CATEGORIA')),
            'marca' => trim($get('MARCA')),
            'nombre' => trim($get('NOMBRE')),
            'descripcion' => trim($get('DESCRIPCION')),
            'precio_compra_sugerido' => trim($get('PRECIO_COMPRA_SUGERIDO')),
            'precio_venta_sugerido' => trim($get('PRECIO_VENTA_SUGERIDO')),
            'estado_activo' => trim($get('ESTADO_ACTIVO')),
            'agotado' => trim($get('AGOTADO')),
        ];
    }

    private function _norm(string $s): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? $s));
        return strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private function _parseBool($v, bool $default = false): bool
    {
        if ($v === null || trim((string) $v) === '') return $default;
        if (is_bool($v)) return $v;
        if (is_int($v) || is_float($v)) return ((int) $v) === 1;
        $s = mb_strtolower(trim((string) $v));
        return in_array($s, ['1', 'si', 'sí', 's', 'yes', 'y', 'true', 'x'], true);
    }

    private static function _normalizarNumero($v): ?float
    {
        if ($v === null) return null;
        if (is_int($v) || is_float($v)) return (float) $v;
        $s = trim((string) $v);
        if ($s === '') return null;
        if (str_contains($s, ',') && str_contains($s, '.')) {
            if (strrpos($s, ',') > strrpos($s, '.')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } else {
                $s = str_replace(',', '', $s);
            }
        } elseif (str_contains($s, ',')) {
            $s = str_replace(',', '.', $s);
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        }
        if (!preg_match('/^-?\d*\.?\d+$/', $s)) return null;
        return (float) $s;
    }

    private function _tempDir(): string
    {
        $base = rtrim((string) sys_get_temp_dir(), '/');
        $dir = $base . '/cargacatalogo_' . bin2hex(random_bytes(6));
        @mkdir($dir, 0700, true);
        return $dir;
    }

    private function _sesion(): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $s = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_array($s) && isset($s['ts']) && (time() - (int) $s['ts']) > 3600) {
            $this->_limpiarSesion($s['path'] ?? null);
            return null;
        }
        return $s;
    }

    private function _guardarSesion(array $d): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        $_SESSION[self::SESSION_KEY] = $d;
    }

    private function _limpiarSesion(?string $path = null): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
        unset($_SESSION[self::SESSION_KEY]);
        if ($path && is_file($path)) {
            @unlink($path);
            @rmdir(dirname($path));
        }
    }
}
