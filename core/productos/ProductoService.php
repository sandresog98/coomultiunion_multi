<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * CRUD de productos/cláusulas y sus asignaciones a asociados.
 * B4. Tablas: productos + asociados_productos.
 */
final class ProductoService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Catálogo de productos y cláusulas activas, para usar en <select>. */
    public function listarTiposActivos(bool $basico = false): array
    {
        $cols = $basico
            ? 'id, nombre, tipo, valor_minimo, valor_maximo, requiere_archivo'
            : 'id, nombre, tipo, valor_minimo, valor_maximo, parametros, requiere_archivo';
        return $this->db->query(
            "SELECT $cols
             FROM productos WHERE estado_activo = 1 ORDER BY tipo, prioridad, nombre"
        )->fetchAll();
    }

    /** Asignaciones activas de un asociado (productos + cláusulas). */
    public function asignacionesPorAsociado(string $cedula): array
    {
        $st = $this->db->prepare(
            "SELECT ap.id, ap.producto_id, ap.monto, ap.dia_pago, ap.fecha_inicio,
                    ap.meses_vigencia, ap.detalle, ap.estado_activo,
                    p.nombre AS producto_nombre, p.tipo, p.requiere_archivo,
                    p.valor_minimo, p.valor_maximo, p.producto_validar_id,
                    pv.nombre AS producto_validar_nombre
             FROM asociados_productos ap
             INNER JOIN productos p ON p.id = ap.producto_id
             LEFT JOIN productos pv ON pv.id = p.producto_validar_id
              WHERE ap.cedula = ? AND p.estado_activo = 1
             ORDER BY p.tipo, p.nombre"
        );
        $st->execute([$cedula]);
        $rows = $st->fetchAll();

        $criteria = [];
        $minDesde = null;
        $maxHasta = null;
        $necesitaAportes = false;
        $necesitaProductos = false;

        foreach ($rows as $i => &$r) {
            $r['vigente'] = null;
            $r['fecha_fin'] = null;
            $r['meses_cumplidos'] = null;
            $r['meses_restantes'] = null;
            $r['pagos_realizados'] = null;
            $r['pagos_esperados'] = null;
            $r['pagos_en_rango'] = null;
            $r['total_meses'] = null;
            $r['estado_vigencia'] = null;

            if ((string) ($r['tipo'] ?? '') !== 'clausula') {
                continue;
            }

            $inicio = trim((string) ($r['fecha_inicio'] ?? ''));
            $meses  = (int) ($r['meses_vigencia'] ?? 0);
            if ($inicio === '' || $meses <= 0) {
                continue;
            }

            try {
                $desde = new DateTime($inicio);
                $hoy   = new DateTime('today');
            } catch (Throwable $e) {
                continue;
            }

            $fin = (clone $desde)->modify('+' . $meses . ' months');
            $finStr = $fin->format('Y-m-d');
            $r['fecha_fin'] = $finStr;
            $r['total_meses'] = $meses;
            $r['vigente'] = ((int) $r['estado_activo'] === 1) && $hoy <= $fin;

            $diff = $desde->diff($hoy);
            $cumplidos = ((int) $diff->y) * 12 + (int) $diff->m;
            if ($diff->invert) {
                $cumplidos = 0;
            }
            $cumplidos = max(0, min($meses, $cumplidos));
            $r['meses_cumplidos'] = $cumplidos;
            $r['meses_restantes'] = max(0, $meses - $cumplidos);
            $r['pagos_esperados'] = $cumplidos;

            $validarId = (int) ($r['producto_validar_id'] ?? 0);
            $esAporte = $validarId > 0
                && str_contains($this->normalizarNombre((string) ($r['producto_validar_nombre'] ?? '')), 'aporte');

            // Los conteos se resuelven en bloque después del bucle (evita N+1).
            $criteria[$i] = [
                'inicio'     => $inicio,
                'fin'        => $finStr,
                'meses'      => $meses,
                'cumplidos'  => $cumplidos,
                'ya_vencio'  => $hoy > $fin,
                'validar_id' => $validarId,
                'es_aporte'  => $esAporte,
            ];
            if ($validarId > 0) {
                if ($esAporte) { $necesitaAportes = true; } else { $necesitaProductos = true; }
                if ($minDesde === null || $inicio < $minDesde) { $minDesde = $inicio; }
                if ($maxHasta === null || $finStr > $maxHasta) { $maxHasta = $finStr; }
            }
        }
        unset($r);

        $pagosAportes = [];
        $pagosProducto = [];
        if ($minDesde !== null && $maxHasta !== null) {
            if ($necesitaAportes) { $pagosAportes = $this->fechasPagosAportes($cedula, $minDesde, $maxHasta); }
            if ($necesitaProductos) { $pagosProducto = $this->fechasPagosProducto($cedula, $minDesde, $maxHasta); }
        }

        foreach ($criteria as $i => $c) {
            $pagados = 0;
            if ($c['validar_id'] > 0) {
                $fechas = $c['es_aporte']
                    ? $pagosAportes
                    : ($pagosProducto[$c['validar_id']] ?? []);
                $mesesPagados = [];
                foreach ($fechas as $fechaPago) {
                    if ($fechaPago >= $c['inicio'] && $fechaPago <= $c['fin']) {
                        $mesesPagados[substr($fechaPago, 0, 7)] = true;
                    }
                }
                $pagados = count($mesesPagados);
            }

            $rows[$i]['pagos_en_rango'] = min($c['meses'], $pagados);
            $rows[$i]['pagos_realizados'] = min($c['meses'], $pagados);

            // Estado de cumplimiento:
            //  - cumplida / incumplida cuando la fecha fin ya pasó
            //  - nueva en el primer mes
            //  - cumpliendo / incumpliendo mientras siga vigente
            if ($c['ya_vencio']) {
                $rows[$i]['estado_vigencia'] = ($rows[$i]['pagos_realizados'] >= $c['meses']) ? 'cumplida' : 'incumplida';
            } elseif ($c['cumplidos'] === 0) {
                $rows[$i]['estado_vigencia'] = 'nueva';
            } elseif ($rows[$i]['pagos_realizados'] >= $c['cumplidos']) {
                $rows[$i]['estado_vigencia'] = 'cumpliendo';
            } else {
                $rows[$i]['estado_vigencia'] = 'incumpliendo';
            }
        }

        return $rows;
    }

    /** Asigna un producto/cláusula a un asociado. */
    public function asignar(string $cedula, int $productoId, array $datos, ?array $actor = null): int
    {
        $prod = $this->db->prepare("SELECT tipo, valor_minimo, valor_maximo FROM productos WHERE id = ? AND estado_activo = 1");
        $prod->execute([$productoId]);
        $p = $prod->fetch();
        if (!$p) {
            throw new DomainException('Producto no encontrado o inactivo.');
        }

        $monto = (float) ($datos['monto'] ?? 0);
        if ($monto <= 0) {
            throw new DomainException('El monto debe ser mayor a cero.');
        }

        $esClausula = $p['tipo'] === 'clausula';
        $diaPago = null;
        $fechaInicio = null;
        $mesesVigencia = null;

        if ($esClausula) {
            $fechaInicio = trim((string) ($datos['fecha_inicio'] ?? ''));
            if ($fechaInicio === '') throw new DomainException('La fecha de inicio es obligatoria.');
            $mesesVigencia = (int) ($datos['meses_vigencia'] ?? 0);
            if ($mesesVigencia <= 0) throw new DomainException('Los meses de vigencia son obligatorios.');
        } else {
            $diaPago = (int) ($datos['dia_pago'] ?? 1);
            if ($diaPago < 1 || $diaPago > 31) throw new DomainException('Día de pago inválido.');
        }

        $detalle = isset($datos['detalle']) ? (string) $datos['detalle'] : null;

        $st = $this->db->prepare(
            "INSERT INTO asociados_productos (cedula, producto_id, monto, dia_pago, fecha_inicio, meses_vigencia, detalle, estado_activo, creado_por)
             VALUES (?,?,?,?,?,?,?,1,?)"
        );
        $st->execute([$cedula, $productoId, $monto, $diaPago, $fechaInicio, $mesesVigencia, $detalle, $actor['id'] ?? null]);

        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'productos.asignar', $actor['id'] ?? null, "Asignado producto #$productoId a $cedula");
        return $id;
    }

    /** Edita una asignación existente. */
    public function editar(int $id, array $datos, ?array $actor = null): void
    {
        $actual = $this->uno("SELECT ap.*, p.tipo, p.valor_minimo, p.valor_maximo FROM asociados_productos ap INNER JOIN productos p ON p.id = ap.producto_id WHERE ap.id = ?", [$id]);
        if (!$actual) {
            throw new DomainException('Asignación no encontrada.');
        }

        $monto = (float) ($datos['monto'] ?? $actual['monto']);
        if ($monto <= 0) {
            throw new DomainException('El monto debe ser mayor a cero.');
        }

        $esClausula = $actual['tipo'] === 'clausula';
        $diaPago = $actual['dia_pago'];

        if ($esClausula) {
            $fechaInicio = !empty($datos['fecha_inicio']) ? (string) $datos['fecha_inicio'] : $actual['fecha_inicio'];
            $mesesVigencia = isset($datos['meses_vigencia']) ? (int) $datos['meses_vigencia'] : $actual['meses_vigencia'];
            $this->db->prepare(
                "UPDATE asociados_productos SET monto=?, fecha_inicio=?, meses_vigencia=?, detalle=?, estado_activo=? WHERE id=?"
            )->execute([$monto, $fechaInicio, $mesesVigencia, $datos['detalle'] ?? $actual['detalle'], (int) ($datos['estado_activo'] ?? $actual['estado_activo']), $id]);
        } else {
            $diaPago = isset($datos['dia_pago']) ? (int) $datos['dia_pago'] : $actual['dia_pago'];
            if ($diaPago < 1 || $diaPago > 31) throw new DomainException('Día de pago inválido.');
            $this->db->prepare(
                "UPDATE asociados_productos SET monto=?, dia_pago=?, estado_activo=? WHERE id=?"
            )->execute([$monto, $diaPago, (int) ($datos['estado_activo'] ?? $actual['estado_activo']), $id]);
        }

        $soloToggle = count($datos) <= 2 && array_key_exists('id', $datos) && array_key_exists('estado_activo', $datos);
        if ($soloToggle) {
            $nuevoEstado = (int) ($datos['estado_activo'] ?? 0);
            $accion = $nuevoEstado ? 'activar' : 'inactivar';
            Logger::log('editar', 'productos.asignar', $actor['id'] ?? null,
                ($nuevoEstado ? 'Activada' : 'Inactivada') . " asignación #$id", null, null, 'warning');
        } else {
            Logger::log('editar', 'productos.asignar', $actor['id'] ?? null, "Editada asignación #$id");
        }
    }

    /** Baja lógica de una asignación. */
    public function eliminar(int $id, ?array $actor = null): void
    {
        $this->db->prepare("UPDATE asociados_productos SET estado_activo = 0 WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'productos.asignar', $actor['id'] ?? null, "Desactivada asignación #$id", null, null, 'warning');
    }

    /** ── Catálogo de productos/cláusulas ── */

    private const CAT_ORDER_COLS = ['id', 'nombre', 'tipo', 'prioridad', 'estado_activo'];

    /** Listado paginado del catálogo con búsqueda y ordenamiento. */
    public function catalogoListar(string $q = '', string $orderBy = 'tipo', string $orderDir = 'ASC', int $limit = 20, int $offset = 0): array
    {
        if (!in_array($orderBy, self::CAT_ORDER_COLS, true)) { $orderBy = 'tipo'; }
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE nombre LIKE ? OR descripcion LIKE ?";
            $like = '%' . $q . '%';
            $params = [$like, $like];
        }

        $stTotal = $this->db->prepare("SELECT COUNT(*) FROM productos $where");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $st = $this->db->prepare(
            "SELECT p.*, (SELECT pv.nombre FROM productos pv WHERE pv.id = p.producto_validar_id) AS producto_validar_nombre
             FROM productos p $where ORDER BY `$orderBy` $orderDir, nombre ASC LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Crea un nuevo tipo de producto o cláusula. */
    public function catalogoCrear(array $data, ?array $actor = null): int
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre es obligatorio.');
        $tipo = trim((string) ($data['tipo'] ?? 'producto'));
        if (!in_array($tipo, ['producto', 'clausula'], true)) throw new DomainException('Tipo inválido.');
        $productoValidarId = (int) ($data['producto_validar_id'] ?? 0) ?: null;
        if ($tipo !== 'clausula') {
            $productoValidarId = null;
        } elseif ($productoValidarId !== null && !$this->productoParaValidarValido($productoValidarId)) {
            throw new DomainException('El producto a validar no existe, no es de tipo producto o está inactivo.');
        }
        $st = $this->db->prepare(
            "INSERT INTO productos (nombre, descripcion, tipo, parametros, producto_validar_id, requiere_archivo,
             valor_minimo, valor_maximo, prioridad, estado_activo, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $nombre,
            trim((string) ($data['descripcion'] ?? '')),
            $tipo,
            trim((string) ($data['parametros'] ?? '')),
            $productoValidarId,
            ($data['requiere_archivo'] ?? false) ? 1 : 0,
            (float) ($data['valor_minimo'] ?? 0),
            (float) ($data['valor_maximo'] ?? 0),
            (int) ($data['prioridad'] ?? 100),
            ($data['estado_activo'] ?? true) ? 1 : 0,
            $actor['id'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'productos.catalogo', $actor['id'] ?? null, "Creado '$nombre' ($tipo) #$id");
        return $id;
    }

    /** Edita un tipo de producto o cláusula. */
    public function catalogoEditar(int $id, array $data, ?array $actor = null): void
    {
        $actual = $this->uno("SELECT * FROM productos WHERE id = ?", [$id]);
        if (!$actual) throw new DomainException('Producto no encontrado.');

        $fields = [];
        $vals = [];
        $tipoFinal = array_key_exists('tipo', $data) && in_array($data['tipo'], ['producto', 'clausula'], true)
            ? (string) $data['tipo']
            : (string) $actual['tipo'];
        $map = [
            'nombre' => fn($v) => trim((string) $v), 'descripcion' => fn($v) => trim((string) $v),
            'tipo' => fn($v) => in_array($v, ['producto', 'clausula'], true) ? $v : $actual['tipo'],
            'parametros' => fn($v) => trim((string) $v), 'requiere_archivo' => fn($v) => $v ? 1 : 0,
            'valor_minimo' => fn($v) => (float) $v, 'valor_maximo' => fn($v) => (float) $v,
            'prioridad' => fn($v) => (int) $v, 'estado_activo' => fn($v) => $v ? 1 : 0,
        ];
        foreach ($map as $k => $fn) {
            if (array_key_exists($k, $data)) {
                $fields[] = "`$k` = ?";
                $vals[] = $fn($data[$k]);
            }
        }

        // Producto a validar: solo aplica a cláusulas.
        $pid = (int) ($data['producto_validar_id'] ?? 0) ?: null;
        if ($tipoFinal !== 'clausula') {
            $pid = null;
        } elseif ($pid !== null && !$this->productoParaValidarValido($pid)) {
            throw new DomainException('El producto a validar no existe, no es de tipo producto o está inactivo.');
        }
        if (array_key_exists('producto_validar_id', $data) || $tipoFinal !== 'clausula' || array_key_exists('tipo', $data)) {
            $fields[] = "producto_validar_id = ?";
            $vals[] = $pid;
        }

        if ($fields) {
            $fields[] = "actualizado_por = ?";
            $vals[] = $actor['id'] ?? null;
            $vals[] = $id;
            $this->db->prepare("UPDATE productos SET " . implode(', ', $fields) . " WHERE id = ?")->execute($vals);
            Logger::log('editar', 'productos.catalogo', $actor['id'] ?? null, "Editado '{$actual['nombre']}' #$id");
        }
    }

    /** Baja lógica de un producto/cláusula del catálogo. */
    public function catalogoEliminar(int $id, ?array $actor = null): void
    {
        $this->db->prepare("UPDATE productos SET estado_activo = 0 WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'productos.catalogo', $actor['id'] ?? null, "Desactivado producto #$id", null, null, 'warning');
    }

    private function normalizarNombre(string $s): string
    {
        $s = mb_strtolower(trim($s));
        return strtr($s, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ü' => 'u', 'ñ' => 'n',
        ]);
    }

    private function productoParaValidarValido(int $id): bool
    {
        $st = $this->db->prepare("SELECT id FROM productos WHERE id = ? AND tipo = 'producto' AND estado_activo = 1 LIMIT 1");
        $st->execute([$id]);
        return (bool) $st->fetchColumn();
    }

    /** Fechas de aportes ordinarios (cuenta 31050501) en el rango [desde, hasta]. */
    private function fechasPagosAportes(string $cedula, string $desde, string $hasta): array
    {
        $st = $this->db->prepare(
            "SELECT fecham
               FROM sifone_movimientos_tributarios
              WHERE cedula = ? AND cuenta = '31050501'
                AND COALESCE(credit, 0) > 0
                AND fecham >= ?
                AND fecham < DATE_ADD(?, INTERVAL 1 DAY)"
        );
        $st->execute([$cedula, $desde, $hasta]);
        $out = [];
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $f) {
            if ($f !== null && $f !== '') { $out[] = (string) $f; }
        }
        return $out;
    }

    /** Pagos aplicados por producto en el rango [desde, hasta], agrupados por producto_id. */
    private function fechasPagosProducto(string $cedula, string $desde, string $hasta): array
    {
        $st = $this->db->prepare(
            "SELECT DATE(t.fecha_creacion) AS fecha, d.producto_id
               FROM transacciones t
               INNER JOIN transacciones_detalle d ON d.transaccion_id = t.id
              WHERE t.cedula = ?
                AND d.tipo_rubro = 'producto'
                AND COALESCE(d.valor_asignado, 0) > 0
                AND t.fecha_creacion >= ?
                AND t.fecha_creacion <= ?"
        );
        $st->execute([$cedula, $desde . ' 00:00:00', $hasta . ' 23:59:59']);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[(int) $r['producto_id']][] = (string) $r['fecha'];
        }
        return $out;
    }

    private function uno(string $sql, array $p): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetch() ?: null;
    }
}
