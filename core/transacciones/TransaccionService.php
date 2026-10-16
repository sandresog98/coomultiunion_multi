<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Gestión de transacciones: creación con auto-balanceo de rubros.
 * B5. Tablas: transacciones, transacciones_detalle.
 */
final class TransaccionService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Lista transacciones de un asociado con paginación. */
    public function listar(string $cedula, int $limit = 20, int $offset = 0): array
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM transacciones WHERE cedula = ?");
        $st->execute([$cedula]);
        $total = (int) $st->fetchColumn();

        $st = $this->db->prepare(
            "SELECT * FROM transacciones WHERE cedula = ? ORDER BY fecha_creacion DESC LIMIT ? OFFSET ?"
        );
        $st->bindValue(1, $cedula);
        $st->bindValue(2, $limit, PDO::PARAM_INT);
        $st->bindValue(3, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        // Adjuntar detalles a cada transacción
        foreach ($rows as &$tx) {
            $stD = $this->db->prepare(
                "SELECT d.*, p.nombre AS producto_nombre
                 FROM transacciones_detalle d
                 LEFT JOIN productos p ON p.id = d.producto_id
                 WHERE d.transaccion_id = ? ORDER BY d.id"
            );
            $stD->execute([$tx['id']]);
            $tx['detalles'] = $stD->fetchAll();
        }

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Obtiene los rubros recomendados para un asociado (créditos + productos). */
    public function rubrosRecomendados(string $cedula): array
    {
        $creditos = $this->db->prepare(
            "SELECT numero, tipopr, carter AS capital, valorc AS cuota FROM sifone_cartera_aseguradora WHERE cedula = ? ORDER BY numero"
        );
        $creditos->execute([$cedula]);

        $mora = $this->db->prepare(
            "SELECT presta, sdomor, diav FROM sifone_cartera_mora WHERE cedula = ?"
        );
        $mora->execute([$cedula]);

        $productos = $this->db->prepare(
            "SELECT ap.id, p.nombre, ap.monto FROM asociados_productos ap
             INNER JOIN productos p ON p.id = ap.producto_id
             WHERE ap.cedula = ? AND p.tipo = 'producto' AND ap.estado_activo = 1 AND p.estado_activo = 1"
        );
        $productos->execute([$cedula]);

        return [
            'creditos' => $creditos->fetchAll(),
            'mora' => $mora->fetchAll(),
            'productos' => $productos->fetchAll(),
        ];
    }

    /**
     * Crea una transacción con balanceo automático de rubros.
     * @param bool $txExterna true si el llamador ya abrió una transacción PDO
     *                        (el insert se hace dentro de ella; sin commit propio).
     */
    public function crear(array $data, ?array $actor = null, bool $txExterna = false): int
    {
        $cedula = trim((string) ($data['cedula'] ?? ''));
        if ($cedula === '') throw new DomainException('Falta cédula del asociado.');
        $origen = trim((string) ($data['origen'] ?? ''));
        if (!in_array($origen, ['pse', 'confiar', 'boleta'], true)) throw new DomainException('Origen inválido.');
        $valorTotal = (float) ($data['valor_total'] ?? 0);
        if ($valorTotal <= 0) throw new DomainException('El valor total debe ser mayor a cero.');

        $detalles = $data['detalles'] ?? [];
        if (empty($detalles)) throw new DomainException('Debe asignar al menos un rubro.');

        if (!$txExterna) $this->db->beginTransaction();
        try {
            $st = $this->db->prepare(
                "INSERT INTO transacciones (cedula, origen, pse_id, confiar_id, recibo_caja, valor_total, usuario_id)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $st->execute([
                $cedula, $origen,
                trim((string) ($data['pse_id'] ?? '')),
                trim((string) ($data['confiar_id'] ?? '')),
                trim((string) ($data['recibo_caja'] ?? '')),
                $valorTotal,
                $actor['id'] ?? null,
            ]);
            $trxId = (int) $this->db->lastInsertId();

            $stD = $this->db->prepare(
                "INSERT INTO transacciones_detalle (transaccion_id, tipo_rubro, referencia_credito, producto_id, descripcion, valor_recomendado, valor_asignado)
                 VALUES (?,?,?,?,?,?,?)"
            );
            foreach ($detalles as $d) {
                $stD->execute([
                    $trxId,
                    trim((string) ($d['tipo_rubro'] ?? '')),
                    trim((string) ($d['referencia_credito'] ?? '')) ?: null,
                    ($d['producto_id'] ?? 0) ? (int) $d['producto_id'] : null,
                    trim((string) ($d['descripcion'] ?? '')),
                    (float) ($d['valor_recomendado'] ?? 0),
                    (float) ($d['valor_asignado'] ?? 0),
                ]);
            }

            if (!$txExterna) $this->db->commit();
            Logger::log('crear', 'transacciones', $actor['id'] ?? null, "Creada transacción #$trxId para $cedula ($origen, $valorTotal)");
            return $trxId;
        } catch (Throwable $e) {
            if (!$txExterna) $this->db->rollBack();
            throw $e;
        }
    }

    /** Lista pagos disponibles (con restante > 0) para el selector de transacción. */
    public function listarPagosDisponibles(string $tipo): array
    {
        if ($tipo === 'pse') {
            return $this->db->query(
                "SELECT p.pse_id AS id, p.pse_id AS label, p.valor,
                        COALESCE(tx.usado, 0) AS usado,
                        p.valor - COALESCE(tx.usado, 0) AS restante,
                        p.referencia_2 AS cedula, p.referencia_3 AS nombre
                 FROM pagos_pse p
                 LEFT JOIN (SELECT pse_id, SUM(valor_total) AS usado FROM transacciones GROUP BY pse_id) tx ON tx.pse_id = p.pse_id
                 WHERE p.valor - COALESCE(tx.usado, 0) > 0 AND p.estado = 'Aprobada'
                 ORDER BY p.fecha_resolucion DESC LIMIT 200"
            )->fetchAll();
        }
        return $this->db->query(
            "SELECT c.confiar_id AS id, c.confiar_id AS label, c.valor_consignacion AS valor,
                    COALESCE(tx.usado, 0) AS usado,
                    c.valor_consignacion - COALESCE(tx.usado, 0) AS restante
             FROM pagos_confiar c
             LEFT JOIN (SELECT confiar_id, SUM(valor_total) AS usado FROM transacciones GROUP BY confiar_id) tx ON tx.confiar_id = c.confiar_id
             WHERE c.valor_consignacion - COALESCE(tx.usado, 0) > 0
               AND NOT EXISTS (SELECT 1 FROM pagos_confirmacion pc WHERE pc.confiar_id = c.confiar_id AND pc.estado = 'no_valido')
             ORDER BY c.fecha DESC LIMIT 200"
        )->fetchAll();
    }

    /** Lista todas las transacciones con filtros y paginación. */
    public function listarTodas(string $q = '', string $origen = '', string $fechaDesde = '', string $fechaHasta = '', int $limit = 20, int $offset = 0): array
    {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(t.cedula LIKE ? OR t.pse_id LIKE ? OR t.confiar_id LIKE ? OR t.recibo_caja LIKE ? OR sa.nombre LIKE ?)";
            $like = '%' . $q . '%';
            $params = [$like, $like, $like, $like, $like];
        }
        if ($origen !== '') {
            $where[] = "t.origen = ?";
            $params[] = $origen;
        }
        if ($fechaDesde !== '') { $where[] = "DATE(t.fecha_creacion) >= ?"; $params[] = $fechaDesde; }
        if ($fechaHasta !== '') { $where[] = "DATE(t.fecha_creacion) <= ?"; $params[] = $fechaHasta; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM transacciones t LEFT JOIN sifone_asociados sa ON sa.cedula = t.cedula $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->db->prepare(
            "SELECT t.*, sa.nombre AS nombre_asociado, u.nombre_completo AS nombre_usuario
             FROM transacciones t
             LEFT JOIN sifone_asociados sa ON sa.cedula = t.cedula
             LEFT JOIN usuarios u ON u.id = t.usuario_id
             $w ORDER BY t.id DESC LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        // Adjuntar detalles
        foreach ($rows as &$tx) {
            $stD = $this->db->prepare(
                "SELECT d.*, p.nombre AS producto_nombre
                 FROM transacciones_detalle d
                 LEFT JOIN productos p ON p.id = d.producto_id
                 WHERE d.transaccion_id = ? ORDER BY d.id"
            );
            $stD->execute([$tx['id']]);
            $tx['detalles'] = $stD->fetchAll();
        }
        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Elimina una transacción y sus detalles. */
    public function eliminar(int $id, ?array $actor = null): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM transacciones_detalle WHERE transaccion_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM transacciones WHERE id = ?")->execute([$id]);
            $this->db->commit();
            Logger::log('eliminar', 'transacciones', $actor['id'] ?? null, "Eliminada transacción #$id", null, null, 'warning');
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
