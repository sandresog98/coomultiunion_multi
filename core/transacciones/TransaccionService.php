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

        $sumaAsignada = 0.0;
        foreach ($detalles as $d) {
            $sumaAsignada += (float) ($d['valor_asignado'] ?? 0);
        }
        // Validación estricta solo cuando la transacción viene ligada a un pago
        // (flujo crear.php). El wizard antiguo no envía pse_id/confiar_id.
        $pagoId = trim((string) ($data[$origen . '_id'] ?? ''));
        $validarPago = in_array($origen, ['pse', 'confiar'], true) && $pagoId !== '';
        if ($validarPago && abs($sumaAsignada - $valorTotal) > 0.01) {
            throw new DomainException('El valor total no coincide con la suma de los rubros asignados.');
        }

        if (!$txExterna) $this->db->beginTransaction();
        try {
            if ($validarPago) {
                $this->validarPagoDisponible($origen, $pagoId, $valorTotal);
            }

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

    /**
     * Lista pagos PSE y Confiar con saldo disponible, con la información del
     * asociado ya cruzada (cédula/nombre) para el paso 1 de creación de transacciones.
     */
    public function listarPagosDisponiblesTodos(string $q = '', string $origen = '', int $limit = 20, int $offset = 0): array
    {
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $origen = in_array($origen, ['pse', 'confiar'], true) ? $origen : '';

        $asociados = "SELECT TRIM(LEADING '0' FROM cedula) AS cedula_norm,
                             MAX(nombre) AS nombre, MIN(cedula) AS cedula
                      FROM sifone_asociados
                      WHERE cedula IS NOT NULL AND cedula <> ''
                      GROUP BY cedula_norm";

        $usadoConfiar = "SELECT confiar_id, SUM(valor_total) AS usado FROM (
                             SELECT t.id, t.confiar_id, t.valor_total
                             FROM transacciones t
                             WHERE t.confiar_id IS NOT NULL AND t.confiar_id <> ''
                             UNION
                             SELECT t.id, pc2.confiar_id, t.valor_total
                             FROM transacciones t
                             JOIN pagos_conciliacion pc2 ON pc2.pse_id = t.pse_id
                             WHERE t.pse_id IS NOT NULL AND t.pse_id <> ''
                         ) u GROUP BY confiar_id";

        $pse = "SELECT
                    'pse' AS origen,
                    p.pse_id AS id,
                    CAST(p.valor AS DECIMAL(15,2)) AS valor,
                    CAST(COALESCE(txp.usado, 0) AS DECIMAL(15,2)) AS usado,
                    CAST(p.valor - COALESCE(txp.usado, 0) AS DECIMAL(15,2)) AS restante,
                    CAST(p.referencia_2 AS CHAR) AS cedula_pago,
                    p.referencia_3 AS nombre_pago,
                    sap.nombre AS asociado_nombre,
                    sap.cedula AS asociado_cedula,
                    CONCAT_WS(' · ', NULLIF(p.banco_originador, ''), NULLIF(p.referencia_1, '')) AS descripcion,
                    p.fecha_resolucion AS fecha,
                    p.estado AS estado,
                    0 AS pse_count,
                    0 AS pse_total,
                    0 AS pse_cedulas_count,
                    COALESCE(pcf.confiar_count, 0) AS confiar_count,
                    pcf.confiar_ids AS confiar_ids
                FROM pagos_pse p
                LEFT JOIN (
                    SELECT pse_id, SUM(valor_total) AS usado
                    FROM transacciones
                    WHERE pse_id IS NOT NULL AND pse_id <> ''
                    GROUP BY pse_id
                ) txp ON txp.pse_id = p.pse_id
                LEFT JOIN (
                    SELECT pse_id,
                           COUNT(*) AS confiar_count,
                           GROUP_CONCAT(confiar_id ORDER BY confiar_id SEPARATOR ', ') AS confiar_ids
                    FROM pagos_conciliacion
                    GROUP BY pse_id
                ) pcf ON pcf.pse_id = p.pse_id
                LEFT JOIN ($asociados) sap
                       ON sap.cedula_norm = TRIM(LEADING '0' FROM CAST(p.referencia_2 AS CHAR))
                WHERE p.estado = 'Aprobada'
                  AND EXISTS (SELECT 1 FROM pagos_conciliacion pc WHERE pc.pse_id = p.pse_id)
                  AND p.valor - COALESCE(txp.usado, 0) > 0";

        $confiar = "SELECT
                        'confiar' AS origen,
                        c.confiar_id AS id,
                        CAST(COALESCE(c.valor_consignacion, 0) AS DECIMAL(15,2)) AS valor,
                        CAST(COALESCE(txc.usado, 0) AS DECIMAL(15,2)) AS usado,
                        CAST(COALESCE(c.valor_consignacion, 0) - COALESCE(txc.usado, 0) AS DECIMAL(15,2)) AS restante,
                        CASE
                            WHEN pco.cedula IS NOT NULL AND pco.cedula <> '' THEN CAST(pco.cedula AS CHAR)
                            WHEN COALESCE(psx.pse_cedulas_count, 0) = 1 THEN CAST(psx.pse_cedula AS CHAR)
                            ELSE NULL
                        END AS cedula_pago,
                        COALESCE(sac.nombre, sapx.nombre, pco.cedula) AS nombre_pago,
                        COALESCE(sac.nombre, sapx.nombre) AS asociado_nombre,
                        CASE
                            WHEN pco.cedula IS NOT NULL AND pco.cedula <> '' THEN sac.cedula
                            WHEN COALESCE(psx.pse_cedulas_count, 0) = 1 THEN sapx.cedula
                            ELSE NULL
                        END AS asociado_cedula,
                        c.descripcion,
                        c.fecha,
                        COALESCE(pco.estado, 'pendiente') AS estado,
                        COALESCE(psx.pse_count, 0) AS pse_count,
                        CAST(COALESCE(psx.pse_total, 0) AS DECIMAL(15,2)) AS pse_total,
                        COALESCE(psx.pse_cedulas_count, 0) AS pse_cedulas_count,
                        0 AS confiar_count,
                        NULL AS confiar_ids
                    FROM pagos_confiar c
                    LEFT JOIN pagos_confirmacion pco ON pco.confiar_id = c.confiar_id
                    LEFT JOIN ($usadoConfiar) txc ON txc.confiar_id = c.confiar_id
                    LEFT JOIN (
                        SELECT pc.confiar_id, COUNT(*) AS pse_count, SUM(p.valor) AS pse_total,
                               COUNT(DISTINCT p.referencia_2) AS pse_cedulas_count,
                               MIN(p.referencia_2) AS pse_cedula
                        FROM pagos_conciliacion pc
                        JOIN pagos_pse p ON p.pse_id = pc.pse_id
                        GROUP BY pc.confiar_id
                    ) psx ON psx.confiar_id = c.confiar_id
                    LEFT JOIN ($asociados) sac
                           ON sac.cedula_norm = TRIM(LEADING '0' FROM CAST(pco.cedula AS CHAR))
                    LEFT JOIN ($asociados) sapx
                           ON sapx.cedula_norm = TRIM(LEADING '0' FROM CAST(psx.pse_cedula AS CHAR))
                    WHERE pco.estado = 'asignado'
                      AND pco.cedula IS NOT NULL AND pco.cedula <> ''
                      AND c.descripcion NOT LIKE '%Intereses de Ahorros%'
                      AND c.descripcion NOT LIKE 'Reverso%'
                      AND c.descripcion NOT LIKE '%Gravamen%'
                      AND c.descripcion NOT LIKE 'Movimiento Saldo%'
                      AND c.descripcion NOT LIKE 'Devoluci%Credito ACH%'
                      AND COALESCE(c.valor_consignacion, 0) - COALESCE(txc.usado, 0) > 0";

        $partes = [];
        if ($origen !== 'confiar') { $partes[] = $pse; }
        if ($origen !== 'pse') { $partes[] = $confiar; }
        $union = implode(' UNION ALL ', $partes);

        $where = '';
        $params = [];
        if ($q !== '') {
            $where = "WHERE (u.id LIKE ? OR u.cedula_pago LIKE ? OR u.asociado_cedula LIKE ?
                             OR u.nombre_pago LIKE ? OR u.asociado_nombre LIKE ? OR u.descripcion LIKE ?)";
            $like = '%' . $q . '%';
            $params = [$like, $like, $like, $like, $like, $like];
        }

        $st = $this->db->prepare("SELECT COUNT(*) FROM ($union) u $where");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $st = $this->db->prepare(
            "SELECT * FROM ($union) u $where ORDER BY u.fecha DESC, u.id DESC LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
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

    /**
     * Valida que el pago exista, esté vigente y tenga saldo suficiente.
     * Debe llamarse dentro de una transacción para bloquear el pago.
     */
    private function validarPagoDisponible(string $origen, string $pagoId, float $valor): void
    {
        if ($pagoId === '') {
            throw new DomainException('Falta el pago seleccionado.');
        }

        if ($origen === 'pse') {
            $lock = $this->db->prepare("SELECT pse_id FROM pagos_pse WHERE pse_id = ? FOR UPDATE");
            $lock->execute([$pagoId]);
            if (!$lock->fetch()) {
                throw new DomainException('El pago PSE no existe.');
            }

            $st = $this->db->prepare(
                "SELECT p.estado,
                        p.valor - COALESCE(tx.usado, 0) AS restante,
                        EXISTS (SELECT 1 FROM pagos_conciliacion pc WHERE pc.pse_id = p.pse_id) AS con_cruce
                 FROM pagos_pse p
                 LEFT JOIN (
                     SELECT pse_id, SUM(valor_total) AS usado
                     FROM transacciones
                     WHERE pse_id IS NOT NULL AND pse_id <> ''
                     GROUP BY pse_id
                 ) tx ON tx.pse_id = p.pse_id
                 WHERE p.pse_id = ?"
            );
            $st->execute([$pagoId]);
            $pago = $st->fetch();
            if (!$pago) {
                throw new DomainException('El pago PSE no existe.');
            }
            if ($pago['estado'] !== 'Aprobada') {
                throw new DomainException('El pago PSE no está aprobado.');
            }
            if (!(int) $pago['con_cruce']) {
                throw new DomainException('El pago PSE no está validado con Confiar.');
            }
            if ((float) $pago['restante'] + 0.01 < $valor) {
                throw new DomainException('El pago PSE no tiene saldo suficiente.');
            }
            return;
        }

        $lock = $this->db->prepare("SELECT confiar_id FROM pagos_confiar WHERE confiar_id = ? FOR UPDATE");
        $lock->execute([$pagoId]);
        if (!$lock->fetch()) {
            throw new DomainException('El pago Confiar no existe.');
        }

        $st = $this->db->prepare(
            "SELECT COALESCE(pco.estado, 'pendiente') AS estado,
                    pco.cedula AS cedula_asignada,
                    COALESCE(c.valor_consignacion, 0) - COALESCE(tx.usado, 0) AS restante
             FROM pagos_confiar c
             LEFT JOIN pagos_confirmacion pco ON pco.confiar_id = c.confiar_id
             LEFT JOIN (
                 SELECT confiar_id, SUM(valor_total) AS usado FROM (
                     SELECT t.id, t.confiar_id, t.valor_total
                     FROM transacciones t
                     WHERE t.confiar_id IS NOT NULL AND t.confiar_id <> ''
                     UNION
                     SELECT t.id, pc2.confiar_id, t.valor_total
                     FROM transacciones t
                     JOIN pagos_conciliacion pc2 ON pc2.pse_id = t.pse_id
                     WHERE t.pse_id IS NOT NULL AND t.pse_id <> ''
                 ) u GROUP BY confiar_id
             ) tx ON tx.confiar_id = c.confiar_id
             WHERE c.confiar_id = ?"
        );
        $st->execute([$pagoId]);
        $pago = $st->fetch();
        if (!$pago) {
            throw new DomainException('El pago Confiar no existe.');
        }
        if ($pago['estado'] === 'no_valido') {
            throw new DomainException('El pago Confiar no es válido.');
        }
        if ($pago['estado'] !== 'asignado' || empty($pago['cedula_asignada'])) {
            throw new DomainException('El pago Confiar debe estar asignado a un asociado.');
        }
        if ((float) $pago['restante'] + 0.01 < $valor) {
            throw new DomainException('El pago Confiar no tiene saldo suficiente.');
        }
    }
}
