<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Catálogo PÚBLICO de Tienda (B15). Sin auth: solo lectura de productos
 * activos (categoría/marca activas), informativo (sin stock / sin "agotado").
 *
 * Precios:
 *  - precio_venta: precio de la última compra con stock disponible, o el del
 *    catálogo si no hay (COALESCE) — regla del FLUJO_PRECIOS_TIENDA.
 *  - precio_multiunion: precio del catálogo con el % del rango aplicado.
 *  - promoción vigente → en_promocion + precio_descuento.
 */
final class TiendaPublicoService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Lista los productos activos del catálogo (informativo).
     * Filtros: categoria_id, marca_id, nombre (LIKE), precio_min/max (sobre
     * precio_multiunion). Orden por columnas SQL; precio usa el multiunión
     * calculado en SQL (regla del legacy) para poder ordenar/filtrar + paginar.
     */
    public function catalogo(
        int $categoriaId = 0,
        int $marcaId = 0,
        string $q = '',
        float $precioMin = 0,
        float $precioMax = 0,
        string $orden = 'destacado',
        string $dir = 'asc',
        int $limit = 50,
        int $offset = 0
    ): array {
        // precio_venta = última compra con stock disponible COALESCE catálogo
        $precioSql = "COALESCE(pr.precio_venta_sugerido, p.precio_venta_sugerido)";
        // precio Multiunión = ROUND(precio - (precio * %/100), -3) con el rango vigente
        $multiunionSql = "ROUND($precioSql - ($precioSql * COALESCE((SELECT rp.porcentaje FROM tienda_rangos_precio rp
                          WHERE $precioSql BETWEEN rp.desde AND rp.hasta LIMIT 1), 0) / 100), -3)";

        $where = ["p.estado_activo = 1", "c.estado_activo = 1", "m.estado_activo = 1"];
        $params = [];
        if ($categoriaId > 0) { $where[] = "p.categoria_id = ?"; $params[] = $categoriaId; }
        if ($marcaId > 0) { $where[] = "p.marca_id = ?"; $params[] = $marcaId; }
        if ($q !== '') { $where[] = "p.nombre LIKE ?"; $params[] = '%' . $q . '%'; }
        if ($precioMin > 0) { $where[] = "$multiunionSql >= ?"; $params[] = $precioMin; }
        if ($precioMax > 0) { $where[] = "$multiunionSql <= ?"; $params[] = $precioMax; }
        $w = 'WHERE ' . implode(' AND ', $where);

        $precioJoin = "LEFT JOIN (
            SELECT cd.producto_id, cd.precio_venta_sugerido,
                   ROW_NUMBER() OVER (PARTITION BY cd.producto_id ORDER BY cd.id DESC) AS rn
            FROM tienda_compra_detalles cd
            WHERE cd.precio_venta_sugerido > 0
              AND EXISTS (SELECT 1 FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'disponible')
        ) pr ON pr.producto_id = p.id AND pr.rn = 1";

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_productos p
                                  JOIN tienda_categorias c ON c.id = p.categoria_id
                                  JOIN tienda_marcas m ON m.id = p.marca_id
                                  $precioJoin $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderDir = strtoupper($dir) === 'DESC' ? 'DESC' : 'ASC';
        $orderSql = "en_promocion DESC, p.nombre ASC";
        switch ($orden) {
            case 'precio': $orderSql = "precio_multiunion $orderDir"; break;
            case 'nombre': $orderSql = "p.nombre $orderDir"; break;
            case 'categoria': $orderSql = "c.nombre $orderDir, p.nombre ASC"; break;
            case 'marca': $orderSql = "m.nombre $orderDir, p.nombre ASC"; break;
        }

        $sql = "SELECT p.id, p.nombre, p.descripcion, p.foto_path, p.agotado, c.nombre AS categoria, m.nombre AS marca,
                       $precioSql AS precio_venta,
                       $multiunionSql AS precio_multiunion,
                       promo.precio_descuento AS precio_descuento,
                       CASE WHEN promo.producto_id IS NULL THEN 0 ELSE 1 END AS en_promocion
                FROM tienda_productos p
                JOIN tienda_categorias c ON c.id = p.categoria_id
                JOIN tienda_marcas m ON m.id = p.marca_id
                $precioJoin
                LEFT JOIN (
                    SELECT producto_id, MAX(precio_descuento) AS precio_descuento
                    FROM tienda_promociones
                    WHERE estado_activo = 1
                      AND fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE()
                      AND precio_descuento > 0
                    GROUP BY producto_id
                ) promo ON promo.producto_id = p.id
                $w ORDER BY $orderSql LIMIT ? OFFSET ?";

        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        $out = [];
        foreach ($rows as $r) {
            $precioDescuento = $r['precio_descuento'] !== null ? (float) $r['precio_descuento'] : null;
            $enPromo = ((int) $r['en_promocion'] > 0 && $precioDescuento > 0) ? 1 : 0;
            $out[] = [
                'id' => (int) $r['id'],
                'nombre' => $r['nombre'],
                'descripcion' => $r['descripcion'],
                'foto_path' => $r['foto_path'],
                'agotado' => (int) $r['agotado'],
                'categoria' => $r['categoria'],
                'marca' => $r['marca'],
                'precio_venta' => (float) $r['precio_venta'],
                'precio_multiunion' => $r['precio_multiunion'] !== null ? (float) $r['precio_multiunion'] : null,
                'en_promocion' => $enPromo,
                'precio_descuento' => $enPromo ? $precioDescuento : null,
                'promocion_porcentaje' => $enPromo && (float) $r['precio_venta'] > 0
                    ? (int) round((1 - $precioDescuento / (float) $r['precio_venta']) * 100) : 0,
            ];
        }

        return ['rows' => $out, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function producto(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.id, p.nombre, p.descripcion, p.foto_path, p.agotado, c.nombre AS categoria, m.nombre AS marca,
                    COALESCE((SELECT cd.precio_venta_sugerido FROM tienda_compra_detalles cd
                              WHERE cd.producto_id = p.id AND cd.precio_venta_sugerido > 0
                                AND EXISTS (SELECT 1 FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'disponible')
                              ORDER BY cd.id DESC LIMIT 1), p.precio_venta_sugerido) AS precio_venta,
                    (SELECT MAX(pr.precio_descuento) FROM tienda_promociones pr
                      WHERE pr.producto_id = p.id AND pr.estado_activo = 1
                        AND pr.fecha_inicio <= CURDATE() AND pr.fecha_fin >= CURDATE()
                        AND pr.precio_descuento > 0) AS precio_descuento
             FROM tienda_productos p
             JOIN tienda_categorias c ON c.id = p.categoria_id
             JOIN tienda_marcas m ON m.id = p.marca_id
             WHERE p.id = ? AND p.estado_activo = 1 AND c.estado_activo = 1 AND m.estado_activo = 1"
        );
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) return null;

        $precioDescuento = $r['precio_descuento'] !== null ? (float) $r['precio_descuento'] : null;
        $rangos = $this->_mapaRangos();
        return [
            'id' => (int) $r['id'],
            'nombre' => $r['nombre'],
            'descripcion' => $r['descripcion'],
            'foto_path' => $r['foto_path'],
            'agotado' => (int) $r['agotado'],
            'categoria' => $r['categoria'],
            'marca' => $r['marca'],
            'precio_venta' => (float) $r['precio_venta'],
            'precio_multiunion' => $this->_multiunion((float) $r['precio_venta'], $rangos),
            'en_promocion' => $precioDescuento > 0 ? 1 : 0,
            'precio_descuento' => $precioDescuento,
            'promocion_porcentaje' => $precioDescuento > 0 && (float) $r['precio_venta'] > 0
                ? (int) round((1 - $precioDescuento / (float) $r['precio_venta']) * 100) : 0,
        ];
    }

    public function categorias(): array
    {
        return $this->db->query(
            "SELECT id, nombre FROM tienda_categorias WHERE estado_activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function marcas(): array
    {
        return $this->db->query(
            "SELECT id, nombre FROM tienda_marcas WHERE estado_activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    public function uploadsTiendaDir(): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') $base = dirname(__DIR__, 2) . '/' . $base;
        $dir = rtrim($base, '/') . '/tienda';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir;
    }

    private function _mapaRangos(): array
    {
        $map = [];
        foreach ($this->db->query("SELECT desde, hasta, porcentaje FROM tienda_rangos_precio")->fetchAll() as $r) {
            $map[] = ['desde' => (float) $r['desde'], 'hasta' => (float) $r['hasta'], 'porcentaje' => (float) $r['porcentaje']];
        }
        return $map;
    }

    private function _multiunion(float $precio, array $rangos): ?float
    {
        if ($precio <= 0) return null;
        $pct = 0.0;
        foreach ($rangos as $r) {
            if ($precio >= $r['desde'] && $precio <= $r['hasta']) { $pct = $r['porcentaje']; break; }
        }
        return (float) round($precio - ($precio * $pct / 100), -3);
    }
}