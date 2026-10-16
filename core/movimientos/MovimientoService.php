<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once __DIR__ . '/MovimientoTipoService.php';

/**
 * Gestión de movimientos genéricos en `movimientos_multi`.
 * B4/unificación. Los TIPOS provienen del catálogo `movimientos_tipos` (configurable):
 * cada tipo define clasificacion (ingreso|egreso), requiere_referido y valida_disponible.
 * El signo del valor se deriva de la clasificación (ingreso +, egreso −).
 * 'valida_disponible' limita el egreso a `vw_productos.comisiones` (comisiones netas).
 */
final class MovimientoService
{
    private const ORDER_COLS = ['id', 'fecha', 'tipo', 'valor'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Catálogo de tipos activos para selects. */
    public function tipos(): array
    {
        return (new MovimientoTipoService($this->db))->all(true);
    }

    /** Disponible para tipos que validan vs comisiones netas (redencion_comisiones). */
    public function disponibleRedencion(string $cedula): float
    {
        $st = $this->db->prepare("SELECT COALESCE(comisiones, 0) FROM vw_productos WHERE cedula = ?");
        $st->execute([$cedula]);
        return (float) ($st->fetchColumn() ?? 0);
    }

    public function detalleDisponible(string $cedula): array
    {
        $disponible = $this->disponibleRedencion($cedula);
        return ['disponible' => $disponible, 'comisiones' => $disponible];
    }

    /** Lista movimientos con filtros, orden y paginación. */
    public function listar(
        string $q = '',
        string $tipo = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        string $orderBy = 'fecha',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        if (!in_array($orderBy, self::ORDER_COLS, true)) { $orderBy = 'fecha'; }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(m.cedula LIKE ? OR sa.nombre LIKE ? OR COALESCE(m.detalle,'') LIKE ?)";
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        if ($tipo !== '') {
            $where[] = 'm.tipo = ?';
            $params[] = $tipo;
        }
        if ($fechaDesde !== '') { $where[] = "DATE(m.fecha) >= ?"; $params[] = $fechaDesde; }
        if ($fechaHasta !== '') { $where[] = "DATE(m.fecha) <= ?"; $params[] = $fechaHasta; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM movimientos_multi m LEFT JOIN sifone_asociados sa ON sa.cedula = m.cedula $w"
        );
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->db->prepare(
            "SELECT m.id, m.cedula, sa.nombre AS nombre_asociado, m.tipo, m.clasificacion, m.valor, m.fecha,
                    m.detalle, m.fecha_creacion,
                    u.nombre_completo AS creado_por_nombre
             FROM movimientos_multi m
             LEFT JOIN sifone_asociados sa ON sa.cedula = m.cedula
             LEFT JOIN usuarios u ON u.id = m.creado_por
             $w ORDER BY `$orderBy` $orderDir, m.id DESC LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        $labels = $this->labels();
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $raw = (string) ($r['detalle'] ?? '');
            $dec = $raw !== '' ? json_decode($raw, true) : null;
            $r['detalle'] = is_array($dec) ? $dec : ['texto' => $raw];
            $r['label'] = $labels[$r['tipo']] ?? $r['tipo'];
        }
        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Crea un movimiento (ingreso o egreso). */
    public function crear(array $data, ?array $actor = null): int
    {
        [$cedula, $tipo, $clasificacion, $valor, $reqRef] = $this->normalizar($data, null);

        $fecha = trim((string) ($data['fecha'] ?? ''));
        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new DomainException('Fecha inválida (YYYY-MM-DD).');

        $referido = trim((string) ($data['asociado_referido'] ?? ''));
        $obs = trim((string) ($data['observaciones'] ?? ''));
        if ($reqRef && $referido === '') throw new DomainException('Para este tipo es obligatorio el asociado referido.');
        if ($reqRef && $referido === $cedula) throw new DomainException('El asociado referido no puede ser el mismo.');

        $detalle = json_encode(['asociado_referido' => $referido, 'observaciones' => $obs], JSON_UNESCAPED_UNICODE);
        $st = $this->db->prepare(
            "INSERT INTO movimientos_multi (cedula, tipo, clasificacion, valor, fecha, detalle, creado_por)
             VALUES (?,?,?,?,?,?,?)"
        );
        $st->execute([$cedula, $tipo, $clasificacion, $valor, $fecha, $detalle, $actor['id'] ?? null]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'movimientos', $actor['id'] ?? null, "Registrado movimiento #$id ($tipo/$clasificacion, $valor) a $cedula");
        return $id;
    }

    /** Edita un movimiento. */
    public function editar(int $id, array $data, ?array $actor = null): void
    {
        $actual = $this->uno($id);
        if (!$actual) throw new DomainException('Movimiento no encontrado.');

        $payload = $data;
        $payload['cedula'] = $payload['cedula'] ?? $actual['cedula'];
        $payload['tipo'] = $payload['tipo'] ?? $actual['tipo'];
        $payload['valor'] = $payload['valor'] ?? (float) $actual['valor'];

        [$cedula, $tipo, $clasificacion, $valor, $reqRef] = $this->normalizar($payload, $actual);

        $fecha = trim((string) ($payload['fecha'] ?? $actual['fecha']));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) throw new DomainException('Fecha inválida (YYYY-MM-DD).');

        $referido = trim((string) ($payload['asociado_referido'] ?? ($actual['detalle']['asociado_referido'] ?? '')));
        $obs = trim((string) ($payload['observaciones'] ?? ($actual['detalle']['observaciones'] ?? '')));
        if ($reqRef && $referido === '') throw new DomainException('Para este tipo es obligatorio el asociado referido.');
        if ($reqRef && $referido === $cedula) throw new DomainException('El asociado referido no puede ser el mismo.');

        $detalle = json_encode(['asociado_referido' => $referido, 'observaciones' => $obs], JSON_UNESCAPED_UNICODE);
        $this->db->prepare(
            "UPDATE movimientos_multi SET cedula=?, tipo=?, clasificacion=?, valor=?, fecha=?, detalle=? WHERE id=?"
        )->execute([$cedula, $tipo, $clasificacion, $valor, $fecha, $detalle, $id]);
        Logger::log('editar', 'movimientos', $actor['id'] ?? null, "Editado movimiento #$id");
    }

    /** Normaliza tipo/clasificación/valor según el catálogo. */
    private function normalizar(array $d, ?array $actual): array
    {
        $cedula = trim((string) ($d['cedula'] ?? ($actual['cedula'] ?? '')));
        if ($cedula === '') throw new DomainException('Falta la cédula del asociado.');

        $tipo = trim((string) ($d['tipo'] ?? ($actual['tipo'] ?? '')));
        $st = $this->db->prepare(
            "SELECT tipo, clasificacion, requiere_referido, valida_disponible, activo FROM movimientos_tipos WHERE tipo = ?"
        );
        $st->execute([$tipo]);
        $cat = $st->fetch();
        if (!$cat) throw new DomainException('Tipo de movimiento inválido.');
        if (!$cat['activo'] && ($actual['tipo'] ?? '') !== $tipo) {
            throw new DomainException('El tipo de movimiento está inactivo.');
        }

        $clasificacion = $cat['clasificacion'];
        $reqRef = (int) $cat['requiere_referido'];

        $valor = abs((float) ($d['valor'] ?? ($actual['valor'] ?? 0)));
        if ($valor <= 0) throw new DomainException('El valor debe ser mayor a cero.');

        if ((int) $cat['valida_disponible']) {
            $disp = $this->disponibleRedencion($cedula);
            if (($actual['tipo'] ?? '') === $tipo) {
                $disp += abs((float) $actual['valor']); // excluye esta misma redención
            }
            if ($valor > $disp) {
                throw new DomainException('La redención no puede superar el disponible (' . number_format($disp, 0, ',', '.') . ').');
            }
        }

        $valor = $clasificacion === 'egreso' ? -$valor : $valor;
        return [$cedula, $tipo, $clasificacion, $valor, $reqRef];
    }

    private function labels(): array
    {
        $map = [];
        foreach ($this->db->query("SELECT tipo, nombre FROM movimientos_tipos")->fetchAll() as $r) {
            $map[$r['tipo']] = $r['nombre'];
        }
        return $map;
    }

    /** Elimina un movimiento. */
    public function eliminar(int $id, ?array $actor = null): void
    {
        if (!$this->uno($id)) throw new DomainException('Movimiento no encontrado.');
        $this->db->prepare("DELETE FROM movimientos_multi WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'movimientos', $actor['id'] ?? null, "Eliminado movimiento #$id", null, null, 'warning');
    }

    private function uno(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT m.*, m.detalle AS det FROM movimientos_multi m WHERE m.id = ?"
        );
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) return null;
        $raw = (string) ($r['det'] ?? '');
        $dec = $raw !== '' ? json_decode($raw, true) : null;
        $r['detalle'] = is_array($dec) ? $dec : ['texto' => $raw];
        unset($r['det']);
        return $r;
    }
}