<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Consulta de logs con filtros, paginación y estadísticas.
 */
final class LogService
{
    /** Columnas permitidas para ordenamiento (whitelist, nunca del cliente). */
    private const ORDER_COLS = ['id', 'id_usuario', 'accion', 'modulo', 'nivel', 'timestamp'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Listado paginado con filtros y ordenamiento. */
    public function listar(array $filtros): array
    {
        $orderBy = trim((string) ($filtros['order'] ?? 'id'));
        $orderDir = strtoupper(trim((string) ($filtros['dir'] ?? 'DESC'))) === 'ASC' ? 'ASC' : 'DESC';
        if (!in_array($orderBy, self::ORDER_COLS, true)) { $orderBy = 'id'; }
        $where = [];
        $params = [];

        if (!empty($filtros['usuario'])) {
            $where[] = "l.id_usuario = ?";
            $params[] = $filtros['usuario'];
        }
        if (!empty($filtros['modulo'])) {
            $where[] = "l.modulo = ?";
            $params[] = $filtros['modulo'];
        }
        if (!empty($filtros['accion'])) {
            $where[] = "l.accion = ?";
            $params[] = $filtros['accion'];
        }
        if (!empty($filtros['nivel'])) {
            $where[] = "l.nivel = ?";
            $params[] = $filtros['nivel'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[] = "DATE(l.timestamp) >= ?";
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = "DATE(l.timestamp) <= ?";
            $params[] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['q'])) {
            $where[] = "l.detalle LIKE ?";
            $params[] = '%' . $filtros['q'] . '%';
        }

        $whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $limit = min(200, max(1, (int) ($filtros['limit'] ?? 20)));
        $offset = max(0, (int) ($filtros['offset'] ?? 0));

        $from = "FROM logs l LEFT JOIN usuarios u ON u.id = l.id_usuario $whereSQL";
        $stTotal = $this->db->prepare("SELECT COUNT(*) $from");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $st = $this->db->prepare(
            "SELECT l.id, l.id_usuario, l.timestamp, l.accion, l.modulo, l.detalle,
                    l.ip_address, l.nivel, u.nombre_completo AS nombre_usuario
             $from ORDER BY l.`$orderBy` $orderDir LIMIT ? OFFSET ?"
        );
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Detalle completo de un log (con datos anteriores/nuevos). */
    public function detalle(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT l.*, u.nombre_completo AS nombre_usuario, u.usuario AS login_usuario
             FROM logs l LEFT JOIN usuarios u ON u.id = l.id_usuario
             WHERE l.id = ?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) return null;

        // Decodificar JSONs
        foreach (['datos_anteriores', 'datos_nuevos'] as $k) {
            if (!empty($row[$k]) && is_string($row[$k])) {
                $decoded = json_decode($row[$k], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row[$k . '_parsed'] = $decoded;
                }
            }
        }
        return $row;
    }

    /** Estadísticas para los cards del dashboard. */
    public function estadisticas(?string $fechaDesde = null, ?string $fechaHasta = null): array
    {
        $where = [];
        $params = [];
        if ($fechaDesde) { $where[] = "DATE(timestamp) >= ?"; $params[] = $fechaDesde; }
        if ($fechaHasta) { $where[] = "DATE(timestamp) <= ?"; $params[] = $fechaHasta; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT
            COUNT(*) AS total_logs,
            SUM(CASE WHEN accion = 'crear' THEN 1 ELSE 0 END) AS creaciones,
            SUM(CASE WHEN accion = 'editar' THEN 1 ELSE 0 END) AS ediciones,
            SUM(CASE WHEN accion = 'eliminar' THEN 1 ELSE 0 END) AS eliminaciones,
            COUNT(DISTINCT id_usuario) AS usuarios_activos
            FROM logs $w");
        $st->execute($params);
        return $st->fetch() ?: [];
    }

    /** Valores distintos para poblar los selects de filtros. */
    public function filtrosOpciones(): array
    {
        return [
            'modulos'  => $this->db->query("SELECT DISTINCT modulo FROM logs WHERE modulo IS NOT NULL ORDER BY modulo")->fetchAll(PDO::FETCH_COLUMN),
            'acciones' => $this->db->query("SELECT DISTINCT accion FROM logs WHERE accion IS NOT NULL ORDER BY accion")->fetchAll(PDO::FETCH_COLUMN),
            'niveles'  => $this->db->query("SELECT DISTINCT nivel FROM logs WHERE nivel IS NOT NULL ORDER BY nivel")->fetchAll(PDO::FETCH_COLUMN),
            'usuarios' => $this->db->query("SELECT id, usuario, nombre_completo FROM usuarios ORDER BY nombre_completo")->fetchAll(),
        ];
    }
}
