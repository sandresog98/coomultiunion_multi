<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/transacciones/TransaccionService.php';
require_once dirname(__DIR__) . '/Storage/Storage.php';

/**
 * Inventario de boletas (B9) — stock unitario serializado de los productos
 * tipo "boleta" (convenios_productos.maneja_stock=1). Estado y eventos completos.
 * Tablas: boleteria_boletas, boleteria_eventos. Recursos: uploads/boletas/.
 *
 * Máquina de estados: disponible→vendida→contabilizada; anular/desanular/
 * deshacer_venta; eliminar solo si está anulada. TODAS las transiciones
 * registran evento (corrige bug O-10). Anular limpia el dato comercial y
 * financiero (corrige O-11).
 */
final class ProductoStockService
{
    private const ORDER_COLS = [
        'id', 'serial', 'producto', 'precio_compra', 'precio_venta', 'estado',
        'fecha_creacion', 'fecha_vendida', 'fecha_actualizacion', 'vencimiento',
    ];

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Lista de boletas con filtros y paginación (patrón Apéndice A §4.2).
     *  @param ?array $estados null = sin filtro; [] = filtro aplicado sin estados → 0 resultados. */
    public function listar(
        int $productoId = 0,
        ?array $estados = null,
        string $q = '',
        string $cedula = '',
        string $fechaCreacionDesde = '',
        string $fechaCreacionHasta = '',
        string $fechaVendidaDesde = '',
        string $fechaVendidaHasta = '',
        string $orderBy = 'fecha_creacion',
        string $orderDir = 'DESC',
        int $limit = 20,
        int $offset = 0
    ): array {
        // Filtro de estados enviado pero sin estados válidos → 0 resultados.
        if ($estados !== null && empty($estados)) {
            return ['rows' => [], 'total' => 0, 'limit' => $limit, 'offset' => $offset];
        }
        $where = [];
        $params = [];
        if ($productoId > 0) { $where[] = "b.producto_id = ?"; $params[] = $productoId; }
        if (!empty($estados)) {
            $ph = implode(',', array_fill(0, count($estados), '?'));
            $where[] = "b.estado IN ($ph)";
            foreach ($estados as $e) $params[] = $e;
        }
        if ($q !== '') {
            $where[] = "(b.serial LIKE ? OR b.numero_factura LIKE ? OR COALESCE(sa.nombre,'') LIKE ? OR cp.nombre LIKE ?)";
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($cedula !== '') { $where[] = "b.asociado_cedula LIKE ?"; $params[] = '%' . $cedula . '%'; }
        if ($fechaCreacionDesde !== '') { $where[] = "b.fecha_creacion >= ?"; $params[] = $fechaCreacionDesde . ' 00:00:00'; }
        if ($fechaCreacionHasta !== '') { $where[] = "b.fecha_creacion <= ?"; $params[] = $fechaCreacionHasta . ' 23:59:59'; }
        if ($fechaVendidaDesde !== '') { $where[] = "b.fecha_vendida >= ?"; $params[] = $fechaVendidaDesde . ' 00:00:00'; }
        if ($fechaVendidaHasta !== '') { $where[] = "b.fecha_vendida <= ?"; $params[] = $fechaVendidaHasta . ' 23:59:59'; }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM boleteria_boletas b
             LEFT JOIN convenios_productos cp ON cp.id = b.producto_id
             LEFT JOIN sifone_asociados sa ON sa.cedula = b.asociado_cedula
             $w"
        );
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $orderBy = in_array($orderBy, self::ORDER_COLS, true) ? $orderBy : 'fecha_creacion';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $col = match ($orderBy) {
            'producto' => 'cp.nombre',
            'precio_compra' => 'b.precio_compra_snapshot',
            'precio_venta' => 'b.precio_venta_snapshot',
            'vencimiento' => 'b.fecha_vencimiento',
            default => "b.$orderBy",
        };

        $sql = "SELECT b.*, cp.nombre AS producto_nombre, c.nombre AS convenio_nombre,
                       sa.nombre AS asociado_nombre,
                       u0.nombre_completo AS creado_por_nombre,
                       u1.nombre_completo AS vendido_por_nombre,
                       u2.nombre_completo AS contabilizado_por_nombre,
                       CASE
                         WHEN b.estado <> 'disponible' THEN 'VENDIDA/OTRO'
                         WHEN b.fecha_vencimiento IS NULL THEN 'NO TIENE'
                         WHEN DATEDIFF(b.fecha_vencimiento, NOW()) <= 30 THEN 'FECHA PROXIMA'
                         WHEN DATEDIFF(b.fecha_vencimiento, NOW()) <= 90 THEN 'MESES SIGUIENTES'
                         ELSE 'PENDIENTE'
                       END AS vencimiento_flag
                FROM boleteria_boletas b
                JOIN convenios_productos cp ON cp.id = b.producto_id
                JOIN convenios c ON c.id = cp.convenio_id
                LEFT JOIN sifone_asociados sa ON sa.cedula = b.asociado_cedula
                LEFT JOIN usuarios u0 ON u0.id = b.creado_por
                LEFT JOIN usuarios u1 ON u1.id = b.vendido_por
                LEFT JOIN usuarios u2 ON u2.id = b.contabilizado_por
                $w ORDER BY $col $orderDir LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();

        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    /** Productos que manejan stock (para el selector de la pantalla de stock). */
    public function productosConStock(): array
    {
        return $this->db->query(
            "SELECT p.id, p.nombre, c.nombre AS convenio_nombre, p.precio_compra, p.precio_venta
             FROM convenios_productos p
             JOIN convenios c ON c.id = p.convenio_id
             WHERE p.maneja_stock = 1 AND p.activo = 1
             ORDER BY c.nombre, p.nombre"
        )->fetchAll();
    }

    public function existeSerial(int $productoId, string $serial): bool
    {
        $st = $this->db->prepare("SELECT 1 FROM boleteria_boletas WHERE producto_id = ? AND serial = ? LIMIT 1");
        $st->execute([$productoId, trim($serial)]);
        return (bool) $st->fetchColumn();
    }

    /** Crea una boleta (disponible) con snapshots de precio y archivo opcional. */
    public function crear(array $data, ?array $actor = null, ?array $file = null): array
    {
        $productoId = (int) ($data['producto_id'] ?? 0);
        $producto = $this->_productoStock($productoId);
        $serial = trim((string) ($data['serial'] ?? ''));
        $numeroFactura = trim((string) ($data['numero_factura'] ?? ''));
        if ($serial === '') throw new DomainException('El serial es requerido.');
        if ($numeroFactura === '') throw new DomainException('El número de factura es requerido.');
        if ($this->existeSerial($productoId, $serial)) {
            throw new DomainException('El serial ya existe para este producto.');
        }

        // Precios: los campos vacíos ('') caen al precio del producto (no a $0).
        $pc = isset($data['precio_compra']) ? trim((string) $data['precio_compra']) : '';
        $pv = isset($data['precio_venta']) ? trim((string) $data['precio_venta']) : '';
        $precioCompra = $pc !== '' ? (float) $pc : (float) ($producto['precio_compra'] ?? 0);
        $precioVenta = $pv !== '' ? (float) $pv : (float) ($producto['precio_venta'] ?? 0);
        if ($precioCompra < 0 || $precioVenta < 0) throw new DomainException('Los precios deben ser >= $0.');
        if ($precioVenta <= 0) throw new DomainException('El precio de venta debe ser mayor a $0.');

        $archivo = null;
        if ($file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $archivo = $this->_guardarArchivoBoleta($file, $productoId);
        }

        $fechaVencimiento = trim((string) ($data['fecha_vencimiento'] ?? ''));
        if ($fechaVencimiento !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaVencimiento)) {
            throw new DomainException('Fecha de vencimiento inválida.');
        }

        $st = $this->db->prepare(
            "INSERT INTO boleteria_boletas (producto_id, serial, numero_factura, precio_compra_snapshot,
                                            precio_venta_snapshot, archivo_ruta, fecha_vencimiento, creado_por)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $st->execute([$productoId, $serial, $numeroFactura, $precioCompra, $precioVenta,
            $archivo, $fechaVencimiento !== '' ? $fechaVencimiento : null, $actor['id'] ?? null]);
        $id = (int) $this->db->lastInsertId();

        $this->_evento($id, $actor['id'] ?? null, 'crear', null, 'disponible', 'Boleta creada');
        if ($archivo !== null) {
            $this->_evento($id, $actor['id'] ?? null, 'cargar_documento', null, 'disponible', 'Documento cargado');
        }
        Logger::log('crear', 'boletas', $actor['id'] ?? null, "Creada boleta #$id ($serial)");
        return ['id' => $id];
    }

    /**
     * Carga masiva: inserta N boletas YA validadas dentro de una transacción,
     * registrando el evento 'crear' por boleta y un resumen en logs.
     * @param array<int, array{producto_id:int, serial:string, numero_factura:string,
     *                         precio_compra:float, precio_venta:float, fecha_vencimiento:?string}> $items
     */
    public function crearBatch(array $items, ?array $actor = null): int
    {
        if (empty($items)) return 0;
        $n = 0;
        $this->db->beginTransaction();
        try {
            // Prevalidar seriales existentes en bloque (evita una consulta por boleta).
            $productIds = array_values(array_unique(array_map(static fn(array $it): int => (int) $it['producto_id'], $items)));
            $seriales = array_values(array_unique(array_map(static fn(array $it): string => trim((string) $it['serial']), $items)));
            $existentes = [];
            $inProd = implode(',', array_fill(0, count($productIds), '?'));
            foreach (array_chunk($seriales, 300) as $chunk) {
                $inSer = implode(',', array_fill(0, count($chunk), '?'));
                $stEx = $this->db->prepare(
                    "SELECT producto_id, serial FROM boleteria_boletas
                     WHERE producto_id IN ($inProd) AND serial IN ($inSer)"
                );
                $stEx->execute(array_merge($productIds, $chunk));
                foreach ($stEx->fetchAll() as $r) {
                    $existentes[(int) $r['producto_id'] . '|' . (string) $r['serial']] = true;
                }
            }

            $st = $this->db->prepare(
                "INSERT INTO boleteria_boletas (producto_id, serial, numero_factura, precio_compra_snapshot,
                                                precio_venta_snapshot, fecha_vencimiento, creado_por)
                 VALUES (?,?,?,?,?,?,?)"
            );
            $eventos = [];
            foreach ($items as $it) {
                $pid = (int) $it['producto_id'];
                $serial = trim((string) $it['serial']);
                $clave = $pid . '|' . $serial;
                if (isset($existentes[$clave])) {
                    throw new DomainException("El serial $serial ya existe para el producto.");
                }
                $existentes[$clave] = true;
                $st->execute([
                    $pid, $serial,
                    trim((string) ($it['numero_factura'] ?? '')),
                    (float) $it['precio_compra'], (float) $it['precio_venta'],
                    !empty($it['fecha_vencimiento']) ? $it['fecha_vencimiento'] : null,
                    $actor['id'] ?? null,
                ]);
                $bid = (int) $this->db->lastInsertId();
                $eventos[] = [$bid, $actor['id'] ?? null, 'crear', null, 'disponible', 'Boleta creada (carga masiva)'];
                $n++;
            }

            // Eventos en lotes (mismo efecto que _evento, sin preparar por fila).
            try {
                foreach (array_chunk($eventos, 500) as $chunk) {
                    $ph = implode(',', array_fill(0, count($chunk), '(?,?,?,?,?,?)'));
                    $params = [];
                    foreach ($chunk as $e) { array_push($params, ...$e); }
                    $this->db->prepare(
                        "INSERT INTO boleteria_eventos (boleta_id, usuario_id, accion, estado_anterior, estado_nuevo, detalle)
                         VALUES $ph"
                    )->execute($params);
                }
            } catch (Throwable $e) {
                error_log('[boleteria_eventos] ' . $e->getMessage());
            }

            $this->db->commit();
            Logger::log('crear', 'boletas', $actor['id'] ?? null, "Carga masiva: $n boletas creadas", null, null, 'info');
            return $n;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Vende una boleta disponible. Opcionalmente vincula el pago:
     *  - financiero['tipo']='sifone'  → valida numero+cedula+valor contra
     *    sifone_movimientos_tributarios y persiste sifone_numero.
     *  - financiero['tipo']='transaccion' → crea transacción (origen='boleta')
     *    y persiste transaccion_id.
     */
    public function vender(int $id, string $cedula, string $metodoVenta, ?array $financiero = null, ?array $actor = null): array
    {
        $boleta = $this->_obtener($id);
        if ($boleta['estado'] !== 'disponible') throw new DomainException('Solo se pueden vender boletas disponibles.');
        $cedula = trim($cedula);
        if ($cedula === '') throw new DomainException('La cédula del comprador es requerida.');
        $metodo = in_array($metodoVenta, ['credito', 'regalo_cooperativa'], true) ? $metodoVenta : 'credito';

        $this->db->beginTransaction();
        try {
            $sifone = null;
            $transaccion = null;
            $financiero = is_array($financiero) ? $financiero : null;
            if ($financiero !== null) {
                $tipo = trim((string) ($financiero['tipo'] ?? ''));
                if ($tipo === 'sifone') {
                    $numero = trim((string) ($financiero['numero'] ?? ''));
                    if ($numero === '') throw new DomainException('Debe indicar el nº de movimiento Sifone.');
                    if (!$this->_validarSifone($numero, $cedula, (float) $boleta['precio_venta_snapshot'])) {
                        throw new DomainException('El nº de movimiento Sifone no corresponde a la cédula y valor de la venta.');
                    }
                    $st = $this->db->prepare("SELECT COUNT(*) FROM boleteria_boletas WHERE sifone_numero = ? AND sifone_numero IS NOT NULL AND id <> ?");
                    $st->execute([$numero, $id]);
                    if ((int) $st->fetchColumn() > 0) {
                        throw new DomainException('El nº de movimiento Sifone ya está vinculado a otra boleta.');
                    }
                    $sifone = $numero;
                } elseif ($tipo === 'transaccion') {
                    $detalle = 'Boleta ' . $boleta['serial'] . ' – ' . $boleta['producto_nombre'];
                    $valor = (float) $boleta['precio_venta_snapshot'];
                    $transaccion = (new TransaccionService($this->db))->crear([
                        'origen' => 'boleta',
                        'cedula' => $cedula,
                        'valor_total' => $valor,
                        'recibo_caja' => 'BOLETA-' . $boleta['serial'],
                        'detalles' => [[
                            'tipo_rubro' => 'boleta',
                            'descripcion' => $detalle,
                            'valor_recomendado' => $valor,
                            'valor_asignado' => $valor,
                        ]],
                    ], $actor, true);
                }
            }

            $this->db->prepare(
                "UPDATE boleteria_boletas SET estado = 'vendida', asociado_cedula = ?, metodo_venta = ?,
                        fecha_vendida = CURRENT_TIMESTAMP, fecha_actualizacion = CURRENT_TIMESTAMP,
                        vendido_por = ?, sifone_numero = ?, transaccion_id = ?
                 WHERE id = ? AND estado = 'disponible'"
            )->execute([$cedula, $metodo, $actor['id'] ?? null, $sifone, $transaccion, $id]);

            $det = "Boleta vendida a $cedula por método $metodo";
            if ($sifone !== null) $det .= " (Sifone $sifone)";
            elseif ($transaccion !== null) $det .= " (Transacción #$transaccion)";
            $this->_evento($id, $actor['id'] ?? null, 'vender', 'disponible', 'vendida', $det);

            $this->db->commit();
            Logger::log('editar', 'boletas', $actor['id'] ?? null, "Vendida boleta #$id ($cedula, $metodo)");
            return ['id' => $id, 'sifone_numero' => $sifone, 'transaccion_id' => $transaccion];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function contabilizar(int $id, string $comprobante, ?array $actor = null): void
    {
        $boleta = $this->_obtener($id);
        if ($boleta['estado'] !== 'vendida') throw new DomainException('Solo se pueden contabilizar boletas vendidas.');
        $comprobante = trim($comprobante);
        if ($comprobante === '') throw new DomainException('El comprobante es requerido.');

        $this->db->prepare(
            "UPDATE boleteria_boletas SET estado = 'contabilizada', comprobante = ?,
                    fecha_contabilizacion = CURRENT_TIMESTAMP, fecha_actualizacion = CURRENT_TIMESTAMP,
                    contabilizado_por = ?
             WHERE id = ? AND estado = 'vendida'"
        )->execute([$comprobante, $actor['id'] ?? null, $id]);
        $this->_evento($id, $actor['id'] ?? null, 'contabilizar', 'vendida', 'contabilizada', 'Contabilizada con comprobante: ' . $comprobante);
        Logger::log('editar', 'boletas', $actor['id'] ?? null, "Contabilizada boleta #$id ($comprobante)");
    }

    /** Anula desde CUALQUIER estado ≠ anulada. Limpia todo el dato comercial y financiero (O-11). */
    public function anular(int $id, ?array $actor = null): void
    {
        $boleta = $this->_obtener($id);
        if ($boleta['estado'] === 'anulada') throw new DomainException('La boleta ya está anulada.');

        $this->db->prepare(
            "UPDATE boleteria_boletas SET estado = 'anulada',
                    asociado_cedula = NULL, metodo_venta = NULL, fecha_vendida = NULL, vendido_por = NULL,
                    comprobante = NULL, fecha_contabilizacion = NULL, contabilizado_por = NULL,
                    sifone_numero = NULL, transaccion_id = NULL,
                    fecha_actualizacion = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$id]);
        $this->_evento($id, $actor['id'] ?? null, 'anular', $boleta['estado'], 'anulada', 'Boleta anulada');
        Logger::log('editar', 'boletas', $actor['id'] ?? null, "Anulada boleta #$id", null, null, 'warning');
    }

    /** Desanula (anulada → disponible). */
    public function desanular(int $id, ?array $actor = null): void
    {
        $boleta = $this->_obtener($id);
        if ($boleta['estado'] !== 'anulada') throw new DomainException('Solo se pueden desanular boletas anuladas.');

        $this->db->prepare(
            "UPDATE boleteria_boletas SET estado = 'disponible', fecha_actualizacion = CURRENT_TIMESTAMP WHERE id = ?"
        )->execute([$id]);
        $this->_evento($id, $actor['id'] ?? null, 'desanular', 'anulada', 'disponible', 'Boleta desanulada');
        Logger::log('editar', 'boletas', $actor['id'] ?? null, "Desanulada boleta #$id", null, null, 'warning');
    }

    /** Deshace una venta (vendida → disponible) limpiando el vínculo comercial/financiero. */
    public function deshacerVenta(int $id, ?array $actor = null): void
    {
        $boleta = $this->_obtener($id);
        if ($boleta['estado'] !== 'vendida') throw new DomainException('Solo se puede deshacer venta de boletas vendidas.');

        $this->db->prepare(
            "UPDATE boleteria_boletas SET estado = 'disponible',
                    asociado_cedula = NULL, metodo_venta = NULL, fecha_vendida = NULL, vendido_por = NULL,
                    sifone_numero = NULL, transaccion_id = NULL,
                    fecha_actualizacion = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$id]);
        $this->_evento($id, $actor['id'] ?? null, 'deshacer_venta', 'vendida', 'disponible', 'Venta deshecha');
        Logger::log('editar', 'boletas', $actor['id'] ?? null, "Deshecha venta de boleta #$id", null, null, 'warning');
    }

    /** Elimina física SOLO boletas anuladas (fila + eventos + archivo). */
    public function eliminar(int $id, ?array $actor = null): void
    {
        $boleta = $this->_obtener($id);
        if ($boleta['estado'] !== 'anulada') throw new DomainException('Solo se pueden eliminar boletas anuladas.');

        if (!empty($boleta['archivo_ruta'])) {
            Storage::for('boletas')->delete('boletas/' . basename((string) $boleta['archivo_ruta']));
        }
        $this->db->prepare("DELETE FROM boleteria_eventos WHERE boleta_id = ?")->execute([$id]);
        $this->db->prepare("DELETE FROM boleteria_boletas WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'boletas', $actor['id'] ?? null, "Eliminada boleta #$id", null, null, 'warning');
    }

    /** Timeline de eventos de una boleta. */
    public function eventos(int $boletaId): array
    {
        $st = $this->db->prepare(
            "SELECT e.*, u.nombre_completo AS usuario_nombre
             FROM boleteria_eventos e
             LEFT JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.boleta_id = ? ORDER BY e.fecha DESC, e.id DESC"
        );
        $st->execute([$boletaId]);
        return $st->fetchAll();
    }

    /** Boleta individual (incluye convenio/producto). */
    public function obtener(int $id): array
    {
        return $this->_obtener($id);
    }

    /** Registra la visualización/descarga del documento en el timeline. */
    public function registrarEventoDescarga(int $boletaId, int $usuarioId, string $tipo = 'view'): void
    {
        $tipoTexto = $tipo === 'download' ? 'Descargó el documento' : 'Vio el documento';
        $this->_evento($boletaId, $usuarioId, 'descargar', null, null, $tipoTexto);
    }

    /** KPIs de inventario por producto (o global si productoId=0). */
    public function resumenKpis(int $productoId = 0): array
    {
        $and = $productoId > 0 ? ' WHERE producto_id = ' . (int) $productoId : '';
        $kpis = $this->db->query(
            "SELECT COUNT(*) AS creadas,
                    COALESCE(SUM(estado='disponible'),0) AS disponibles,
                    COALESCE(SUM(estado='vendida'),0) AS vendidas,
                    COALESCE(SUM(estado='contabilizada'),0) AS contabilizadas,
                    COALESCE(SUM(estado='anulada'),0) AS anuladas,
                    COALESCE(SUM(IF(estado IN ('vendida','contabilizada'), precio_venta_snapshot - precio_compra_snapshot, 0)),0) AS ganancia
             FROM boleteria_boletas$and"
        )->fetch();

        $top = function (string $extra) use ($productoId) {
            $sql = "SELECT b.asociado_cedula AS cedula, COALESCE(sa.nombre, b.asociado_cedula) AS nombre, COUNT(*) AS cantidad
                    FROM boleteria_boletas b
                    LEFT JOIN sifone_asociados sa ON sa.cedula = b.asociado_cedula
                    WHERE b.estado IN ('vendida','contabilizada') AND b.asociado_cedula IS NOT NULL
                    $extra
                    GROUP BY b.asociado_cedula, sa.nombre ORDER BY cantidad DESC LIMIT 10";
            return $this->db->query($sql)->fetchAll();
        };
        $prodAnd = $productoId > 0 ? " AND b.producto_id = " . (int) $productoId : '';

        return [
            'kpis' => $kpis,
            'top_1y' => $top("AND b.fecha_vendida >= DATE_SUB(NOW(), INTERVAL 365 DAY)$prodAnd"),
            'top_all' => $top($prodAnd),
        ];
    }

    /** Datos para el reporte Excel (boletas completas o vendidas/contabilizadas). */
    public function reporteBoletas(string $accion): array
    {
        if (!in_array($accion, ['boletas', 'vendidas'], true)) {
            throw new DomainException('Acción inválida.');
        }
        $estadoTexto = ['disponible' => 'Disponible', 'vendida' => 'Vendida', 'contabilizada' => 'Contabilizada', 'anulada' => 'Anulada'];
        $metodoTexto = ['credito' => 'Crédito', 'regalo_cooperativa' => 'Regalo Cooperativa'];

        if ($accion === 'vendidas') {
            $headers = ['ID', 'Convenio', 'Producto', 'Serial', 'Nº Factura', 'Precio Compra', 'Precio Venta', 'Ganancia',
                'Estado', 'Cédula', 'Comprador', 'Método', 'Comprobante', 'Sifone', 'Transacción', 'Fecha Venta', 'Vendido Por'];
            $rows = $this->db->query(
                "SELECT b.id, c.nombre AS convenio, cp.nombre AS producto, b.serial, b.numero_factura,
                        b.precio_compra_snapshot, b.precio_venta_snapshot,
                        (b.precio_venta_snapshot - b.precio_compra_snapshot) AS ganancia,
                        b.estado, b.asociado_cedula, sa.nombre AS asociado_nombre, b.metodo_venta,
                        b.comprobante, b.sifone_numero, b.transaccion_id, b.fecha_vendida,
                        u1.nombre_completo AS vendido_por_nombre
                 FROM boleteria_boletas b
                 JOIN convenios_productos cp ON cp.id = b.producto_id
                 JOIN convenios c ON c.id = cp.convenio_id
                 LEFT JOIN sifone_asociados sa ON sa.cedula = b.asociado_cedula
                 LEFT JOIN usuarios u1 ON u1.id = b.vendido_por
                 WHERE b.estado IN ('vendida','contabilizada') ORDER BY b.fecha_vendida DESC"
            )->fetchAll();
            $data = [$headers];
            foreach ($rows as $r) {
                $data[] = [$r['id'], $r['convenio'], $r['producto'], $r['serial'], $r['numero_factura'],
                    (float) $r['precio_compra_snapshot'], (float) $r['precio_venta_snapshot'], (float) $r['ganancia'],
                    $estadoTexto[$r['estado']] ?? $r['estado'], $r['asociado_cedula'], $r['asociado_nombre'],
                    $metodoTexto[$r['metodo_venta']] ?? '', $r['comprobante'], $r['sifone_numero'],
                    $r['transaccion_id'], $r['fecha_vendida'], $r['vendido_por_nombre']];
            }
            return ['data' => $data, 'filename' => 'boletas_vendidas_' . date('Y-m-d_H-i-s') . '.xlsx',
                'sheet' => 'Boletas Vendidas', 'widths' => [8, 20, 26, 18, 12, 14, 14, 12, 14, 14, 24, 18, 15, 14, 12, 18, 20]];
        }

        $headers = ['ID', 'Convenio', 'Producto', 'Serial', 'Nº Factura', 'Precio Compra', 'Precio Venta',
            'Estado', 'Cédula', 'Comprador', 'Método', 'Comprobante', 'Sifone', 'Transacción',
            'Fecha Creación', 'Fecha Venta', 'Fecha Contabilización', 'Fecha Vencimiento', 'Creado Por', 'Vendido Por', 'Contabilizado Por'];
        $rows = $this->db->query(
            "SELECT b.id, c.nombre AS convenio, cp.nombre AS producto, b.serial, b.numero_factura,
                    b.precio_compra_snapshot, b.precio_venta_snapshot, b.estado, b.asociado_cedula,
                    sa.nombre AS asociado_nombre, b.metodo_venta, b.comprobante, b.sifone_numero,
                    b.transaccion_id, b.fecha_creacion, b.fecha_vendida, b.fecha_contabilizacion,
                    b.fecha_vencimiento, u0.nombre_completo AS creado, u1.nombre_completo AS vendido,
                    u2.nombre_completo AS contabilizado
             FROM boleteria_boletas b
             JOIN convenios_productos cp ON cp.id = b.producto_id
             JOIN convenios c ON c.id = cp.convenio_id
             LEFT JOIN sifone_asociados sa ON sa.cedula = b.asociado_cedula
             LEFT JOIN usuarios u0 ON u0.id = b.creado_por
             LEFT JOIN usuarios u1 ON u1.id = b.vendido_por
             LEFT JOIN usuarios u2 ON u2.id = b.contabilizado_por
             ORDER BY b.id DESC"
        )->fetchAll();
        $data = [$headers];
        foreach ($rows as $r) {
            $data[] = [$r['id'], $r['convenio'], $r['producto'], $r['serial'], $r['numero_factura'],
                (float) $r['precio_compra_snapshot'], (float) $r['precio_venta_snapshot'],
                $estadoTexto[$r['estado']] ?? $r['estado'], $r['asociado_cedula'], $r['asociado_nombre'],
                $metodoTexto[$r['metodo_venta']] ?? '', $r['comprobante'], $r['sifone_numero'], $r['transaccion_id'],
                $r['fecha_creacion'], $r['fecha_vendida'], $r['fecha_contabilizacion'], $r['fecha_vencimiento'],
                $r['creado'], $r['vendido'], $r['contabilizado']];
        }
        return ['data' => $data, 'filename' => 'boletas_todas_' . date('Y-m-d_H-i-s') . '.xlsx',
            'sheet' => 'Boletas', 'widths' => [8, 20, 26, 18, 12, 14, 14, 14, 14, 24, 18, 15, 14, 12, 18, 18, 18, 14, 20, 20, 22]];
    }

    public function uploadsBoletasDir(): string
    {
        $base = getenv('UPLOAD_DIR') ?: (dirname(__DIR__, 2) . '/uploads');
        if ($base[0] !== '/') $base = dirname(__DIR__, 2) . '/' . $base;
        $dir = rtrim($base, '/') . '/boletas';
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @chmod($dir, 0777);
        return $dir;
    }

    private function _productoStock(int $productoId): array
    {
        $st = $this->db->prepare(
            "SELECT p.*, c.nombre AS convenio_nombre FROM convenios_productos p
             JOIN convenios c ON c.id = p.convenio_id WHERE p.id = ? AND p.maneja_stock = 1"
        );
        $st->execute([$productoId]);
        $p = $st->fetch();
        if (!$p) throw new DomainException('El producto no existe o no maneja stock.');
        return $p;
    }

    private function _obtener(int $id): array
    {
        $st = $this->db->prepare(
            "SELECT b.*, cp.nombre AS producto_nombre, cp.maneja_stock, c.nombre AS convenio_nombre
             FROM boleteria_boletas b
             JOIN convenios_productos cp ON cp.id = b.producto_id
             JOIN convenios c ON c.id = cp.convenio_id
             WHERE b.id = ?"
        );
        $st->execute([$id]);
        $row = $st->fetch();
        if (!$row) throw new DomainException('Boleta no encontrada.');
        return $row;
    }

    private function _evento(int $boletaId, string|int|null $usuarioId, string $accion, ?string $ant, ?string $nuevo, ?string $detalle = null): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO boleteria_eventos (boleta_id, usuario_id, accion, estado_anterior, estado_nuevo, detalle)
                 VALUES (?,?,?,?,?,?)"
            )->execute([$boletaId, $usuarioId, $accion, $ant, $nuevo, $detalle]);
        } catch (Throwable $e) {
            error_log('[boleteria_eventos] ' . $e->getMessage());
        }
    }

    /** Match exacto contra sifone_movimientos_tributarios por numero+cedula+valor. */
    private function _validarSifone(string $numero, string $cedula, float $valor): bool
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM sifone_movimientos_tributarios
             WHERE numero = ? AND cedula = ? AND (debito = ? OR credit = ?)"
        );
        $st->execute([$numero, $cedula, $valor, $valor]);
        return (int) $st->fetchColumn() > 0;
    }

    private function _guardarArchivoBoleta(array $file, int $productoId): string
    {
        if (($file['error'] ?? 1) !== UPLOAD_ERR_OK) throw new DomainException('No se pudo subir el archivo.');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'], true)) {
            throw new DomainException('Formato no permitido (jpg/jpeg/png/pdf).');
        }
        $maxMb = (float) (getenv('UPLOAD_MAX_PDF_MB') ?: '10');
        if ($file['size'] > $maxMb * 1024 * 1024) throw new DomainException("El archivo supera el límite de {$maxMb} MB.");
        $clave = substr(hash('sha256', (string) microtime(true)), 0, 16);
        $nombre = Storage::nombreOpaco('boleta', (string) $productoId, $clave, $ext);
        Storage::for('boletas')->put('boletas/' . $nombre, $file['tmp_name'], ['mime' => $file['type'] ?? 'application/octet-stream']);
        return $nombre;
    }
}