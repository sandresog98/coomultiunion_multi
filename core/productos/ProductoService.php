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
    public function listarTiposActivos(): array
    {
        return $this->db->query(
            "SELECT id, nombre, tipo, valor_minimo, valor_maximo, parametros, requiere_archivo
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
                    p.valor_minimo, p.valor_maximo
             FROM asociados_productos ap
             INNER JOIN productos p ON p.id = ap.producto_id
              WHERE ap.cedula = ? AND p.estado_activo = 1
             ORDER BY p.tipo, p.nombre"
        );
        $st->execute([$cedula]);
        return $st->fetchAll();
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
            "SELECT * FROM productos $where ORDER BY `$orderBy` $orderDir, nombre ASC LIMIT ? OFFSET ?"
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
        $st = $this->db->prepare(
            "INSERT INTO productos (nombre, descripcion, tipo, parametros, requiere_archivo,
             valor_minimo, valor_maximo, prioridad, estado_activo, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $nombre,
            trim((string) ($data['descripcion'] ?? '')),
            $tipo,
            trim((string) ($data['parametros'] ?? '')),
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

    private function uno(string $sql, array $p): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetch() ?: null;
    }
}
