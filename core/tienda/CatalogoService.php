<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Catálogo de Tienda (B8): categorías, marcas y productos + rangos de precio.
 * Tablas: tienda_categorias, tienda_marcas, tienda_productos, tienda_rangos_precio.
 * Recursos: uploads/tienda/ (fotos opacas, servidas por API).
 *
 * El "precio Multiunión" es el precio del catálogo con el porcentaje del rango
 * aplicado: ROUND(precio - (precio * % / 100), -3) (regla del legacy).
 */
final class CatalogoService
{
    private const ORDER_COLS_CAT   = ['nombre', 'fecha_creacion'];
    private const ORDER_COLS_MARCA = ['nombre', 'fecha_creacion'];
    private const ORDER_COLS_PROD  = ['nombre', 'categoria', 'marca', 'precio_venta_sugerido', 'fecha_creacion', 'disponible'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    // ------------------------------------------------------------------
    // Categorías
    // ------------------------------------------------------------------

    public function listarCategorias(
        string $q = '',
        string $estado = '',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "c.nombre LIKE ?";
            $params[] = '%' . $q . '%';
        }
        if ($estado !== '' && in_array($estado, ['0', '1'], true)) {
            $where[] = "c.estado_activo = ?";
            $params[] = (int) $estado;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_categorias c $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_CAT, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT c.*, (SELECT COUNT(*) FROM tienda_productos p WHERE p.categoria_id = c.id) AS total_productos
                FROM tienda_categorias c $w ORDER BY c.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function listarCategoriasActivas(): array
    {
        return $this->db->query("SELECT id, nombre FROM tienda_categorias WHERE estado_activo = 1 ORDER BY nombre")->fetchAll();
    }

    public function obtenerCategoria(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM tienda_categorias WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function guardarCategoria(array $data, ?array $actor = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre de la categoría es requerido.');

        try {
            if ($id > 0) {
                $this->db->prepare(
                    "UPDATE tienda_categorias SET nombre = ?, estado_activo = ?, fecha_actualizacion = ? WHERE id = ?"
                )->execute([
                    $nombre,
                    isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                    date('Y-m-d H:i:s'),
                    $id,
                ]);
                Logger::log('editar', 'tienda.catalogo', $actor['id'] ?? null, "Editada categoría #$id ($nombre)");
                return ['id' => $id];
            }
            $st = $this->db->prepare("INSERT INTO tienda_categorias (nombre, estado_activo, creado_por) VALUES (?,?,?)");
            $st->execute([
                $nombre,
                isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                $actor['id'] ?? null,
            ]);
            $id = (int) $this->db->lastInsertId();
            Logger::log('crear', 'tienda.catalogo', $actor['id'] ?? null, "Creada categoría #$id ($nombre)");
            return ['id' => $id];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('Ya existe una categoría con ese nombre.');
            throw $e;
        }
    }

    public function toggleCategoria(int $id, ?array $actor = null): array
    {
        $c = $this->obtenerCategoria($id);
        if (!$c) throw new DomainException('Categoría no encontrada.');
        $nuevo = $c['estado_activo'] ? 0 : 1;
        $this->db->prepare("UPDATE tienda_categorias SET estado_activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'tienda.catalogo', $actor['id'] ?? null,
            ($nuevo ? 'Activada' : 'Inactivada') . " categoría #$id", null, null, 'warning');
        return ['id' => $id, 'estado_activo' => $nuevo];
    }

    public function eliminarCategoria(int $id, ?array $actor = null): void
    {
        $c = $this->obtenerCategoria($id);
        if (!$c) throw new DomainException('Categoría no encontrada.');
        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_productos WHERE categoria_id = ?");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: la categoría tiene productos asociados.');
        }
        $this->db->prepare("DELETE FROM tienda_categorias WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'tienda.catalogo', $actor['id'] ?? null, "Eliminada categoría #$id", null, null, 'warning');
    }

    // ------------------------------------------------------------------
    // Marcas
    // ------------------------------------------------------------------

    public function listarMarcas(
        string $q = '',
        string $estado = '',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "m.nombre LIKE ?";
            $params[] = '%' . $q . '%';
        }
        if ($estado !== '' && in_array($estado, ['0', '1'], true)) {
            $where[] = "m.estado_activo = ?";
            $params[] = (int) $estado;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_marcas m $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_MARCA, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT m.*, (SELECT COUNT(*) FROM tienda_productos p WHERE p.marca_id = m.id) AS total_productos
                FROM tienda_marcas m $w ORDER BY m.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function listarMarcasActivas(): array
    {
        return $this->db->query("SELECT id, nombre FROM tienda_marcas WHERE estado_activo = 1 ORDER BY nombre")->fetchAll();
    }

    public function obtenerMarca(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM tienda_marcas WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function guardarMarca(array $data, ?array $actor = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre de la marca es requerido.');

        try {
            if ($id > 0) {
                $this->db->prepare("UPDATE tienda_marcas SET nombre = ?, estado_activo = ?, fecha_actualizacion = ? WHERE id = ?")
                    ->execute([
                        $nombre,
                        isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                        date('Y-m-d H:i:s'),
                        $id,
                    ]);
                Logger::log('editar', 'tienda.catalogo', $actor['id'] ?? null, "Editada marca #$id ($nombre)");
                return ['id' => $id];
            }
            $st = $this->db->prepare("INSERT INTO tienda_marcas (nombre, estado_activo, creado_por) VALUES (?,?,?)");
            $st->execute([
                $nombre,
                isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                $actor['id'] ?? null,
            ]);
            $id = (int) $this->db->lastInsertId();
            Logger::log('crear', 'tienda.catalogo', $actor['id'] ?? null, "Creada marca #$id ($nombre)");
            return ['id' => $id];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('Ya existe una marca con ese nombre.');
            throw $e;
        }
    }

    public function toggleMarca(int $id, ?array $actor = null): array
    {
        $m = $this->obtenerMarca($id);
        if (!$m) throw new DomainException('Marca no encontrada.');
        $nuevo = $m['estado_activo'] ? 0 : 1;
        $this->db->prepare("UPDATE tienda_marcas SET estado_activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'tienda.catalogo', $actor['id'] ?? null,
            ($nuevo ? 'Activada' : 'Inactivada') . " marca #$id", null, null, 'warning');
        return ['id' => $id, 'estado_activo' => $nuevo];
    }

    public function eliminarMarca(int $id, ?array $actor = null): void
    {
        $m = $this->obtenerMarca($id);
        if (!$m) throw new DomainException('Marca no encontrada.');
        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_productos WHERE marca_id = ?");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: la marca tiene productos asociados.');
        }
        $this->db->prepare("DELETE FROM tienda_marcas WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'tienda.catalogo', $actor['id'] ?? null, "Eliminada marca #$id", null, null, 'warning');
    }

    // ------------------------------------------------------------------
    // Productos
    // ------------------------------------------------------------------

    public function listarProductos(
        string $q = '',
        int $categoriaId = 0,
        int $marcaId = 0,
        string $estado = '',
        string $agotado = '',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(p.nombre LIKE ? OR p.descripcion LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like]);
        }
        if ($categoriaId > 0) { $where[] = "p.categoria_id = ?"; $params[] = $categoriaId; }
        if ($marcaId > 0) { $where[] = "p.marca_id = ?"; $params[] = $marcaId; }
        if ($estado !== '' && in_array($estado, ['0', '1'], true)) {
            $where[] = "p.estado_activo = ?";
            $params[] = (int) $estado;
        }
        if ($agotado !== '' && in_array($agotado, ['0', '1'], true)) {
            $where[] = "p.agotado = ?";
            $params[] = (int) $agotado;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_productos p $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_PROD, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $col = match ($orderBy) {
            'categoria' => 'c.nombre',
            'marca' => 'm.nombre',
            'disponible' => '(SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = \'disponible\')',
            default => "p.$orderBy",
        };

        $sql = "SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS marca_nombre,
                       (SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = 'disponible') AS disponible,
                       (SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = 'vendido') AS vendido
                FROM tienda_productos p
                JOIN tienda_categorias c ON c.id = p.categoria_id
                JOIN tienda_marcas m ON m.id = p.marca_id
                $w ORDER BY $col $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        $rows = $st->fetchAll();
        $promos = $this->obtenerPromocionesVigentes();
        $rangos = $this->_mapaRangos();
        foreach ($rows as &$p) {
            $p['precio_multiunion'] = $this->_aplicarRango((float) $p['precio_venta_sugerido'], $rangos);
            $p['en_promocion'] = 0;
            $p['precio_descuento'] = null;
            if (isset($promos[$p['id']])) {
                $p['en_promocion'] = 1;
                $p['precio_descuento'] = $promos[$p['id']]['precio_descuento'];
                $p['promocion_porcentaje'] = $promos[$p['id']]['porcentaje'];
            }
        }

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function obtenerProducto(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS marca_nombre,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = 'disponible') AS disponible,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = 'vendido') AS vendido
             FROM tienda_productos p
             JOIN tienda_categorias c ON c.id = p.categoria_id
             JOIN tienda_marcas m ON m.id = p.marca_id
             WHERE p.id = ?"
        );
        $st->execute([$id]);
        $p = $st->fetch();
        if (!$p) return null;
        $p['precio_multiunion'] = $this->_aplicarRango((float) $p['precio_venta_sugerido'], $this->_mapaRangos());
        $promos = $this->obtenerPromocionesVigentes();
        $p['en_promocion'] = 0;
        $p['precio_descuento'] = null;
        if (isset($promos[$p['id']])) {
            $p['en_promocion'] = 1;
            $p['precio_descuento'] = $promos[$p['id']]['precio_descuento'];
            $p['promocion_porcentaje'] = $promos[$p['id']]['porcentaje'];
        }
        return $p;
    }

    public function guardarProducto(array $data, ?array $actor = null, ?array $file = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $categoriaId = (int) ($data['categoria_id'] ?? 0);
        $marcaId = (int) ($data['marca_id'] ?? 0);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($categoriaId <= 0 || !$this->obtenerCategoria($categoriaId)) {
            throw new DomainException('Debe seleccionar una categoría válida.');
        }
        if ($marcaId <= 0 || !$this->obtenerMarca($marcaId)) {
            throw new DomainException('Debe seleccionar una marca válida.');
        }
        if ($nombre === '') throw new DomainException('El nombre del producto es requerido.');

        $precioCompra = (isset($data['precio_compra_sugerido']) && $data['precio_compra_sugerido'] !== '')
            ? (float) $data['precio_compra_sugerido'] : null;
        $precioVenta = (isset($data['precio_venta_sugerido']) && $data['precio_venta_sugerido'] !== '')
            ? (float) $data['precio_venta_sugerido'] : null;
        if ($precioCompra !== null && $precioCompra < 0) throw new DomainException('El precio de compra debe ser >= $0.');
        if ($precioVenta !== null && $precioVenta < 0) throw new DomainException('El precio de venta debe ser >= $0.');
        $agotado = isset($data['agotado']) ? ((int) $data['agotado'] === 1 ? 1 : 0) : 0;

        $foto = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $foto = $this->_guardarImagen($file);
        } elseif (isset($data['foto_path']) && $data['foto_path'] !== '') {
            $foto = $this->_normalizarImagen((string) $data['foto_path']);
        }

        try {
            if ($id > 0) {
                $sets = 'categoria_id = ?, marca_id = ?, nombre = ?, descripcion = ?, precio_compra_sugerido = ?, precio_venta_sugerido = ?, estado_activo = ?, agotado = ?, fecha_actualizacion = ?';
                $vals = [
                    $categoriaId, $marcaId, $nombre, trim((string) ($data['descripcion'] ?? '')),
                    $precioCompra, $precioVenta,
                    isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                    $agotado,
                    date('Y-m-d H:i:s'),
                ];
                if ($foto !== null) {
                    $sets .= ', foto_path = ?';
                    $vals[] = $foto;
                }
                $vals[] = $id;
                $this->db->prepare("UPDATE tienda_productos SET $sets WHERE id = ?")->execute($vals);
                Logger::log('editar', 'tienda.catalogo', $actor['id'] ?? null, "Editado producto #$id ($nombre)");
                return ['id' => $id];
            }

            $st = $this->db->prepare(
                "INSERT INTO tienda_productos (categoria_id, marca_id, nombre, descripcion, foto_path, precio_compra_sugerido, precio_venta_sugerido, estado_activo, agotado, creado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            );
            $st->execute([
                $categoriaId, $marcaId, $nombre, trim((string) ($data['descripcion'] ?? '')), $foto,
                $precioCompra, $precioVenta,
                isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                $agotado,
                $actor['id'] ?? null,
            ]);
            $id = (int) $this->db->lastInsertId();
            Logger::log('crear', 'tienda.catalogo', $actor['id'] ?? null, "Creado producto #$id ($nombre)");
            return ['id' => $id];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') throw new DomainException('Ya existe un producto con esa categoría, marca y nombre.');
            throw $e;
        }
    }

    public function toggleProducto(int $id, ?array $actor = null): array
    {
        $p = $this->obtenerProducto($id);
        if (!$p) throw new DomainException('Producto no encontrado.');
        $nuevo = $p['estado_activo'] ? 0 : 1;
        $this->db->prepare("UPDATE tienda_productos SET estado_activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'tienda.catalogo', $actor['id'] ?? null,
            ($nuevo ? 'Activado' : 'Inactivado') . " producto #$id", null, null, 'warning');
        return ['id' => $id, 'estado_activo' => $nuevo];
    }

    public function toggleAgotado(int $id, ?array $actor = null): array
    {
        $p = $this->obtenerProducto($id);
        if (!$p) throw new DomainException('Producto no encontrado.');
        $nuevo = $p['agotado'] ? 0 : 1;
        $this->db->prepare("UPDATE tienda_productos SET agotado = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'marcar_agotado' : 'marcar_disponible', 'tienda.catalogo', $actor['id'] ?? null,
            ($nuevo ? 'Marcado agotado' : 'Marcado disponible') . " producto #$id", null, null, 'warning');
        return ['id' => $id, 'agotado' => $nuevo];
    }

    public function eliminarProducto(int $id, ?array $actor = null): void
    {
        $p = $this->obtenerProducto($id);
        if (!$p) throw new DomainException('Producto no encontrado.');
        $st = $this->db->query("SELECT
                (SELECT COUNT(*) FROM tienda_stock WHERE producto_id = $id) +
                (SELECT COUNT(*) FROM tienda_promociones WHERE producto_id = $id)");
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: el producto tiene stock o promociones asociadas.');
        }
        $this->db->prepare("DELETE FROM tienda_productos WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'tienda.catalogo', $actor['id'] ?? null, "Eliminado producto #$id", null, null, 'warning');
    }

    // ------------------------------------------------------------------
    // Rangos de precio (precio Multiunión)
    // ------------------------------------------------------------------

    public function listarRangosPrecio(): array
    {
        return $this->db->query("SELECT * FROM tienda_rangos_precio ORDER BY desde")->fetchAll();
    }

    public function obtenerRangoPrecio(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM tienda_rangos_precio WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function guardarRangoPrecio(array $data, ?array $actor = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $desde = (float) ($data['desde'] ?? 0);
        $hasta = (float) ($data['hasta'] ?? 0);
        $porcentaje = (float) ($data['porcentaje'] ?? 0);
        if ($desde < 0 || $hasta < 0) throw new DomainException('Los límites del rango deben ser >= $0.');
        if ($desde >= $hasta) throw new DomainException('El valor "desde" debe ser menor que "hasta".');
        if ($porcentaje < 0 || $porcentaje > 100) throw new DomainException('El porcentaje debe estar entre 0 y 100.');

        try {
            if ($id > 0) {
                $this->db->prepare("UPDATE tienda_rangos_precio SET desde = ?, hasta = ?, porcentaje = ? WHERE id = ?")
                    ->execute([$desde, $hasta, $porcentaje, $id]);
                Logger::log('editar', 'tienda.rangos_precio', $actor['id'] ?? null, "Editado rango de precio #$id ($desde-$hasta)");
                return ['id' => $id];
            }
            $st = $this->db->prepare("INSERT INTO tienda_rangos_precio (desde, hasta, porcentaje, creado_por) VALUES (?,?,?,?)");
            $st->execute([$desde, $hasta, $porcentaje, $actor['id'] ?? null]);
            $id = (int) $this->db->lastInsertId();
            Logger::log('crear', 'tienda.rangos_precio', $actor['id'] ?? null, "Creado rango de precio #$id ($desde-$hasta)");
            return ['id' => $id];
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new DomainException('El rango se solapa con uno existente o repite un límite.');
            }
            throw $e;
        }
    }

    public function eliminarRangoPrecio(int $id, ?array $actor = null): void
    {
        if (!$this->obtenerRangoPrecio($id)) throw new DomainException('Rango de precio no encontrado.');
        $this->db->prepare("DELETE FROM tienda_rangos_precio WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'tienda.rangos_precio', $actor['id'] ?? null, "Eliminado rango de precio #$id", null, null, 'warning');
    }

    /** Promociones vigentes (estado_activo + fechas hoy) como mapa producto_id => [precio_descuento, porcentaje]. */
    public function obtenerPromocionesVigentes(): array
    {
        $st = $this->db->query(
            "SELECT pr.producto_id, pr.precio_descuento, p.precio_venta_sugerido
             FROM tienda_promociones pr
             JOIN tienda_productos p ON p.id = pr.producto_id
             WHERE pr.estado_activo = 1 AND pr.fecha_inicio <= CURDATE() AND pr.fecha_fin >= CURDATE()
               AND p.precio_venta_sugerido > 0"
        );
        $map = [];
        foreach ($st->fetchAll() as $r) {
            $map[(int) $r['producto_id']] = [
                'precio_descuento' => (float) $r['precio_descuento'],
                'porcentaje' => (int) round((1 - (float) $r['precio_descuento'] / (float) $r['precio_venta_sugerido']) * 100),
            ];
        }
        return $map;
    }

    private function _mapaRangos(): array
    {
        $map = [];
        foreach ($this->db->query("SELECT desde, hasta, porcentaje FROM tienda_rangos_precio")->fetchAll() as $r) {
            $map[] = ['desde' => (float) $r['desde'], 'hasta' => (float) $r['hasta'], 'porcentaje' => (float) $r['porcentaje']];
        }
        return $map;
    }

    /** Aplica el rango de descuento al precio de referencia (formato legacy: ROUND(..., -3)). */
    public function _aplicarRango(float $precio, ?array $rangos = null): ?float
    {
        if ($precio <= 0) return null;
        $rangos = $rangos ?? $this->_mapaRangos();
        $pct = 0.0;
        foreach ($rangos as $r) {
            if ($precio >= $r['desde'] && $precio <= $r['hasta']) { $pct = $r['porcentaje']; break; }
        }
        return (float) round($precio - ($precio * $pct / 100), -3);
    }

    // ------------------------------------------------------------------
    // Fotos (opacas, servidas por API)
    // ------------------------------------------------------------------

    public function fotoUrl(mixed $foto, ?callable $resolver = null): ?string
    {
        if (empty($foto)) return null;
        $s = (string) $foto;
        if (str_starts_with($s, 'http://') || str_starts_with($s, 'https://')) return $s;
        if ($resolver !== null) return $resolver($s);
        return $s;
    }

    public function uploadsTiendaDir(): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') $base = dirname(__DIR__, 2) . '/' . $base;
        $dir = rtrim($base, '/') . '/tienda';
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
        return $dir;
    }

    private function _guardarImagen(array $file): string
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new DomainException('No se pudo subir la imagen.');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new DomainException('Formato de imagen no permitido (jpg/jpeg/png/webp).');
        }
        $maxMb = (float) (getenv('UPLOAD_MAX_IMAGE_MB') ?: '5');
        if ($file['size'] > $maxMb * 1024 * 1024) throw new DomainException("La imagen supera el límite de {$maxMb} MB.");
        $nombre = 'tienda_producto_' . substr(hash('sha256', (string) microtime(true)), 0, 16) . '.' . $ext;
        $dir = $this->uploadsTiendaDir();
        if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $nombre)) throw new DomainException('No se pudo guardar la imagen.');
        return $nombre;
    }

    private function _normalizarImagen(string $imagen): string
    {
        $imagen = trim($imagen);
        if (str_starts_with($imagen, 'http://') || str_starts_with($imagen, 'https://')) return $imagen;
        if (preg_match('/^[A-Za-z0-9_.-]+$/', $imagen)) return $imagen; // basename opaco
        return '';
    }
}