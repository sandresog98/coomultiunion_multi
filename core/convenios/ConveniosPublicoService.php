<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Público de Convenios (B15/B10) — consultas del catálogo público.
 * listado(): grilla de convenios activos (búsqueda + tipo + orden, paginada).
 * productos(): beneficios de un convenio (búsqueda + rango de precio + orden, paginada).
 */
final class ConveniosPublicoService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Convenios activos con búsqueda/tipo/orden y paginación. */
    public function listado(
        string $q = '',
        string $orden = 'nombre',
        string $dir = 'asc',
        string $tipo = 'todas',
        int $limit = 12,
        int $offset = 0
    ): array {
        $where = ['c.activo = 1'];
        $params = [];
        if ($q !== '') {
            $where[] = "(c.nombre LIKE ? OR COALESCE(c.descripcion,'') LIKE ? OR COALESCE(c.correo,'') LIKE ?)";
            $like = '%' . $q . '%';
            $params = [$like, $like, $like];
        }
        if ($tipo === 'con_productos') {
            $where[] = "EXISTS (SELECT 1 FROM convenios_productos pc WHERE pc.convenio_id = c.id AND pc.activo = 1 AND pc.maneja_stock = 0)";
        } elseif ($tipo === 'enlace') {
            $where[] = "NOT EXISTS (SELECT 1 FROM convenios_productos pc WHERE pc.convenio_id = c.id AND pc.activo = 1 AND pc.maneja_stock = 0)";
        }
        $w = 'WHERE ' . implode(' AND ', $where);

        $st = $this->db->prepare("SELECT COUNT(*) FROM convenios c $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderSql = 'c.nombre ASC';
        if ($orden === 'productos') {
            $orderSql = "(SELECT COUNT(*) FROM convenios_productos pc WHERE pc.convenio_id = c.id AND pc.activo = 1 AND pc.maneja_stock = 0) DESC, c.nombre ASC";
        } elseif ($orden === 'nombre' && strtoupper($dir) === 'DESC') {
            $orderSql = 'c.nombre DESC';
        }

        $sql = "SELECT c.id, c.nombre, c.descripcion, c.telefono, c.correo, c.link, c.anexo_nombre, c.imagen,
                       (SELECT COUNT(*) FROM convenios_productos pc WHERE pc.convenio_id = c.id AND pc.activo = 1 AND pc.maneja_stock = 0) AS total_productos
                FROM convenios c $w ORDER BY $orderSql LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total];
    }

    /** Convenio activo por id, con conteo de productos públicos. */
    public function convenio(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT c.*, (SELECT COUNT(*) FROM convenios_productos pc WHERE pc.convenio_id = c.id AND pc.activo = 1 AND pc.maneja_stock = 0) AS total_productos
             FROM convenios c WHERE c.id = ? AND c.activo = 1"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        return $row ?: null;
    }

    /** Beneficios de un convenio con búsqueda/rango de precio/orden y paginación. */
    public function productos(
        int $convenioId,
        string $q = '',
        float $min = 0,
        float $max = 0,
        string $orden = 'nombre',
        string $dir = 'asc',
        int $limit = 12,
        int $offset = 0
    ): array {
        $where = ['p.convenio_id = ?', 'p.activo = 1', 'p.maneja_stock = 0'];
        $params = [$convenioId];
        if ($q !== '') {
            $where[] = "(p.nombre LIKE ? OR COALESCE(p.descripcion,'') LIKE ?)";
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($min > 0) { $where[] = "COALESCE(p.valor, 0) >= ?"; $params[] = $min; }
        if ($max > 0) { $where[] = "COALESCE(p.valor, 0) <= ?"; $params[] = $max; }
        $w = 'WHERE ' . implode(' AND ', $where);

        $st = $this->db->prepare("SELECT COUNT(*) FROM convenios_productos p $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderSql = 'p.nombre ASC';
        if ($orden === 'precio') {
            $orderSql = 'COALESCE(p.valor, 0) ' . (strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC') . ', p.nombre ASC';
        } elseif ($orden === 'nombre' && strtoupper($dir) === 'DESC') {
            $orderSql = 'p.nombre DESC';
        }

        $sql = "SELECT p.id, p.convenio_id, p.nombre, p.descripcion, p.valor, p.valor_general, p.imagen
                FROM convenios_productos p $w ORDER BY $orderSql LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total];
    }
}