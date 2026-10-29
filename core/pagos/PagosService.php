<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Consulta y gestión de pagos PSE y Confiar.
 * B5. Tablas: pagos_pse, pagos_confiar, pagos_conciliacion, pagos_confirmacion.
 */
final class PagosService
{
    private const PSE_ORDER_COLS = ['pse_id', 'valor', 'usado', 'restante', 'estado', 'referencia_2', 'fecha_resolucion'];
    private const CONF_ORDER_COLS = ['confiar_id', 'valor_consignacion', 'usado', 'restante', 'pse_count', 'descripcion', 'cedula', 'confirmacion_estado', 'fecha'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Lista pagos PSE con filtros, ordenamiento y paginación. */
    public function listarPse(string $q = '', string $estado = '', string $conciliado = '', string $fechaDesde = '', string $fechaHasta = '', string $asignacion = '', string $orderBy = 'fecha_resolucion', string $orderDir = 'DESC', int $limit = 20, int $offset = 0, string $confiarId = ''): array
    {
        if (!in_array($orderBy, self::PSE_ORDER_COLS, true)) { $orderBy = 'fecha_resolucion'; }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(p.pse_id LIKE ? OR CAST(p.referencia_2 AS CHAR) LIKE ? OR p.referencia_3 LIKE ?)";
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($estado !== '') {
            $where[] = "p.estado = ?";
            $params[] = $estado;
        }
        if ($conciliado === '1') {
            $where[] = "pc.confiar_id IS NOT NULL";
        } elseif ($conciliado === '0') {
            $where[] = "pc.confiar_id IS NULL";
        }
        if ($confiarId !== '') {
            $where[] = "pc.confiar_id = ?";
            $params[] = $confiarId;
        }
        if ($fechaDesde !== '') { $where[] = "p.fecha_resolucion >= ?"; $params[] = $fechaDesde . ' 00:00:00'; }
        if ($fechaHasta !== '') { $where[] = "p.fecha_resolucion <= ?"; $params[] = $fechaHasta . ' 23:59:59'; }
        if ($asignacion === '0') {
            $where[] = "COALESCE(tx.usado, 0) = 0";
        } elseif ($asignacion === 'parcial') {
            $where[] = "COALESCE(tx.usado, 0) > 0 AND COALESCE(tx.usado, 0) < p.valor";
        } elseif ($asignacion === 'completo') {
            $where[] = "COALESCE(tx.usado, 0) >= p.valor";
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare(
            "SELECT COUNT(*)
             FROM pagos_pse p
             LEFT JOIN pagos_conciliacion pc ON pc.pse_id = p.pse_id
             LEFT JOIN (SELECT pse_id, SUM(valor_total) AS usado FROM transacciones GROUP BY pse_id) tx ON tx.pse_id = p.pse_id
             $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->db->prepare(
            "SELECT p.*, pc.confiar_id AS conciliado_confiar, pc.tipo AS conciliacion_tipo,
                    COALESCE(tx.usado, 0) AS usado,
                    p.valor - COALESCE(tx.usado, 0) AS restante,
                    tx.cedula AS cedula_movimiento,
                    td.movimientos,
                    c.fecha AS confiar_fecha, c.descripcion AS confiar_descripcion,
                    c.documento AS confiar_documento, c.oficina AS confiar_oficina,
                    c.valor_consignacion AS confiar_valor, c.valor_retiro AS confiar_retiro,
                    c.saldo AS confiar_saldo, c.tipo_transaccion AS confiar_tipo
             FROM pagos_pse p
             LEFT JOIN pagos_conciliacion pc ON pc.pse_id = p.pse_id
             LEFT JOIN pagos_confiar c ON c.confiar_id = pc.confiar_id
             LEFT JOIN (SELECT pse_id, SUM(valor_total) AS usado, MAX(cedula) AS cedula FROM transacciones GROUP BY pse_id) tx ON tx.pse_id = p.pse_id
             LEFT JOIN (
                 SELECT t.pse_id,
                        JSON_ARRAYAGG(JSON_OBJECT(
                            'tipo', d.tipo_rubro,
                            'referencia', d.referencia_credito,
                            'producto_id', d.producto_id,
                            'descripcion', d.descripcion,
                            'valor', d.valor_asignado
                        )) AS movimientos
                 FROM transacciones t
                 JOIN transacciones_detalle d ON d.transaccion_id = t.id
                 WHERE d.valor_asignado > 0
                 GROUP BY t.pse_id
             ) td ON td.pse_id = p.pse_id
             $w ORDER BY `$orderBy` $orderDir LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Lista pagos Confiar con filtros, ordenamiento y paginación. */
    public function listarConfiar(string $q = '', string $estado = '', string $fechaDesde = '', string $fechaHasta = '', string $asignacion = '', string $orderBy = 'fecha', string $orderDir = 'DESC', int $limit = 20, int $offset = 0, string $pse = '', array|string $descripcion = [], bool $disponible = false): array
    {
        if (!in_array($orderBy, self::CONF_ORDER_COLS, true)) { $orderBy = 'fecha'; }
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(c.confiar_id LIKE ? OR c.descripcion LIKE ? OR c.documento LIKE ? OR pco.cedula LIKE ?)";
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($estado !== '') {
            if ($estado === 'pendiente') {
                $where[] = "(COALESCE(pco.estado, 'pendiente') = 'pendiente' AND COALESCE(pse.pse_count, 0) = 0)";
            } elseif ($estado === 'asignado') {
                $where[] = "(pco.estado = 'asignado' OR (COALESCE(pse.pse_count, 0) > 0 AND COALESCE(tx.usado, 0) >= c.valor_consignacion))";
            } elseif ($estado === 'no_valido') {
                $where[] = "pco.estado = 'no_valido'";
            } else {
                $where[] = "COALESCE(pco.estado, 'pendiente') = ?";
                $params[] = $estado;
            }
        }
        if ($fechaDesde !== '') { $where[] = "c.fecha >= ?"; $params[] = $fechaDesde; }
        if ($fechaHasta !== '') { $where[] = "c.fecha <= ?"; $params[] = $fechaHasta; }
        if ($asignacion === '0') {
            $where[] = "COALESCE(tx.usado, 0) = 0";
        } elseif ($asignacion === 'parcial') {
            $where[] = "COALESCE(tx.usado, 0) > 0 AND COALESCE(tx.usado, 0) < c.valor_consignacion";
        } elseif ($asignacion === 'completo') {
            $where[] = "COALESCE(tx.usado, 0) >= c.valor_consignacion";
        }
        if ($pse === 'con') {
            $where[] = "COALESCE(pse.pse_count, 0) > 0";
        } elseif ($pse === 'sin') {
            $where[] = "COALESCE(pse.pse_count, 0) = 0";
        }
        $descs = is_array($descripcion) ? $descripcion : [$descripcion];
        $descs = array_values(array_unique(array_filter(
            array_map(static fn($d) => trim((string) $d), $descs),
            static fn($d) => $d !== '' && $d !== 'Todos'
        )));
        if ($descs) {
            $or = [];
            foreach ($descs as $desc) {
                if ($desc === 'Credito ACH') {
                    $or[] = "c.descripcion = 'Credito ACH'";
                } elseif ($desc === 'Consignacion Pago QR') {
                    $or[] = "(c.tipo_transaccion = 'Pago QR' OR c.descripcion LIKE 'Consignacion Pago QR%')";
                } elseif ($desc === 'Consignacion en Cuenta Efectivo') {
                    $or[] = "(c.tipo_transaccion = 'Pago Efectivo' OR c.descripcion LIKE 'Consignacion en Cuenta Efectivo%')";
                } elseif ($desc === 'Consignacion por Transf. Agencia Virtual') {
                    $or[] = "(c.tipo_transaccion = 'Transf. Agencia Virtual' OR c.descripcion LIKE 'Consignacion por Transf. Agencia Virtual%')";
                } elseif ($desc === 'Cheque' || $desc === 'Consignacion en Cuenta Cheque') {
                    $or[] = "(c.tipo_transaccion = 'Cheque' OR c.descripcion LIKE 'Consignacion en Cuenta Cheque%')";
                } else {
                    $or[] = "c.descripcion = ?";
                    $params[] = $desc;
                }
            }
            $where[] = '(' . implode(' OR ', $or) . ')';
        }
        if ($disponible) {
            $where[] = "(c.valor_consignacion - COALESCE(pse.pse_total, 0)) > 10";
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $from = "FROM pagos_confiar c
                 LEFT JOIN pagos_confirmacion pco ON pco.confiar_id = c.confiar_id
                 LEFT JOIN (
                     SELECT confiar_id, SUM(valor_total) AS usado
                     FROM (
                         SELECT t.id, c2.confiar_id, t.valor_total
                         FROM transacciones t
                         JOIN pagos_confiar c2 ON c2.confiar_id = t.confiar_id
                         UNION
                         SELECT t.id, pc2.confiar_id, t.valor_total
                         FROM transacciones t
                         JOIN pagos_conciliacion pc2 ON pc2.pse_id = t.pse_id
                     ) u
                     GROUP BY confiar_id
                 ) tx ON tx.confiar_id = c.confiar_id
                 LEFT JOIN (SELECT pc.confiar_id, COUNT(*) AS pse_count, SUM(p.valor) AS pse_total,
                                   COUNT(DISTINCT p.referencia_2) AS pse_cedulas_count,
                                   MIN(p.referencia_2) AS pse_cedula,
                                   MIN(p.fecha_resolucion) AS pse_fecha,
                                   MIN(pc.fecha_creacion) AS pse_conciliacion_fecha
                            FROM pagos_conciliacion pc
                            JOIN pagos_pse p ON p.pse_id = pc.pse_id
                            GROUP BY pc.confiar_id) pse ON pse.confiar_id = c.confiar_id
                 $w";

        $st = $this->db->prepare("SELECT COUNT(*) $from");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->db->prepare(
            "SELECT c.*, pco.id AS confirmacion_id, pco.cedula, pco.ruta_comprobante,
                    pco.comentario, COALESCE(pco.estado,'pendiente') AS confirmacion_estado, pco.motivo_rechazo,
                    pco.fecha_creacion AS confirmacion_fecha,
                    COALESCE(tx.usado, 0) AS usado,
                    c.valor_consignacion - COALESCE(tx.usado, 0) AS restante,
                    COALESCE(pse.pse_count, 0) AS pse_count,
                    COALESCE(pse.pse_total, 0) AS pse_total,
                    GREATEST(0, c.valor_consignacion - COALESCE(pse.pse_total, 0)) AS pse_restante,
                    COALESCE(pse.pse_cedulas_count, 0) AS pse_cedulas_count,
                    pse.pse_cedula,
                    pse.pse_fecha,
                    pse.pse_conciliacion_fecha
             $from ORDER BY `$orderBy` $orderDir LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Confirma/asigna un pago Confiar a una cédula. */
    public function confirmarConfiar(string $confiarId, string $cedula, ?string $comentario, ?string $rutaComprobante = null, ?array $actor = null): void
    {
        $existe = $this->db->prepare("SELECT confiar_id FROM pagos_confiar WHERE confiar_id = ?");
        $existe->execute([$confiarId]);
        if (!$existe->fetch()) throw new DomainException('Pago Confiar no encontrado.');

        $st = $this->db->prepare(
            "INSERT INTO pagos_confirmacion (confiar_id, cedula, ruta_comprobante, comentario, estado, creado_por)
             VALUES (?,?,?,?,'asignado',?)
             ON DUPLICATE KEY UPDATE cedula=VALUES(cedula), ruta_comprobante=COALESCE(VALUES(ruta_comprobante), ruta_comprobante), comentario=VALUES(comentario), estado='asignado'"
        );
        $st->execute([$confiarId, $cedula, $rutaComprobante, $comentario, $actor['id'] ?? null]);
        Logger::log('crear', 'pagos.confirmar', $actor['id'] ?? null, "Confirmado $confiarId → $cedula");
    }

    /** Marca un pago Confiar como no válido. */
    public function rechazarConfiar(string $confiarId, string $motivo, ?array $actor = null): void
    {
        $this->db->prepare(
            "INSERT INTO pagos_confirmacion (confiar_id, estado, motivo_rechazo, rechazado_por, fecha_rechazo)
             VALUES (?,'no_valido',?,?,NOW())
             ON DUPLICATE KEY UPDATE estado='no_valido', motivo_rechazo=VALUES(motivo_rechazo),
             rechazado_por=VALUES(rechazado_por), fecha_rechazo=NOW()"
        )->execute([$confiarId, $motivo, $actor['id'] ?? null]);
        Logger::log('rechazar', 'pagos.rechazar', $actor['id'] ?? null, "No válido $confiarId", null, null, 'warning');
    }

    /** Elimina un enlace de conciliación PSE↔Confiar. */
    public function desconciliar(string $pseId, string $confiarId, ?array $actor = null): void
    {
        $this->db->prepare("DELETE FROM pagos_conciliacion WHERE pse_id = ? AND confiar_id = ?")
                  ->execute([$pseId, $confiarId]);
        Logger::log('eliminar', 'pagos.conciliacion', $actor['id'] ?? null, "Desconciliado $pseId ↔ $confiarId", null, null, 'warning');
    }

    /** Desasigna un pago Confiar (vuelve a pendiente). */
    public function desasignarConfiar(string $confiarId, ?array $actor = null): void
    {
        $this->db->prepare("UPDATE pagos_confirmacion SET estado = 'pendiente', cedula = NULL, motivo_rechazo = NULL, rechazado_por = NULL, fecha_rechazo = NULL WHERE confiar_id = ?")
                  ->execute([$confiarId]);
        Logger::log('editar', 'pagos.desasignar', $actor['id'] ?? null, "Desasignado $confiarId", null, null, 'warning');
    }

    /** Crea un enlace de conciliación PSE↔Confiar manual. */
    public function conciliar(string $pseId, string $confiarId, string $tipo = 'manual', ?array $actor = null): void
    {
        $ePse = $this->db->prepare("SELECT pse_id, valor FROM pagos_pse WHERE pse_id = ?");
        $ePse->execute([$pseId]);
        $pse = $ePse->fetch();
        if (!$pse) throw new DomainException('PSE ID no encontrado.');

        $eCon = $this->db->prepare("SELECT confiar_id, valor_consignacion FROM pagos_confiar WHERE confiar_id = ?");
        $eCon->execute([$confiarId]);
        $confiar = $eCon->fetch();
        if (!$confiar) throw new DomainException('Confiar ID no encontrado.');

        // Un PSE solo puede estar conciliado con un Confiar.
        $st = $this->db->prepare("SELECT confiar_id FROM pagos_conciliacion WHERE pse_id = ? LIMIT 1");
        $st->execute([$pseId]);
        $linked = $st->fetchColumn();
        if ($linked !== false) {
            if ((string) $linked === $confiarId) {
                return;
            }
            throw new DomainException('El PSE ya está asignado a otro Confiar.');
        }

        // Capacidad PSE del Confiar: valor - PSE ya asignados (sin contar este).
        $st = $this->db->prepare(
            "SELECT COALESCE(SUM(p.valor), 0)
             FROM pagos_conciliacion pc
             JOIN pagos_pse p ON p.pse_id = pc.pse_id
             WHERE pc.confiar_id = ? AND pc.pse_id <> ?"
        );
        $st->execute([$confiarId, $pseId]);
        $pseTotal = (float) $st->fetchColumn();
        $confiarValor = (float) $confiar['valor_consignacion'];
        $pseValor = (float) $pse['valor'];
        $disponible = max(0, $confiarValor - $pseTotal);
        if ($pseValor > $disponible + 0.5) {
            throw new DomainException(
                'El Confiar ' . $confiarId . ' solo tiene $' .
                number_format($disponible, 0, ',', '.') .
                ' disponible para PSE; este PSE es de $' .
                number_format($pseValor, 0, ',', '.') . '.'
            );
        }

        $this->db->prepare("INSERT IGNORE INTO pagos_conciliacion (pse_id, confiar_id, tipo) VALUES (?,?,?)")
                  ->execute([$pseId, $confiarId, $tipo]);
        Logger::log('crear', 'pagos.conciliacion', $actor['id'] ?? null, "Conciliado $pseId ↔ $confiarId ($tipo)");
    }
}
