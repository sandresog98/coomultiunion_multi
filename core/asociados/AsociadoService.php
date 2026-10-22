<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Consulta de asociados (solo lectura sobre vistas v2).
 * B3. Usa vw_asociados + vw_productos + vw_creditos.
 */
final class AsociadoService
{
    /** Columnas permitidas para ordenamiento (whitelist, nunca del cliente). */
    private const ORDER_COLS = [
        'cedula', 'nombre', 'celular', 'estado_activo',
        'total_saldos_favor', 'creditos', 'producto_mensual',
    ];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Listado paginado con búsqueda, filtro de estado y ordenamiento. */
    public function listar(string $q, string $estado, string $creditos, string $productos, int $limit = 20, int $offset = 0, string $orderBy = 'nombre', string $orderDir = 'ASC'): array
    {
        if (!in_array($orderBy, self::ORDER_COLS, true)) {
            $orderBy = 'nombre';
        }
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $like = '%' . $q . '%';
        $where = "WHERE (? = '' OR a.cedula LIKE ? OR a.nombre LIKE ?)";
        $params = [$q, $like, $like];

        if ($estado !== '') {
            $where .= " AND ac.estado_activo = ?";
            $params[] = (int) $estado;
        }
        if ($creditos === '1') {
            $where .= " AND p.total_creditos > 0";
        } elseif ($creditos === '0') {
            $where .= " AND (p.total_creditos = 0 OR p.total_creditos IS NULL)";
        }
        if ($productos === '1') {
            $where .= " AND p.producto_mensual_total > 0";
        } elseif ($productos === '0') {
            $where .= " AND (p.producto_mensual_total = 0 OR p.producto_mensual_total IS NULL)";
        }

        $from = "FROM vw_asociados a
                 LEFT JOIN vw_productos p ON p.cedula = a.cedula
                 LEFT JOIN asociados_control ac ON ac.cedula = a.cedula $where";

        // Total
        $stTotal = $this->db->prepare("SELECT COUNT(*) $from");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        // Datos paginados
        $cols = "a.cedula, a.nombre, a.celular,
                 COALESCE(p.total_saldos_favor, 0)        AS total_saldos_favor,
                 COALESCE(p.total_creditos, 0)            AS creditos,
                 COALESCE(p.producto_mensual_total, 0)    AS producto_mensual,
                 COALESCE(p.fondo_bienestar_tiene_producto, 0) AS fondo_bienestar_tiene_producto,
                 COALESCE(p.fondo_bienestar_al_dia, 0)    AS fondo_bienestar_al_dia,
                 COALESCE(p.total_capital_pendiente, 0)   AS total_capital_pendiente,
                 COALESCE(p.total_mora, 0)                AS total_mora,
                 COALESCE(p.pago_minimo_creditos, 0)      AS pago_minimo_creditos,
                 COALESCE(p.aportes_totales, 0)           AS aportes_totales,
                 COALESCE(p.aportes_incentivos, 0)        AS aportes_incentivos,
                 COALESCE(p.aportes_revalorizaciones, 0)  AS aportes_revalorizaciones,
                 COALESCE(p.plan_futuro, 0)               AS plan_futuro,
                 COALESCE(p.bolsillos, 0)                 AS bolsillos,
                 COALESCE(p.comisiones, 0)                AS comisiones,
                 COALESCE(p.total_incentivos, 0)          AS total_incentivos,
                 COALESCE(p.aportes_ultimo_periodo, NULL) AS aportes_ultimo_periodo,
                 COALESCE(p.bolsillos_ultima_fecha, NULL) AS bolsillos_ultima_fecha,
                 COALESCE(p.producto_dia_pago, NULL)      AS producto_dia_pago,
                 CASE WHEN ac.estado_activo IS NULL THEN 1 ELSE ac.estado_activo END AS estado_activo";

        $sql = "SELECT $cols $from ORDER BY `$orderBy` $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Ficha completa: datos personales + monetario + créditos. */
    public function ficha(string $cedula): array
    {
        $persona = $this->uno("SELECT * FROM vw_asociados WHERE cedula = ?", [$cedula]);
        if (!$persona) {
            throw new DomainException('Asociado no encontrado.');
        }

        $productos = $this->uno("SELECT * FROM vw_productos WHERE cedula = ?", [$cedula]);

        $st = $this->db->prepare(
            "SELECT * FROM vw_creditos WHERE cedula = ? ORDER BY numero_credito"
        );
        $st->execute([$cedula]);
        $creditos = $st->fetchAll();

        $estado = $this->uno(
            "SELECT estado_activo, opt_in, password_hash IS NOT NULL AS tiene_portal
             FROM asociados_control WHERE cedula = ?", [$cedula]
        );

        return [
            'persona'   => $persona,
            'productos' => $productos ?: [],
            'creditos'  => $creditos,
            'estado'    => $estado ?: ['estado_activo' => 1, 'opt_in' => 0, 'tiene_portal' => 0],
        ];
    }

    /** Activa/inactiva el consentimiento maestro de comunicaciones. */
    public function setOptIn(string $cedula, bool $optIn, ?array $actor = null): void
    {
        require_once dirname(__DIR__) . '/comunicaciones/ContactoService.php';
        (new ContactoService($this->db))->setOptInAsociado($cedula, $optIn, $actor);
    }

    /** Activa/inactiva un asociado en asociados_control. */
    public function toggleEstado(string $cedula, bool $activo, ?array $actor = null): void
    {
        $existe = $this->db->prepare("SELECT cedula FROM asociados_control WHERE cedula = ?");
        $existe->execute([$cedula]);
        if ($existe->fetch()) {
            $this->db->prepare("UPDATE asociados_control SET estado_activo = ? WHERE cedula = ?")
                      ->execute([$activo ? 1 : 0, $cedula]);
        } else {
            $this->db->prepare("INSERT INTO asociados_control (cedula, estado_activo) VALUES (?, ?)")
                      ->execute([$cedula, $activo ? 1 : 0]);
        }
        // Si inactiva al asociado, inactivar también sus productos asignados
        if (!$activo) {
            $this->db->prepare("UPDATE asociados_productos SET estado_activo = 0 WHERE cedula = ?")
                      ->execute([$cedula]);
        }
        Logger::log($activo ? 'activar' : 'inactivar', 'asociados', $actor['id'] ?? null,
            "Toggle estado $cedula: " . ($activo ? 'activo' : 'inactivo'), null, null, 'warning');
    }

    private function uno(string $sql, array $p): ?array
    {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetch() ?: null;
    }
}
