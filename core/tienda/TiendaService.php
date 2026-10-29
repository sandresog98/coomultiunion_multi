<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';

/**
 * Núcleo transaccional de Tienda (B8): compras → stock (1 fila por unidad),
 * ventas (multi-unidad, ganancia calculada), reversiones → bonos, bonos como
 * medio de pago, y promociones.
 *
 * Invariante de stock: ingresado = vendido + disponible + anulado (las unidades
 * nunca se borran; al retirarlas pasan a estado 'anulado').
 * INVARIANTE venta: Σ pagos == total (fx del ítem), en una transacción.
 */
final class TiendaService
{
    private const ORDER_COLS_COMPRAS = ['id', 'fecha_compra', 'fecha_creacion', 'total_items', 'total_cantidad', 'total_valor'];
    private const ORDER_COLS_VENTAS  = ['id', 'fecha_venta', 'fecha_creacion', 'total', 'tipo_cliente', 'ceco'];
    private const ORDER_COLS_REV     = ['id', 'fecha_creacion'];
    private const ORDER_COLS_BONOS   = ['id', 'monto_original', 'saldo', 'estado', 'fecha_creacion'];
    private const ORDER_COLS_PROMO   = ['id', 'precio_descuento', 'fecha_inicio', 'fecha_fin', 'estado_activo'];
    private const ORDER_COLS_STOCK   = ['nombre', 'categoria', 'marca', 'ingresado', 'vendido', 'disponible'];

    private const TIPOS_PAGO          = ['efectivo', 'bold', 'qr', 'sifone', 'sistecredito', 'alocredito', 'bono'];
    private const TIPOS_PAGO_CON_NUMERO = ['sifone', 'sistecredito', 'alocredito'];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    // ==================================================================
    // PROMOCIONES
    // ==================================================================

    public function listarPromociones(
        string $q = '',
        int $productoId = 0,
        string $estado = '',
        string $soloVigentes = '',
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(p.nombre LIKE ? OR c.nombre LIKE ? OR m.nombre LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($productoId > 0) { $where[] = "pr.producto_id = ?"; $params[] = $productoId; }
        if ($estado !== '' && in_array($estado, ['0', '1'], true)) {
            $where[] = "pr.estado_activo = ?";
            $params[] = (int) $estado;
        }
        if ($soloVigentes === '1') {
            $where[] = "pr.estado_activo = 1 AND pr.fecha_inicio <= CURDATE() AND pr.fecha_fin >= CURDATE()";
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_promociones pr
                                  JOIN tienda_productos p ON p.id = pr.producto_id
                                  JOIN tienda_categorias c ON c.id = p.categoria_id
                                  JOIN tienda_marcas m ON m.id = p.marca_id $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_PROMO, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT pr.*, p.nombre AS producto_nombre, c.nombre AS categoria_nombre, m.nombre AS marca_nombre
                FROM tienda_promociones pr
                JOIN tienda_productos p ON p.id = pr.producto_id
                JOIN tienda_categorias c ON c.id = p.categoria_id
                JOIN tienda_marcas m ON m.id = p.marca_id
                $w ORDER BY pr.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();

        foreach ($rows as &$r) {
            $r['vigente'] = ((int) $r['estado_activo'] === 1
                && date('Y-m-d') >= $r['fecha_inicio'] && date('Y-m-d') <= $r['fecha_fin']) ? 1 : 0;
        }

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function obtenerPromocion(int $id): ?array
    {
        $st = $this->db->prepare(
            "SELECT pr.*, p.nombre AS producto_nombre FROM tienda_promociones pr
             JOIN tienda_productos p ON p.id = pr.producto_id WHERE pr.id = ?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if ($row) $row['vigente'] = ((int) $row['estado_activo'] === 1
            && date('Y-m-d') >= $row['fecha_inicio'] && date('Y-m-d') <= $row['fecha_fin']) ? 1 : 0;
        return $row ?: null;
    }

    public function guardarPromocion(array $data, ?array $actor = null): array
    {
        $id = (int) ($data['id'] ?? 0);
        $productoId = (int) ($data['producto_id'] ?? 0);
        $precioDescuento = (float) ($data['precio_descuento'] ?? 0);
        $fechaInicio = trim((string) ($data['fecha_inicio'] ?? ''));
        $fechaFin = trim((string) ($data['fecha_fin'] ?? ''));

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_productos WHERE id = ?");
        $st->execute([$productoId]);
        if ($productoId <= 0 || (int) $st->fetchColumn() === 0) {
            throw new DomainException('Debe seleccionar un producto válido.');
        }
        if ($precioDescuento <= 0) throw new DomainException('El precio de descuento debe ser mayor a $0.');
        $dI = DateTime::createFromFormat('Y-m-d', $fechaInicio);
        $dF = DateTime::createFromFormat('Y-m-d', $fechaFin);
        if (!$dI || !$dF) throw new DomainException('Fechas de la promoción inválidas (YYYY-MM-DD).');
        if ($dF < $dI) throw new DomainException('La fecha de fin no puede ser anterior a la de inicio.');

        if ($id > 0) {
            $this->db->prepare(
                "UPDATE tienda_promociones SET producto_id = ?, precio_descuento = ?, fecha_inicio = ?, fecha_fin = ?, estado_activo = ?, fecha_actualizacion = ? WHERE id = ?"
            )->execute([
                $productoId, $precioDescuento, $fechaInicio, $fechaFin,
                isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
                date('Y-m-d H:i:s'), $id,
            ]);
            Logger::log('editar', 'tienda.promociones', $actor['id'] ?? null, "Editada promoción #$id (producto #$productoId)");
            return ['id' => $id];
        }

        $st = $this->db->prepare(
            "INSERT INTO tienda_promociones (producto_id, precio_descuento, fecha_inicio, fecha_fin, estado_activo, creado_por)
             VALUES (?,?,?,?,?,?)"
        );
        $st->execute([
            $productoId, $precioDescuento, $fechaInicio, $fechaFin,
            isset($data['estado_activo']) ? ((int) $data['estado_activo'] === 1 ? 1 : 0) : 1,
            $actor['id'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'tienda.promociones', $actor['id'] ?? null, "Creada promoción #$id (producto #$productoId)");
        return ['id' => $id];
    }

    public function togglePromocion(int $id, ?array $actor = null): array
    {
        $pr = $this->obtenerPromocion($id);
        if (!$pr) throw new DomainException('Promoción no encontrada.');
        $nuevo = $pr['estado_activo'] ? 0 : 1;
        $this->db->prepare("UPDATE tienda_promociones SET estado_activo = ?, fecha_actualizacion = ? WHERE id = ?")
            ->execute([$nuevo, date('Y-m-d H:i:s'), $id]);
        Logger::log($nuevo ? 'activar' : 'inactivar', 'tienda.promociones', $actor['id'] ?? null,
            ($nuevo ? 'Activada' : 'Inactivada') . " promoción #$id", null, null, 'warning');
        return ['id' => $id, 'estado_activo' => $nuevo];
    }

    public function eliminarPromocion(int $id, ?array $actor = null): void
    {
        if (!$this->obtenerPromocion($id)) throw new DomainException('Promoción no encontrada.');
        $this->db->prepare("DELETE FROM tienda_promociones WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'tienda.promociones', $actor['id'] ?? null, "Eliminada promoción #$id", null, null, 'warning');
    }

    // ==================================================================
    // COMPRAS (ingreso a inventario)
    // ==================================================================

    public function listarCompras(
        string $q = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '' && ctype_digit($q)) {
            $where[] = "co.id = ?";
            $params[] = (int) $q;
        }
        if ($fechaDesde !== '') { $where[] = "co.fecha_compra >= ?"; $params[] = $fechaDesde; }
        if ($fechaHasta !== '') { $where[] = "co.fecha_compra <= ?"; $params[] = $fechaHasta; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_compras co $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_COMPRAS, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT co.*,
                       COUNT(DISTINCT cd.id) AS total_items,
                       COALESCE(SUM(cd.cantidad), 0) AS total_cantidad,
                       COALESCE(SUM(cd.cantidad * cd.precio_compra), 0) AS total_valor
                FROM tienda_compras co
                LEFT JOIN tienda_compra_detalles cd ON cd.compra_id = co.id
                $w GROUP BY co.id ORDER BY co.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function obtenerCompra(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM tienda_compras WHERE id = ?");
        $st->execute([$id]);
        $compra = $st->fetch();
        if (!$compra) return null;

        $st = $this->db->prepare(
            "SELECT cd.*, p.nombre AS producto_nombre, c.nombre AS categoria_nombre, m.nombre AS marca_nombre,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'vendido') AS vendido,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'disponible') AS disponible,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'anulado') AS anulado
             FROM tienda_compra_detalles cd
             JOIN tienda_productos p ON p.id = cd.producto_id
             JOIN tienda_categorias c ON c.id = p.categoria_id
             JOIN tienda_marcas m ON m.id = p.marca_id
             WHERE cd.compra_id = ? ORDER BY cd.id"
        );
        $st->execute([$id]);
        $detalles = $st->fetchAll();

        if ($detalles) {
            $ids = array_map(static fn(array $d): int => (int) $d['id'], $detalles);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stU = $this->db->prepare(
                "SELECT compra_detalle_id, id, numero_serie, estado
                 FROM tienda_stock WHERE compra_detalle_id IN ($in) ORDER BY id"
            );
            $stU->execute($ids);
            $unidades = [];
            foreach ($stU->fetchAll() as $u) {
                $unidades[(int) $u['compra_detalle_id']][] = $u;
            }
            foreach ($detalles as &$d) {
                $d['unidades'] = $unidades[(int) $d['id']] ?? [];
                $series = array_values(array_filter(array_map(static fn($u) => $u['numero_serie'], $d['unidades'])));
                $d['series'] = $series;
                $d['con_serie'] = $series !== [] ? 1 : 0;
            }
            unset($d);
        }
        $compra['detalles'] = $detalles;
        return $compra;
    }

    /** Crea una compra y genera las unidades físicas (tienda_stock) dentro de la transacción. */
    public function guardarCompra(array $data, ?array $actor = null): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) {
            throw new DomainException('Debe agregar al menos un producto a la compra.');
        }
        $fechaCompra = $this->_validarFechaOpcional($data['fecha_compra'] ?? null);
        $obs = trim((string) ($data['observacion'] ?? ''));

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare("INSERT INTO tienda_compras (usuario_id, observacion, fecha_compra) VALUES (?,?,?)");
            $st->execute([$actor['id'] ?? null, $obs !== '' ? $obs : null, $fechaCompra]);
            $compraId = (int) $this->db->lastInsertId();

            foreach ($items as $it) {
                $this->_insertarDetalle($compraId, $it);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Logger::log('crear', 'tienda.compras', $actor['id'] ?? null, "Creada compra #$compraId con " . count($items) . ' ítems');
        return ['id' => $compraId];
    }

    public function editarCompra(int $id, array $data, ?array $actor = null): array
    {
        $compra = $this->obtenerCompra($id);
        if (!$compra) throw new DomainException('Compra no encontrada.');

        $fechaCompra = $this->_validarFechaOpcional($data['fecha_compra'] ?? null) ?? $compra['fecha_compra'];
        $obs = isset($data['observacion']) ? trim((string) $data['observacion']) : $compra['observacion'];

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE tienda_compras SET fecha_compra = ?, observacion = ?, fecha_actualizacion = ? WHERE id = ?")
                ->execute([$fechaCompra, $obs !== '' ? $obs : null, date('Y-m-d H:i:s'), $id]);

            foreach (($data['items'] ?? []) as $it) {
                $detId = (int) ($it['id'] ?? 0);
                if ($detId > 0) {
                    $this->_editarDetalle($detId, $it);
                } else {
                    $this->_insertarDetalle($id, $it);
                }
            }
            foreach (($data['delete_ids'] ?? []) as $did) {
                $this->_eliminarDetalle((int) $did);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Logger::log('editar', 'tienda.compras', $actor['id'] ?? null, "Editada compra #$id");
        return ['id' => $id];
    }

    public function eliminarCompra(int $id, ?array $actor = null): void
    {
        $compra = $this->obtenerCompra($id);
        if (!$compra) throw new DomainException('Compra no encontrada.');

        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM tienda_stock s WHERE s.compra_detalle_id IN (SELECT id FROM tienda_compra_detalles WHERE compra_id = ?) AND s.estado = 'vendido'"
        );
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: la compra tiene unidades vendidas.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM tienda_stock WHERE compra_detalle_id IN (SELECT id FROM tienda_compra_detalles WHERE compra_id = ?)")->execute([$id]);
            $this->db->prepare("DELETE FROM tienda_compra_detalles WHERE compra_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM tienda_compras WHERE id = ?")->execute([$id]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Logger::log('eliminar', 'tienda.compras', $actor['id'] ?? null, "Eliminada compra #$id", null, null, 'warning');
    }

    private function _validarFechaOpcional(?string $f): ?string
    {
        $f = trim((string) $f);
        if ($f === '') return null;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) || DateTime::createFromFormat('Y-m-d', $f) === false) {
            throw new DomainException('Fecha de compra inválida (YYYY-MM-DD).');
        }
        return $f;
    }

    private function _insertarDetalle(int $compraId, array $it): int
    {
        $productoId = (int) ($it['producto_id'] ?? 0);
        $cantidad = (int) ($it['cantidad'] ?? 0);
        $precioCompra = (float) ($it['precio_compra'] ?? 0);
        $precioVentaSugerido = (float) ($it['precio_venta_sugerido'] ?? 0);

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_productos WHERE id = ? AND estado_activo = 1");
        $st->execute([$productoId]);
        if ($productoId <= 0 || (int) $st->fetchColumn() === 0) {
            throw new DomainException('Producto inválido en un ítem de la compra.');
        }
        if ($cantidad < 1) throw new DomainException('La cantidad de cada ítem debe ser al menos 1.');
        if ($precioCompra < 0 || $precioVentaSugerido < 0) throw new DomainException('Los precios de la compra deben ser >= $0.');

        $st = $this->db->prepare(
            "INSERT INTO tienda_compra_detalles (compra_id, producto_id, cantidad, precio_compra, precio_venta_sugerido)
             VALUES (?,?,?,?,?)"
        );
        $st->execute([$compraId, $productoId, $cantidad, $precioCompra, $precioVentaSugerido]);
        $detId = (int) $this->db->lastInsertId();

        $imeis = $it['imeis'] ?? [];
        $imeis = is_array($imeis)
            ? array_values(array_unique(array_filter(array_map(static fn($v) => trim((string) $v), $imeis), static fn($v) => $v !== '')))
            : [];
        $this->_insertarStock($detId, $productoId, $cantidad, $precioCompra, $imeis);
        return $detId;
    }

    private function _insertarStock(int $detId, int $productoId, int $cantidad, float $precioCompra, array $imeis = []): void
    {
        if ($imeis !== []) {
            if (count($imeis) !== $cantidad) {
                throw new DomainException("Se indicaron " . count($imeis) . " series para una cantidad de $cantidad. Deben coincidir.");
            }
            $st = $this->db->prepare(
                "INSERT INTO tienda_stock (compra_detalle_id, producto_id, precio_compra, numero_serie, estado) VALUES (?,?,?,?,'disponible')"
            );
            foreach ($imeis as $serie) {
                try {
                    $st->execute([$detId, $productoId, $precioCompra, $serie]);
                } catch (PDOException $e) {
                    if ($e->getCode() === '23000') throw new DomainException("La serie '$serie' ya existe en el inventario.");
                    throw $e;
                }
            }
        } else {
            $st = $this->db->prepare(
                "INSERT INTO tienda_stock (compra_detalle_id, producto_id, precio_compra, estado) VALUES (?,?,?,'disponible')"
            );
            for ($i = 0; $i < $cantidad; $i++) {
                $st->execute([$detId, $productoId, $precioCompra]);
            }
        }
    }

    private function _editarDetalle(int $detId, array $it): void
    {
        $st = $this->db->prepare("SELECT * FROM tienda_compra_detalles WHERE id = ?");
        $st->execute([$detId]);
        $det = $st->fetch();
        if (!$det) throw new DomainException('Ítem de compra no encontrado.');

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_stock WHERE compra_detalle_id = ? AND estado = 'vendido'");
        $st->execute([$detId]);
        $vendidos = (int) $st->fetchColumn();
        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_stock WHERE compra_detalle_id = ? AND estado IN ('disponible','anulado')");
        $st->execute([$detId]);
        $disponibles = (int) $st->fetchColumn();

        $precioCompra = isset($it['precio_compra']) ? (float) $it['precio_compra'] : (float) $det['precio_compra'];
        $precioVenta = isset($it['precio_venta_sugerido']) ? (float) $it['precio_venta_sugerido'] : (float) $det['precio_venta_sugerido'];
        if ($vendidos > 0 && abs($precioCompra - (float) $det['precio_compra']) > 0.001) {
            throw new DomainException('El costo de un ítem con unidades vendidas no se puede modificar.');
        }
        if ($precioCompra < 0 || $precioVenta < 0) throw new DomainException('Los precios de la compra deben ser >= $0.');
        $cantidad = isset($it['cantidad']) ? (int) $it['cantidad'] : (int) $det['cantidad'];
        if ($cantidad < $vendidos) {
            throw new DomainException("La cantidad no puede ser menor a las $vendidos unidades ya vendidas.");
        }

        $this->db->prepare(
            "UPDATE tienda_compra_detalles SET cantidad = ?, precio_compra = ?, precio_venta_sugerido = ? WHERE id = ?"
        )->execute([$cantidad, $precioCompra, $precioVenta, $detId]);

        $libres = $disponibles;
        if ($cantidad - $vendidos > $libres) {
            $nuevas = ($cantidad - $vendidos) - $libres;
            $this->_insertarStock($detId, (int) $det['producto_id'], $nuevas, $precioCompra);
        } elseif ($cantidad - $vendidos < $libres) {
            $exceso = $libres - ($cantidad - $vendidos);
            $st = $this->db->prepare(
                "SELECT id FROM tienda_stock WHERE compra_detalle_id = ? AND estado = 'disponible' ORDER BY id DESC LIMIT ?"
            );
            $st->bindValue(1, $detId);
            $st->bindValue(2, $exceso, PDO::PARAM_INT);
            $st->execute();
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);
            $stUp = $this->db->prepare("UPDATE tienda_stock SET estado = 'anulado', fecha_actualizacion = ? WHERE id = ?");
            foreach ($ids as $sid) $stUp->execute([date('Y-m-d H:i:s'), $sid]);
        }

        if (isset($it['imeis']) && is_array($it['imeis'])) {
            $series = array_values(array_unique(array_filter(array_map(static fn($v) => trim((string) $v), $it['imeis']), static fn($v) => $v !== '')));
            if ($series !== []) {
                $st = $this->db->prepare(
                    "SELECT id FROM tienda_stock WHERE compra_detalle_id = ? AND estado = 'disponible' AND numero_serie IS NULL ORDER BY id LIMIT ?"
                );
                $st->bindValue(1, $detId);
                $st->bindValue(2, count($series), PDO::PARAM_INT);
                $st->execute();
                $libresIds = $st->fetchAll(PDO::FETCH_COLUMN);
                if (count($libresIds) !== count($series)) {
                    throw new DomainException('No hay suficientes unidades disponibles sin serie para asignarlas.');
                }
                $stUp = $this->db->prepare("UPDATE tienda_stock SET numero_serie = ? WHERE id = ?");
                foreach ($series as $i => $serie) {
                    try {
                        $stUp->execute([$serie, $libresIds[$i]]);
                    } catch (PDOException $e) {
                        if ($e->getCode() === '23000') throw new DomainException("La serie '$serie' ya existe en el inventario.");
                        throw $e;
                    }
                }
            }
        }
    }

    private function _eliminarDetalle(int $detId): void
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_stock WHERE compra_detalle_id = ? AND estado = 'vendido'");
        $st->execute([$detId]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar un ítem con unidades vendidas.');
        }
        $this->db->prepare("DELETE FROM tienda_stock WHERE compra_detalle_id = ? ORDER BY id")->execute([$detId]);
        $this->db->prepare("DELETE FROM tienda_compra_detalles WHERE id = ?")->execute([$detId]);
    }

    // ==================================================================
    // STOCK / INVENTARIO
    // ==================================================================

    public function listarStock(
        string $q = '',
        int $categoriaId = 0,
        int $marcaId = 0,
        string $estado = '',
        string $orderBy = 'nombre',
        string $orderDir = 'ASC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = ['(st.total IS NOT NULL OR ci.ingresado IS NOT NULL)'];
        $params = [];
        if ($q !== '') {
            $where[] = "p.nombre LIKE ?";
            $params[] = '%' . $q . '%';
        }
        if ($categoriaId > 0) { $where[] = "p.categoria_id = ?"; $params[] = $categoriaId; }
        if ($marcaId > 0) { $where[] = "p.marca_id = ?"; $params[] = $marcaId; }
        if ($estado === 'disponible') $where[] = "COALESCE(st.disponible, 0) > 0";
        elseif ($estado === 'vendido') $where[] = "COALESCE(st.vendido, 0) > 0";
        $w = 'WHERE ' . implode(' AND ', $where);

        $from = "FROM tienda_productos p
                 JOIN tienda_categorias c ON c.id = p.categoria_id
                 JOIN tienda_marcas m ON m.id = p.marca_id
                 LEFT JOIN (
                     SELECT producto_id, SUM(cantidad) AS ingresado
                     FROM tienda_compra_detalles GROUP BY producto_id
                 ) ci ON ci.producto_id = p.id
                 LEFT JOIN (
                     SELECT producto_id, COUNT(*) AS total,
                            SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) AS vendido,
                            SUM(CASE WHEN estado = 'disponible' THEN 1 ELSE 0 END) AS disponible,
                            SUM(CASE WHEN estado = 'anulado' THEN 1 ELSE 0 END) AS anulado
                     FROM tienda_stock GROUP BY producto_id
                 ) st ON st.producto_id = p.id";

        $st = $this->db->prepare("SELECT COUNT(*) $from $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_STOCK, true) ? $orderBy : 'nombre';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $col = match ($orderBy) {
            'categoria' => 'c.nombre',
            'marca' => 'm.nombre',
            'ingresado' => 'COALESCE(ci.ingresado, 0)',
            'vendido' => 'COALESCE(st.vendido, 0)',
            'disponible' => 'COALESCE(st.disponible, 0)',
            default => "p.$orderBy",
        };

        $sql = "SELECT p.id, p.nombre, p.foto_path, p.estado_activo,
                       c.nombre AS categoria_nombre, m.nombre AS marca_nombre,
                       COALESCE(ci.ingresado, 0) AS ingresado,
                       COALESCE(st.vendido, 0) AS vendido,
                       COALESCE(st.disponible, 0) AS disponible,
                       COALESCE(st.anulado, 0) AS anulado
                $from $w ORDER BY $col $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Lotes + historial de ventas + reversiones de un producto (modal de inventario). */
    public function detalleProductoStock(int $productoId): array
    {
        $st = $this->db->prepare("SELECT p.*, c.nombre AS categoria_nombre, m.nombre AS marca_nombre FROM tienda_productos p
                                  JOIN tienda_categorias c ON c.id = p.categoria_id
                                  JOIN tienda_marcas m ON m.id = p.marca_id WHERE p.id = ?");
        $st->execute([$productoId]);
        $producto = $st->fetch();
        if (!$producto) throw new DomainException('Producto no encontrado.');

        $st = $this->db->prepare(
            "SELECT cd.id, cd.precio_compra, cd.precio_venta_sugerido, co.id AS compra_id, co.fecha_compra,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'vendido') AS vendido,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'disponible') AS disponible,
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'anulado') AS anulado
             FROM tienda_compra_detalles cd
             JOIN tienda_compras co ON co.id = cd.compra_id
             WHERE cd.producto_id = ? ORDER BY co.fecha_compra DESC, cd.id DESC"
        );
        $st->execute([$productoId]);
        $lotes = $st->fetchAll();
        if ($lotes) {
            $ids = array_map(static fn(array $l): int => (int) $l['id'], $lotes);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stS = $this->db->prepare(
                "SELECT compra_detalle_id, numero_serie, estado
                 FROM tienda_stock
                 WHERE compra_detalle_id IN ($in) AND numero_serie IS NOT NULL
                 ORDER BY id"
            );
            $stS->execute($ids);
            $series = [];
            foreach ($stS->fetchAll() as $s) {
                $series[(int) $s['compra_detalle_id']][] = $s;
            }
            foreach ($lotes as &$l) {
                $l['series'] = $series[(int) $l['id']] ?? [];
            }
            unset($l);
        }
        $producto['lotes'] = $lotes;

        $st = $this->db->prepare(
            "SELECT v.id AS venta_id, v.fecha_venta, v.tipo_cliente, v.cedula, v.cliente_id, vd.cantidad, vd.precio_unitario, vd.subtotal, u.nombre_completo AS vendedor
             FROM tienda_venta_detalles vd
             JOIN tienda_ventas v ON v.id = vd.venta_id
             LEFT JOIN usuarios u ON u.id = v.usuario_id
             WHERE vd.producto_id = ? ORDER BY vd.id DESC LIMIT 100"
        );
        $st->execute([$productoId]);
        $producto['ventas'] = $st->fetchAll();

        $st = $this->db->prepare(
            "SELECT r.*, vd.cantidad, vd.precio_unitario, b.saldo AS bono_saldo FROM tienda_reversiones r
             JOIN tienda_venta_detalles vd ON vd.id = r.venta_detalle_id
             LEFT JOIN tienda_bonos b ON b.origen_reversion_id = r.id
             WHERE vd.producto_id = ? ORDER BY r.id DESC LIMIT 100"
        );
        $st->execute([$productoId]);
        $producto['reversiones'] = $st->fetchAll();

        return $producto;
    }

    /** Series disponibles de un producto (modal "IMEIs" / POS). */
    public function listarSeriesDisponibles(int $productoId, string $q = ''): array
    {
        $sql = "SELECT id, numero_serie, precio_compra FROM tienda_stock
                WHERE producto_id = ? AND estado = 'disponible' AND numero_serie IS NOT NULL";
        $params = [$productoId];
        if ($q !== '') {
            $sql .= " AND numero_serie LIKE ?";
            $params[] = '%' . $q . '%';
        }
        $sql .= " ORDER BY id DESC LIMIT 200";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    /** Catálogo para el POS: productos con stock disponible (con precio sugerido de última compra). */
    public function catalogoVenta(string $q = '', int $categoriaId = 0, int $marcaId = 0): array
    {
        $where = ["p.estado_activo = 1", "COALESCE(st.disponible, 0) > 0"];
        $params = [];
        if ($q !== '') {
            $where[] = "(p.nombre LIKE ? OR c.nombre LIKE ? OR m.nombre LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($categoriaId > 0) { $where[] = "p.categoria_id = ?"; $params[] = $categoriaId; }
        if ($marcaId > 0) { $where[] = "p.marca_id = ?"; $params[] = $marcaId; }
        $w = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT p.id, p.nombre, p.foto_path, c.nombre AS categoria_nombre, m.nombre AS marca_nombre,
                       COALESCE((SELECT cd.precio_venta_sugerido FROM tienda_compra_detalles cd
                                 WHERE cd.producto_id = p.id AND EXISTS (SELECT 1 FROM tienda_stock s
                                        WHERE s.compra_detalle_id = cd.id AND s.estado = 'disponible')
                                 ORDER BY cd.id DESC LIMIT 1), p.precio_venta_sugerido) AS precio_sugerido,
                       COALESCE(st.disponible, 0) AS disponible,
                       COALESCE(st.tiene_serie, 0) AS tiene_serie
                FROM tienda_productos p
                JOIN tienda_categorias c ON c.id = p.categoria_id
                JOIN tienda_marcas m ON m.id = p.marca_id
                LEFT JOIN (
                    SELECT producto_id, COUNT(*) AS disponible,
                           SUM(CASE WHEN numero_serie IS NOT NULL THEN 1 ELSE 0 END) AS tiene_serie
                    FROM tienda_stock WHERE estado = 'disponible' GROUP BY producto_id
                ) st ON st.producto_id = p.id
                $w ORDER BY p.nombre";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    // ==================================================================
    // VENTAS
    // ==================================================================

    public function listarVentas(
        string $q = '',
        string $tipoCliente = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        string $ceco = '',
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(CAST(v.id AS CHAR) LIKE ? OR v.cedula LIKE ? OR cl.nombre LIKE ? OR sa.nombre LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($tipoCliente !== '' && in_array($tipoCliente, ['asociado', 'externo'], true)) {
            $where[] = "v.tipo_cliente = ?";
            $params[] = $tipoCliente;
        }
        if ($fechaDesde !== '') { $where[] = "COALESCE(v.fecha_venta, v.fecha_creacion) >= ?"; $params[] = $fechaDesde . ' 00:00:00'; }
        if ($fechaHasta !== '') { $where[] = "COALESCE(v.fecha_venta, v.fecha_creacion) <= ?"; $params[] = $fechaHasta . ' 23:59:59'; }
        if ($ceco !== '') { $where[] = "v.ceco = ?"; $params[] = $ceco; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_ventas v
                                  LEFT JOIN tienda_clientes cl ON cl.id = v.cliente_id
                                  LEFT JOIN sifone_asociados sa ON sa.cedula = v.cedula $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_VENTAS, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
        $orderCol = $orderBy === 'fecha_venta' ? 'COALESCE(v.fecha_venta, v.fecha_creacion)' : "v.$orderBy";

        $sql = "SELECT v.*, COALESCE(sa.nombre, cl.nombre, v.cedula) AS cliente_nombre,
                       COALESCE(dv.total_items, 0) AS total_items,
                       COALESCE(dv.total_cantidad, 0) AS total_cantidad,
                       COALESCE(pg.total_pagos, 0) AS total_pagos,
                       COALESCE(rv.reversiones, 0) AS reversiones
                FROM tienda_ventas v
                LEFT JOIN tienda_clientes cl ON cl.id = v.cliente_id
                LEFT JOIN sifone_asociados sa ON sa.cedula = v.cedula
                LEFT JOIN (
                    SELECT venta_id, COUNT(*) AS total_items, COALESCE(SUM(cantidad), 0) AS total_cantidad
                    FROM tienda_venta_detalles GROUP BY venta_id
                ) dv ON dv.venta_id = v.id
                LEFT JOIN (
                    SELECT venta_id, COUNT(*) AS total_pagos
                    FROM tienda_venta_pagos GROUP BY venta_id
                ) pg ON pg.venta_id = v.id
                LEFT JOIN (
                    SELECT vd.venta_id, COUNT(*) AS reversiones
                    FROM tienda_reversiones r
                    JOIN tienda_venta_detalles vd ON vd.id = r.venta_detalle_id
                    GROUP BY vd.venta_id
                ) rv ON rv.venta_id = v.id
                $w ORDER BY $orderCol $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['reversible'] = (int) $r['reversiones'] === 0 ? 1 : 0;
        }

        return ['rows' => $rows, 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Venta completa con GANANCIA calculada por línea (Σ unitario − Σ precio_compra de sus unidades). */
    public function obtenerVenta(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM tienda_ventas WHERE id = ?");
        $st->execute([$id]);
        $venta = $st->fetch();
        if (!$venta) return null;

        if ($venta['tipo_cliente'] === 'asociado') {
            $st = $this->db->prepare("SELECT cedula, nombre, celula AS telefono FROM sifone_asociados WHERE cedula = ?");
            $st->execute([$venta['cedula']]);
            $venta['cliente_nombre'] = $st->fetch()['nombre'] ?? $venta['cedula'];
        } else {
            $st = $this->db->prepare("SELECT nombre, nit_cedula, telefono, email FROM tienda_clientes WHERE id = ?");
            $st->execute([$venta['cliente_id']]);
            $cli = $st->fetch();
            $venta['cliente_nombre'] = $cli['nombre'] ?? 'Cliente #' . $venta['cliente_id'];
            $venta['cliente_doc'] = $cli['nit_cedula'] ?? null;
        }

        $st = $this->db->prepare(
            "SELECT vd.*, p.nombre AS producto_nombre, c.nombre AS categoria_nombre, m.nombre AS marca_nombre,
                    COALESCE((SELECT SUM(s.precio_compra) FROM tienda_venta_detalle_stock vds
                              JOIN tienda_stock s ON s.id = vds.stock_id WHERE vds.venta_detalle_id = vd.id), 0) AS costo_total,
                    (SELECT COUNT(*) FROM tienda_reversiones r WHERE r.venta_detalle_id = vd.id) AS revertida
             FROM tienda_venta_detalles vd
             JOIN tienda_productos p ON p.id = vd.producto_id
             JOIN tienda_categorias c ON c.id = p.categoria_id
             JOIN tienda_marcas m ON m.id = p.marca_id
             WHERE vd.venta_id = ? ORDER BY vd.id"
        );
        $st->execute([$id]);
        $detalles = $st->fetchAll();

        $unidadesMap = [];
        if ($detalles) {
            $ids = array_map(static fn(array $d): int => (int) $d['id'], $detalles);
            $in = implode(',', array_fill(0, count($ids), '?'));
            $stU = $this->db->prepare(
                "SELECT vds.venta_detalle_id, s.numero_serie, s.precio_compra, s.precio_venta
                 FROM tienda_venta_detalle_stock vds
                 JOIN tienda_stock s ON s.id = vds.stock_id
                 WHERE vds.venta_detalle_id IN ($in) ORDER BY s.id"
            );
            $stU->execute($ids);
            foreach ($stU->fetchAll() as $u) {
                $unidadesMap[(int) $u['venta_detalle_id']][] = $u;
            }
        }

        $totalCosto = 0.0;
        $totalGanancia = 0.0;
        foreach ($detalles as &$d) {
            $d['costo_total'] = (float) $d['costo_total'];
            $d['revertida'] = (int) $d['revertida'];
            $d['ganancia'] = (int) $d['revertida'] === 1 ? 0.0 : (float) $d['subtotal'] - $d['costo_total'];
            if ((int) $d['revertida'] === 1) {
                $d['costo_total'] = 0.0;
            } else {
                $totalCosto += $d['costo_total'];
                $totalGanancia += $d['ganancia'];
            }
            $d['unidades'] = $unidadesMap[(int) $d['id']] ?? [];
            $d['series'] = array_values(array_filter(array_map(static fn($u) => $u['numero_serie'], $d['unidades'])));
        }
        unset($d);
        $venta['detalles'] = $detalles;
        $venta['costo_total'] = $totalCosto;
        $venta['ganancia_total'] = $totalGanancia;

        $st = $this->db->prepare(
            "SELECT pg.*, b.saldo AS bono_saldo_restante FROM tienda_venta_pagos pg
             LEFT JOIN tienda_bonos b ON b.id = pg.bono_id WHERE pg.venta_id = ? ORDER BY pg.id"
        );
        $st->execute([$id]);
        $venta['pagos'] = $st->fetchAll();

        return $venta;
    }

    /** Crea una venta consumiendo stock físico (multi-unidad) y registrando pagos. Transaccional. */
    public function guardarVenta(array $data, ?array $actor = null): array
    {
        $tipoCliente = ($data['tipo_cliente'] ?? 'externo') === 'asociado' ? 'asociado' : 'externo';
        $cedula = null;
        $clienteId = null;
        if ($tipoCliente === 'asociado') {
            $cedula = trim((string) ($data['cedula'] ?? ''));
            if ($cedula === '') throw new DomainException('Para una venta a asociado la cédula es requerida.');
            $st = $this->db->prepare("SELECT COUNT(*) FROM sifone_asociados WHERE cedula = ?");
            $st->execute([$cedula]);
            if ((int) $st->fetchColumn() === 0) throw new DomainException('La cédula del asociado no existe (Sifone).');
        } else {
            $clienteId = (int) ($data['cliente_id'] ?? 0);
            $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_clientes WHERE id = ? AND tipo = 'externo' AND estado_activo = 1");
            $st->execute([$clienteId]);
            if ($clienteId <= 0 || (int) $st->fetchColumn() === 0) {
                throw new DomainException('Debe seleccionar un cliente externo válido.');
            }
        }
        $ceco = trim((string) ($data['ceco'] ?? ''));
        if ($ceco === '') throw new DomainException('El centro de costo (CECO) es requerido.');
        $fechaVenta = $this->_validarFechaHoraOpcional($data['fecha_venta'] ?? null);

        $items = $data['items'] ?? [];
        if (!is_array($items) || count($items) === 0) throw new DomainException('Debe agregar al menos un producto a la venta.');

        $normal = [];
        $total = 0.0;
        foreach ($items as $it) {
            if (!is_array($it)) throw new DomainException('Ítems inválidos.');
            $productoId = (int) ($it['producto_id'] ?? 0);
            $cantidad = (int) ($it['cantidad'] ?? 0);
            $precioUnitario = (float) ($it['precio_unitario'] ?? 0);
            if ($productoId <= 0 || $cantidad < 1) throw new DomainException('Ítem inválido: producto y cantidad requeridos.');
            if ($precioUnitario <= 0) throw new DomainException('El precio unitario de cada ítem debe ser mayor a $0.');
            $imeis = $it['imeis'] ?? [];
            $imeis = is_array($imeis)
                ? array_values(array_unique(array_filter(array_map(static fn($v) => trim((string) $v), $imeis), static fn($v) => $v !== '')))
                : [];
            if ($imeis !== [] && count($imeis) !== $cantidad) {
                throw new DomainException("Cada unidad con serie debe indicar su IMEI/serial (esperados $cantidad).");
            }
            $total += $precioUnitario * $cantidad;
            $normal[] = ['producto_id' => $productoId, 'cantidad' => $cantidad, 'precio_unitario' => $precioUnitario, 'imeis' => $imeis];
        }
        $total = round($total, 2);

        $pagos = $data['pagos'] ?? [];
        if (!is_array($pagos) || count($pagos) === 0) throw new DomainException('Debe registrar al menos un medio de pago.');
        $sumPagos = 0.0;
        foreach ($pagos as $pg) {
            if (!is_array($pg)) throw new DomainException('Pagos inválidos.');
            $tipo = (string) ($pg['tipo'] ?? '');
            if (!in_array($tipo, self::TIPOS_PAGO, true)) throw new DomainException("Tipo de pago '$tipo' no permitido.");
            $monto = (float) ($pg['monto'] ?? 0);
            if ($monto < 0) throw new DomainException('El monto de un pago no puede ser negativo.');
            $sumPagos += $monto;
            if (in_array($tipo, self::TIPOS_PAGO_CON_NUMERO, true)) {
                $num = trim((string) ($pg['numero_credito_sifone'] ?? ''));
                if ($num === '' || !ctype_digit($num)) {
                    throw new DomainException("Para el pago '$tipo' el número de crédito Sifone es requerido (solo dígitos).");
                }
            }
            if ($tipo === 'bono' && (int) ($pg['bono_id'] ?? 0) <= 0) {
                throw new DomainException('Para el pago con bono debe seleccionar el bono.');
            }
        }
        if (abs($total - round($sumPagos, 2)) > 0.011) {
            throw new DomainException('La suma de los pagos debe ser igual al total de la venta.');
        }

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare(
                "INSERT INTO tienda_ventas (tipo_cliente, cedula, cliente_id, usuario_id, total, ceco, fecha_venta)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $st->execute([$tipoCliente, $cedula, $clienteId, $actor['id'] ?? null, $total, $ceco, $fechaVenta]);
            $ventaId = (int) $this->db->lastInsertId();

            $stInsDet = $this->db->prepare(
                "INSERT INTO tienda_venta_detalles (venta_id, producto_id, cantidad, precio_unitario, subtotal) VALUES (?,?,?,?,?)"
            );
            $stUpStock = $this->db->prepare(
                "UPDATE tienda_stock SET estado = 'vendido', precio_venta = ?, venta_detalle_id = ?, fecha_actualizacion = ? WHERE id = ?"
            );
            $stLink = $this->db->prepare("INSERT INTO tienda_venta_detalle_stock (venta_detalle_id, stock_id) VALUES (?,?)");

            foreach ($normal as $it) {
                $stockIds = $this->_reservarUnidades((int) $it['producto_id'], (int) $it['cantidad'], $it['imeis']);
                $subtotal = round($it['precio_unitario'] * $it['cantidad'], 2);
                $stInsDet->execute([$ventaId, $it['producto_id'], $it['cantidad'], $it['precio_unitario'], $subtotal]);
                $detId = (int) $this->db->lastInsertId();
                $now = date('Y-m-d H:i:s');
                foreach ($stockIds as $sid) {
                    $stUpStock->execute([$it['precio_unitario'], $detId, $now, $sid]);
                    $stLink->execute([$detId, $sid]);
                }
            }

            $stInsPago = $this->db->prepare(
                "INSERT INTO tienda_venta_pagos (venta_id, tipo, monto, numero_credito_sifone, bono_id) VALUES (?,?,?,?,?)"
            );
            foreach ($pagos as $pg) {
                $tipo = (string) $pg['tipo'];
                $monto = (float) $pg['monto'];
                $numero = in_array($tipo, self::TIPOS_PAGO_CON_NUMERO, true) ? (string) $pg['numero_credito_sifone'] : null;
                $bonoId = null;
                if ($tipo === 'bono') {
                    $bonoId = (int) $pg['bono_id'];
                    $st = $this->db->prepare("SELECT * FROM tienda_bonos WHERE id = ? AND estado = 'activo' FOR UPDATE");
                    $st->execute([$bonoId]);
                    $bono = $st->fetch();
                    if (!$bono) throw new DomainException('El bono seleccionado no está activo.');
                    if ((float) $bono['saldo'] < $monto) {
                        throw new DomainException('El bono no tiene saldo suficiente para ese monto.');
                    }
                    $nuevoSaldo = round((float) $bono['saldo'] - $monto, 2);
                    $nuevoEstado = $nuevoSaldo <= 0.001 ? 'usado' : 'activo';
                    $this->db->prepare("UPDATE tienda_bonos SET saldo = ?, estado = ?, fecha_actualizacion = ? WHERE id = ?")
                        ->execute([$nuevoSaldo, $nuevoEstado, date('Y-m-d H:i:s'), $bonoId]);
                }
                $stInsPago->execute([$ventaId, $tipo, $monto, $numero, $bonoId]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Logger::log('crear', 'tienda.ventas', $actor['id'] ?? null,
            "Creada venta #$ventaId ($tipoCliente, $ceco) por $total");
        return ['id' => $ventaId, 'total' => $total];
    }

    private function _validarFechaHoraOpcional(?string $f): ?string
    {
        $f = trim((string) $f);
        if ($f === '') return null;
        if (DateTime::createFromFormat('Y-m-d H:i:s', $f) === false && DateTime::createFromFormat('Y-m-d H:i', $f) === false) {
            throw new DomainException('Fecha de venta inválida (YYYY-MM-DD HH:MM[:SS]).');
        }
        return $f;
    }

    /** Reserva unidades (FIFO) de un producto; con series (IMEI/serial) verifica cada una. */
    private function _reservarUnidades(int $productoId, int $cantidad, array $imeis): array
    {
        if ($imeis !== []) {
            $ids = [];
            foreach ($imeis as $serie) {
                $st = $this->db->prepare(
                    "SELECT id FROM tienda_stock WHERE producto_id = ? AND numero_serie = ? AND estado = 'disponible' ORDER BY id LIMIT 1"
                );
                $st->execute([$productoId, $serie]);
                $sid = $st->fetchColumn();
                if (!$sid) throw new DomainException("La serie '$serie' no está disponible para la venta.");
                $ids[] = (int) $sid;
            }
            return $ids;
        }

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_stock WHERE producto_id = ? AND estado = 'disponible'");
        $st->execute([$productoId]);
        if ((int) $st->fetchColumn() < $cantidad) {
            throw new DomainException('Stock insuficiente para el producto solicitado.');
        }
        $st = $this->db->prepare(
            "SELECT id FROM tienda_stock WHERE producto_id = ? AND estado = 'disponible' ORDER BY id LIMIT ?"
        );
        $st->bindValue(1, $productoId);
        $st->bindValue(2, $cantidad, PDO::PARAM_INT);
        $st->execute();
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    // ==================================================================
    // REVERSIONES → BONOS
    // ==================================================================

    public function listarReversiones(
        string $q = '',
        string $fechaDesde = '',
        string $fechaHasta = '',
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(p.nombre LIKE ? OR CAST(v.id AS CHAR) LIKE ? OR r.motivo LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($fechaDesde !== '') { $where[] = "r.fecha_creacion >= ?"; $params[] = $fechaDesde; }
        if ($fechaHasta !== '') { $where[] = "r.fecha_creacion <= ?"; $params[] = $fechaHasta . ' 23:59:59'; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_reversiones r
                                  JOIN tienda_venta_detalles vd ON vd.id = r.venta_detalle_id
                                  JOIN tienda_ventas v ON v.id = vd.venta_id
                                  JOIN tienda_productos p ON p.id = vd.producto_id $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_REV, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT r.*, vd.producto_id, vd.cantidad, vd.precio_unitario, vd.subtotal,
                       p.nombre AS producto_nombre, v.id AS venta_id, v.tipo_cliente, v.cedula, v.cliente_id,
                       u.nombre_completo AS usuario_nombre, b.saldo AS bono_saldo
                FROM tienda_reversiones r
                JOIN tienda_venta_detalles vd ON vd.id = r.venta_detalle_id
                JOIN tienda_ventas v ON v.id = vd.venta_id
                JOIN tienda_productos p ON p.id = vd.producto_id
                LEFT JOIN tienda_bonos b ON b.origen_reversion_id = r.id
                LEFT JOIN usuarios u ON u.id = r.usuario_id
                $w ORDER BY r.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /**
     * Reversión de una línea de venta: devuelve sus unidades a 'disponible' y
     * crea un BONO (crédito) por el subtotal para el comprador. Transaccional.
     */
    public function registrarReversion(int $ventaDetalleId, string $motivo, ?array $actor = null): array
    {
        $st = $this->db->prepare(
            "SELECT vd.*, v.id AS venta_id, v.tipo_cliente, v.cedula, v.cliente_id, v.ceco
             FROM tienda_venta_detalles vd JOIN tienda_ventas v ON v.id = vd.venta_id WHERE vd.id = ?"
        );
        $st->execute([$ventaDetalleId]);
        $det = $st->fetch();
        if (!$det) throw new DomainException('Línea de venta no encontrada.');

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_reversiones WHERE venta_detalle_id = ?");
        $st->execute([$ventaDetalleId]);
        if ((int) $st->fetchColumn() > 0) throw new DomainException('Esa línea de venta ya fue revertida.');

        $motivo = trim($motivo);
        if ($motivo === '') throw new DomainException('El motivo de la reversión es requerido.');

        $st = $this->db->prepare(
            "SELECT s.id FROM tienda_venta_detalle_stock vds
             JOIN tienda_stock s ON s.id = vds.stock_id
             WHERE vds.venta_detalle_id = ? AND s.estado = 'vendido' ORDER BY s.id"
        );
        $st->execute([$ventaDetalleId]);
        $units = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        if ($units === []) throw new DomainException('No se encontraron unidades vendidas para esta línea.');

        $this->db->beginTransaction();
        try {
            $stUp = $this->db->prepare("UPDATE tienda_stock SET estado = 'disponible', venta_detalle_id = NULL, fecha_actualizacion = ? WHERE id = ?");
            $now = date('Y-m-d H:i:s');
            foreach ($units as $sid) $stUp->execute([$now, $sid]);
            // Libera el vínculo de "unidades consumidas": las unidades vuelven a
            // poder venderse; la trazabilidad histórica queda en tienda_reversiones.
            $this->db->prepare("DELETE FROM tienda_venta_detalle_stock WHERE venta_detalle_id = ?")->execute([$ventaDetalleId]);

            $st = $this->db->prepare("INSERT INTO tienda_reversiones (venta_detalle_id, motivo, usuario_id) VALUES (?,?,?)");
            $st->execute([$ventaDetalleId, $motivo, $actor['id'] ?? null]);
            $revId = (int) $this->db->lastInsertId();

            $cedula = $det['tipo_cliente'] === 'asociado' ? $det['cedula'] : null;
            $clienteId = $det['tipo_cliente'] === 'externo' ? (int) $det['cliente_id'] : null;
            $monto = (float) $det['subtotal'];
            $st = $this->db->prepare(
                "INSERT INTO tienda_bonos (cedula, cliente_id, monto_original, saldo, origen_reversion_id, estado)
                 VALUES (?,?,?,?,?,'activo')"
            );
            $st->execute([$cedula, $clienteId, $monto, $monto, $revId]);
            $bonoId = (int) $this->db->lastInsertId();

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Logger::log('revertir', 'tienda.reversiones', $actor['id'] ?? null,
            "Reversión #$revId de la línea #$ventaDetalleId (venta #{$det['venta_id']}) → bono #$bonoId por $monto",
            null, null, 'warning');
        return ['id' => $revId, 'bono_id' => $bonoId, 'monto' => $monto];
    }

    // ==================================================================
    // BONOS
    // ==================================================================

    public function listarBonos(
        string $q = '',
        string $estado = '',
        string $orderBy = 'id',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = "(b.cedula LIKE ? OR cl.nombre LIKE ? OR sa.nombre LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($estado !== '' && in_array($estado, ['activo', 'usado', 'expirado'], true)) {
            $where[] = "b.estado = ?";
            $params[] = $estado;
        }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare("SELECT COUNT(*) FROM tienda_bonos b
                                  LEFT JOIN sifone_asociados sa ON sa.cedula = b.cedula
                                  LEFT JOIN tienda_clientes cl ON cl.id = b.cliente_id $w");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS_BONOS, true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';

        $sql = "SELECT b.*, COALESCE(sa.nombre, cl.nombre) AS acreedor_nombre, r.id AS reversion_id
                FROM tienda_bonos b
                LEFT JOIN sifone_asociados sa ON sa.cedula = b.cedula
                LEFT JOIN tienda_clientes cl ON cl.id = b.cliente_id
                LEFT JOIN tienda_reversiones r ON r.id = b.origen_reversion_id
                $w ORDER BY b.$orderBy $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function obtenerBono(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM tienda_bonos WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Bonos activos con saldo, de un acreedor (para pagar ventas con tipo 'bono'). */
    public function bonosDisponiblesPara(string|null $cedula = null, int|null $clienteId = null): array
    {
        $where = ["estado = 'activo'", "saldo > 0"];
        $params = [];
        if ($cedula !== null && $cedula !== '') { $where[] = "cedula = ?"; $params[] = $cedula; }
        if ($clienteId !== null && $clienteId > 0) { $where[] = "cliente_id = ?"; $params[] = $clienteId; }
        if ($params === []) return [];
        $st = $this->db->prepare("SELECT * FROM tienda_bonos WHERE " . implode(' AND ', $where) . " ORDER BY id");
        $st->execute($params);
        return $st->fetchAll();
    }

    // ==================================================================
    // REPORTES (para Excel)
    // ==================================================================

    public function reporteInventario(): array
    {
        return $this->db->query(
            "SELECT p.id AS 'ID', p.nombre AS 'Producto', c.nombre AS 'Categoría', m.nombre AS 'Marca',
                    (SELECT COALESCE(SUM(d.cantidad),0) FROM tienda_compra_detalles d WHERE d.producto_id = p.id) AS 'Ingresado',
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = 'vendido') AS 'Vendido',
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = 'disponible') AS 'Disponible',
                    (SELECT COUNT(*) FROM tienda_stock s WHERE s.producto_id = p.id AND s.estado = 'anulado') AS 'Anulado',
                    COALESCE((SELECT cd.precio_venta_sugerido FROM tienda_compra_detalles cd WHERE cd.producto_id = p.id
                              AND EXISTS (SELECT 1 FROM tienda_stock s WHERE s.compra_detalle_id = cd.id AND s.estado = 'disponible')
                              ORDER BY cd.id DESC LIMIT 1), p.precio_venta_sugerido) AS 'Precio venta'
             FROM tienda_productos p
             JOIN tienda_categorias c ON c.id = p.categoria_id
             JOIN tienda_marcas m ON m.id = p.marca_id
             ORDER BY p.nombre"
        )->fetchAll();
    }

    public function reporteVentas(string $desde = '', string $hasta = ''): array
    {
        $where = [];
        $params = [];
        if ($desde !== '') { $where[] = "COALESCE(v.fecha_venta, v.fecha_creacion) >= ?"; $params[] = $desde . ' 00:00:00'; }
        if ($hasta !== '') { $where[] = "COALESCE(v.fecha_venta, v.fecha_creacion) <= ?"; $params[] = $hasta . ' 23:59:59'; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare(
            "SELECT v.id AS 'Venta', COALESCE(v.fecha_venta, v.fecha_creacion) AS 'Fecha', v.tipo_cliente AS 'Tipo cliente',
                    COALESCE(sa.nombre, cl.nombre, v.cedula) AS 'Cliente', v.ceco AS 'CECO', v.total AS 'Total',
                    (SELECT COALESCE(SUM(COALESCE(s.precio_compra, 0)),0)
                     FROM tienda_venta_detalles vd
                     JOIN tienda_venta_detalle_stock vds ON vds.venta_detalle_id = vd.id
                     JOIN tienda_stock s ON s.id = vds.stock_id WHERE vd.venta_id = v.id) AS 'Costo',
                    GROUP_CONCAT(DISTINCT pg.tipo ORDER BY pg.id SEPARATOR ' / ') AS 'Medios de pago'
             FROM tienda_ventas v
             LEFT JOIN tienda_clientes cl ON cl.id = v.cliente_id
             LEFT JOIN sifone_asociados sa ON sa.cedula = v.cedula
             LEFT JOIN tienda_venta_pagos pg ON pg.venta_id = v.id
             $w GROUP BY v.id ORDER BY v.id"
        );
        $st->execute($params);
        return $st->fetchAll();
    }

    public function reporteVentasDetalle(string $desde = '', string $hasta = ''): array
    {
        $where = [];
        $params = [];
        if ($desde !== '') { $where[] = "COALESCE(v.fecha_venta, v.fecha_creacion) >= ?"; $params[] = $desde . ' 00:00:00'; }
        if ($hasta !== '') { $where[] = "COALESCE(v.fecha_venta, v.fecha_creacion) <= ?"; $params[] = $hasta . ' 23:59:59'; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare(
            "SELECT v.id AS 'Venta', COALESCE(v.fecha_venta, v.fecha_creacion) AS 'Fecha', v.tipo_cliente AS 'Tipo cliente',
                    COALESCE(sa.nombre, cl.nombre, v.cedula) AS 'Cliente', v.ceco AS 'CECO',
                    p.nombre AS 'Producto', c.nombre AS 'Categoría', m.nombre AS 'Marca',
                    vd.cantidad AS 'Cantidad', vd.precio_unitario AS 'Precio unitario', vd.subtotal AS 'Subtotal',
                    COALESCE((SELECT SUM(COALESCE(s.precio_compra, 0)) FROM tienda_venta_detalle_stock vds
                              JOIN tienda_stock s ON s.id = vds.stock_id WHERE vds.venta_detalle_id = vd.id), 0) AS 'Costo línea',
                    ROUND(vd.subtotal - COALESCE((SELECT SUM(COALESCE(s.precio_compra, 0)) FROM tienda_venta_detalle_stock vds
                              JOIN tienda_stock s ON s.id = vds.stock_id WHERE vds.venta_detalle_id = vd.id), 0), 2) AS 'Ganancia línea'
             FROM tienda_venta_detalles vd
             JOIN tienda_ventas v ON v.id = vd.venta_id
             JOIN tienda_productos p ON p.id = vd.producto_id
             JOIN tienda_categorias c ON c.id = p.categoria_id
             JOIN tienda_marcas m ON m.id = p.marca_id
             LEFT JOIN tienda_clientes cl ON cl.id = v.cliente_id
             LEFT JOIN sifone_asociados sa ON sa.cedula = v.cedula
             $w ORDER BY v.id, vd.id"
        );
        $st->execute($params);
        return $st->fetchAll();
    }
}