<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Acceso a datos del sistema de solicitudes (cabecera + detalle tipado + eventos + comentarios).
 * Todo con sentencias preparadas.
 */
final class SolicitudRepository
{
    /** Whitelist de columnas de orden permitidas (evita inyección). */
    private const ORDER_COLS = [
        'id', 'numero_radicado', 'cedula_solicitante', 'tipo_codigo', 'estado_codigo',
        'responsable_id', 'fecha_creacion', 'fecha_respuesta',
    ];

    public function __construct(private PDO $db) {}

    /** Config de un tipo (o null). */
    public function tipo(string $codigo): ?array
    {
        $st = $this->db->prepare("SELECT * FROM solicitudes_tipos WHERE codigo = ? AND activo = 1");
        $st->execute([$codigo]);
        return $st->fetch() ?: null;
    }

    public function cabecera(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM solicitudes WHERE id = ?");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    /** Inserta la cabecera y asigna el radicado definitivo. Devuelve el id. */
    public function crearCabecera(array $c): int
    {
        $tmp = 'TMP-' . uniqid();
        $st = $this->db->prepare(
            "INSERT INTO solicitudes
               (numero_radicado, tipo_codigo, fase, estado_codigo, cedula_solicitante,
                solicitante_tipo, origen, creado_por, origen_tabla, origen_id, datos_legacy)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $tmp, $c['tipo_codigo'], $c['fase'], $c['estado_codigo'], $c['cedula_solicitante'],
            $c['solicitante_tipo'] ?? 'asociado', $c['origen'] ?? 'portal', $c['creado_por'] ?? null,
            $c['origen_tabla'] ?? null, $c['origen_id'] ?? null, $c['datos_legacy'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();

        $radicado = $c['prefijo'] . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $this->db->prepare("UPDATE solicitudes SET numero_radicado = ? WHERE id = ?")
                 ->execute([$radicado, $id]);
        return $id;
    }

    /**
     * Mapa de columnas permitidas por tabla de detalle (familia CX, Fase 1).
     * Evita columnas arbitrarias: solo se escriben las conocidas.
     */
private const DETALLE_COLS = [
        'solicitudes_det_pqrs'                => ['tipo_pqrs', 'resumen', 'descripcion'],
        'solicitudes_det_actualizacion'       => ['nombre', 'celular', 'email', 'direccion', 'ciudad', 'observaciones'],
        'solicitudes_det_redencion'           => ['val_bolsillos', 'val_incentivos', 'val_comisiones', 'total_redimir', 'medio_redencion', 'comentarios'],
        'solicitudes_det_nuevo_prod'          => ['producto_id', 'dia_pago', 'valor'],
        'solicitudes_det_ticket'              => ['categoria_id', 'resumen', 'descripcion', 'fecha_vencimiento'],
        'solicitudes_det_boleta'              => ['motivo', 'direccion_entrega'],
        'solicitudes_det_retiro_incentivos'   => ['val_incentivos', 'val_comisiones', 'total_retiro', 'medio'],
        'solicitudes_det_certificado'         => ['especie', 'anio'],
        'solicitudes_det_fondo'               => ['convenio_id', 'opcion_elegida', 'ruta_pdf_obligatorio', 'observaciones', 'fecha_uso', 'bloqueado_hasta'],
        'solicitudes_det_retiro_aporte'       => ['tipo', 'monto_solicitado', 'ruta_pdf_retiro', 'ruta_pdf_certificado', 'fecha_estimada_desembolso'],
    ];

    public function insertarDetalle(string $tabla, int $solicitudId, array $data): void
    {
        if (!isset(self::DETALLE_COLS[$tabla])) {
            throw new RuntimeException("Tabla de detalle no soportada: $tabla");
        }
        $cols = ['solicitud_id'];
        $ph   = ['?'];
        $vals = [$solicitudId];
        foreach (self::DETALLE_COLS[$tabla] as $col) {
            if (array_key_exists($col, $data)) {
                $cols[] = $col;
                $ph[]   = '?';
                $vals[] = $data[$col];
            }
        }
        $sql = "INSERT INTO `$tabla` (" . implode(',', $cols) . ") VALUES (" . implode(',', $ph) . ")";
        $this->db->prepare($sql)->execute($vals);
    }

    public function detalle(string $tabla, int $solicitudId): ?array
    {
        if (!isset(self::DETALLE_COLS[$tabla])) {
            return null;
        }
        $st = $this->db->prepare("SELECT * FROM `$tabla` WHERE solicitud_id = ?");
        $st->execute([$solicitudId]);
        return $st->fetch() ?: null;
    }

    public function evento(int $solicitudId, array $e): int
    {
        $st = $this->db->prepare(
            "INSERT INTO solicitudes_eventos
               (solicitud_id, tipo_evento, estado_anterior, estado_nuevo,
                responsable_anterior_id, responsable_nuevo_id, actor_tipo, actor_id, mensaje)
             VALUES (?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $solicitudId, $e['tipo_evento'], $e['estado_anterior'] ?? null, $e['estado_nuevo'] ?? null,
            $e['responsable_anterior_id'] ?? null, $e['responsable_nuevo_id'] ?? null,
            $e['actor_tipo'], $e['actor_id'] ?? null, $e['mensaje'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function eventos(int $solicitudId): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM solicitudes_eventos WHERE solicitud_id = ? ORDER BY fecha ASC, id ASC"
        );
        $st->execute([$solicitudId]);
        return $st->fetchAll();
    }

    public function comentario(int $solicitudId, string $tipo, ?string $autorId, string $mensaje, ?string $ruta): int
    {
        $st = $this->db->prepare(
            "INSERT INTO solicitudes_comentarios (solicitud_id, tipo, autor_id, mensaje, ruta_archivo)
             VALUES (?,?,?,?,?)"
        );
        $st->execute([$solicitudId, $tipo, $autorId, $mensaje, $ruta]);
        return (int) $this->db->lastInsertId();
    }

    public function comentarios(int $solicitudId): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM solicitudes_comentarios WHERE solicitud_id = ? ORDER BY fecha_creacion ASC, id ASC"
        );
        $st->execute([$solicitudId]);
        return $st->fetchAll();
    }

    /**
     * Listado con filtros. Devuelve `{rows, total, limit, offset}` con nombres resueltos
     * (tipo, estado, solicitante, responsable). Filtros: tipo, estado, fase, cédula,
     * responsable, radicado (LIKE), q (búsqueda por radicado/cédula/nombre).
     */
    public function listar(array $f, int $limit = 50, int $offset = 0): array
    {
        $w = [];
        $p = [];
        if (!empty($f['tipo']))        { $w[] = 's.tipo_codigo = ?';        $p[] = $f['tipo']; }
        if (!empty($f['estado']))      { $w[] = 's.estado_codigo = ?';      $p[] = $f['estado']; }
        if (!empty($f['fase']))        { $w[] = 's.fase = ?';               $p[] = $f['fase']; }
        if (!empty($f['cedula']))      { $w[] = 's.cedula_solicitante = ?'; $p[] = $f['cedula']; }
        if (!empty($f['responsable'])) { $w[] = 's.responsable_id = ?';     $p[] = (int) $f['responsable']; }
        if (!empty($f['radicado'])) {
            $w[] = 's.numero_radicado LIKE ?';
            $p[] = '%' . $f['radicado'] . '%';
        }
        if (!empty($f['q'])) {
            $like = '%' . $f['q'] . '%';
            $w[] = '(s.numero_radicado LIKE ? OR s.cedula_solicitante LIKE ? OR COALESCE(sa.nombre,\'\') LIKE ?)';
            $p = array_merge($p, [$like, $like, $like]);
        }
        $wc = $w ? ' WHERE ' . implode(' AND ', $w) : '';

        $pedido = (string) ($f['order'] ?? 'fecha_creacion');
        $orderBy = in_array($pedido, self::ORDER_COLS, true) ? $pedido : 'fecha_creacion';
        $orderDir = strtoupper((string) ($f['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM solicitudes s
               LEFT JOIN sifone_asociados sa ON sa.cedula = s.cedula_solicitante
             $wc"
        );
        $st->execute($p);
        $total = (int) $st->fetchColumn();

        $sql = "SELECT s.id, s.numero_radicado, s.tipo_codigo, t.nombre AS tipo_nombre,
                       s.fase, s.estado_codigo, e.nombre AS estado_nombre,
                       s.cedula_solicitante, sa.nombre AS solicitante_nombre,
                       s.solicitante_tipo, s.responsable_id,
                       u.nombre_completo AS responsable_nombre,
                       s.resultado, s.fecha_creacion, s.fecha_respuesta
                FROM solicitudes s
                LEFT JOIN solicitudes_tipos t ON t.codigo = s.tipo_codigo
                LEFT JOIN solicitudes_estados e ON e.tipo_codigo = s.tipo_codigo AND e.codigo = s.estado_codigo
                LEFT JOIN sifone_asociados sa ON sa.cedula = s.cedula_solicitante
                LEFT JOIN usuarios u ON u.id = s.responsable_id
                $wc
                ORDER BY `$orderBy` $orderDir, s.id DESC
                LIMIT " . (int) $limit . " OFFSET " . (int) $offset;
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function actualizarCabecera(int $id, array $campos): void
    {
        if (!$campos) {
            return;
        }
        $set = [];
        $vals = [];
        foreach ($campos as $col => $val) {
            $set[] = "`$col` = ?";
            $vals[] = $val;
        }
        $vals[] = $id;
        $this->db->prepare('UPDATE solicitudes SET ' . implode(',', $set) . ' WHERE id = ?')
                 ->execute($vals);
    }

    public function pdo(): PDO
    {
        return $this->db;
    }

    /** Detalle con items del carrito de boletas (para detalle/servicio). */
    public function detalleBoleta(int $solicitudId): array
    {
        return [
            'detalle' => $this->detalle('solicitudes_det_boleta', $solicitudId),
            'items'   => $this->itemsBoleta($solicitudId),
            'boletas' => $this->boletasDeSolicitud($solicitudId),
        ];
    }

    public function nombreConvenioFondo(int $convenioId): ?string
    {
        $st = $this->db->prepare("SELECT nombre FROM fondo_bienestar_convenios WHERE id = ?");
        $st->execute([$convenioId]);
        $n = $st->fetchColumn();
        return $n === false ? null : (string) $n;
    }

    public function insertarItemsBoleta(int $solicitudId, array $items): void
    {
        $st = $this->db->prepare(
            "INSERT INTO solicitudes_det_boleta_items (solicitud_id, producto_id, cantidad, precio_venta_snapshot)
             VALUES (?,?,?,?)"
        );
        foreach ($items as $it) {
            $st->execute([
                $solicitudId,
                (int) ($it['producto_id'] ?? 0),
                max(1, (int) ($it['cantidad'] ?? 1)),
                isset($it['precio_venta_snapshot']) ? (float) $it['precio_venta_snapshot'] : null,
            ]);
        }
    }

    public function itemsBoleta(int $solicitudId): array
    {
        $st = $this->db->prepare(
            "SELECT i.*, cp.nombre AS producto_nombre, c.nombre AS convenio_nombre
             FROM solicitudes_det_boleta_items i
             JOIN convenios_productos cp ON cp.id = i.producto_id
             JOIN convenios c ON c.id = cp.convenio_id
             WHERE i.solicitud_id = ? ORDER BY i.id ASC"
        );
        $st->execute([$solicitudId]);
        return $st->fetchAll();
    }

    /** Persiste el vínculo solicitud↔boleta al emitir/sellar (efecto 'emitir_boletas'). */
    public function persistirVinculoBoleta(int $solicitudId, array $vinculos): void
    {
        $st = $this->db->prepare(
            "INSERT INTO solicitudes_boletas (solicitud_id, boleta_id, producto_id, item_id) VALUES (?,?,?,?)"
        );
        foreach ($vinculos as $v) {
            $st->execute([$solicitudId, (int) $v['boleta_id'], $v['producto_id'] ?? null, $v['item_id'] ?? null]);
        }
    }

    public function boletasDeSolicitud(int $solicitudId): array
    {
        $st = $this->db->prepare(
            "SELECT sb.*, b.serial, b.estado, b.asociado_cedula, b.metodo_venta,
                    cp.nombre AS producto_nombre
             FROM solicitudes_boletas sb
             JOIN boleteria_boletas b ON b.id = sb.boleta_id
             LEFT JOIN convenios_productos cp ON cp.id = b.producto_id
             WHERE sb.solicitud_id = ? ORDER BY sb.id ASC"
        );
        $st->execute([$solicitudId]);
        return $st->fetchAll();
    }

    /** ¿Existe una solicitud del tipo/cedula aún no final (para autobloqueo 1-pendiente)? */
    public function tienePendiente(string $tipoCodigo, string $cedula): bool
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM solicitudes s
             JOIN solicitudes_estados e ON e.tipo_codigo = s.tipo_codigo AND e.codigo = s.estado_codigo AND e.es_final = 0
             WHERE s.tipo_codigo = ? AND s.cedula_solicitante = ?"
        );
        $st->execute([$tipoCodigo, $cedula]);
        return (int) $st->fetchColumn() > 0;
    }

    /** Tipos de activos (para permiso de los formularios). */
    public function tiposActivos(): array
    {
        return $this->db->query(
            "SELECT codigo, nombre, prefijo_radicado, detalle_tabla FROM solicitudes_tipos WHERE activo = 1 ORDER BY nombre"
        )->fetchAll();
    }

    /** Personal (usuarios) usable como responsable en solicitudes. */
    public function responsables(): array
    {
        return $this->db->query(
            "SELECT id, nombre_completo, rol FROM usuarios WHERE estado_activo = 1 ORDER BY nombre_completo"
        )->fetchAll();
    }

    /** Catálogo de productos-boleta (maneja_stock=1) activos, con disponibilidad. */
    public function productosBoleta(): array
    {
        $rows = $this->db->query(
            "SELECT p.id, p.nombre, p.precio_venta, p.precio_compra, c.nombre AS convenio_nombre,
                    (SELECT COUNT(*) FROM boleteria_boletas b WHERE b.producto_id = p.id AND b.estado = 'disponible') AS disponibles
             FROM convenios_productos p
             JOIN convenios c ON c.id = p.convenio_id
             WHERE p.maneja_stock = 1 AND p.activo = 1
             ORDER BY c.nombre, p.nombre"
        )->fetchAll();
        return array_map(static fn($r) => [
            'id' => $r['id'], 'nombre' => $r['nombre'], 'convenio' => $r['convenio_nombre'],
            'precio_venta' => (float) ($r['precio_venta'] ?? 0),
            'disponibles' => (int) $r['disponibles'],
        ], $rows);
    }

    public function estadosDe(string $tipoCodigo): array
    {
        $st = $this->db->prepare(
            "SELECT codigo, nombre, fase, es_final FROM solicitudes_estados
             WHERE tipo_codigo = ? ORDER BY orden ASC"
        );
        $st->execute([$tipoCodigo]);
        return $st->fetchAll();
    }

    /** Inserta un adjunto y devuelve su id. */
    public function insertarAdjunto(
        int $solicitudId,
        array $a,
        ?int $eventoId = null
    ): int {
        $st = $this->db->prepare(
            "INSERT INTO solicitudes_adjuntos
               (solicitud_id, evento_id, contexto, tipo, tipo_documento, nombre_original,
                nombre_archivo, ruta_archivo, tamano_archivo, tipo_mime, subido_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $solicitudId, $eventoId, $a['contexto'], $a['tipo'], $a['tipo_documento'] ?? null,
            $a['nombre_original'] ?? null, $a['nombre_archivo'] ?? null, $a['ruta_archivo'],
            $a['tamano_archivo'] ?? null, $a['tipo_mime'] ?? null, $a['subido_por'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function adjuntos(int $solicitudId): array
    {
        $st = $this->db->prepare(
            "SELECT * FROM solicitudes_adjuntos WHERE solicitud_id = ? ORDER BY fecha_creacion ASC, id ASC"
        );
        $st->execute([$solicitudId]);
        return $st->fetchAll();
    }

    public function adjunto(int $adjId): ?array
    {
        $st = $this->db->prepare("SELECT * FROM solicitudes_adjuntos WHERE id = ?");
        $st->execute([$adjId]);
        return $st->fetch() ?: null;
    }

    /** Categorías de ticket (catálogo) activas. */
    public function ticketCategorias(bool $soloActivas = true): array
    {
        $sql = "SELECT * FROM solicitudes_ticket_categorias" . ($soloActivas ? " WHERE estado_activo = 1" : "") . " ORDER BY nombre";
        return $this->db->query($sql)->fetchAll();
    }

    public function guardarTicketCategoria(array $d, ?int $actorId = null): int
    {
        $estado = (int) (($d['estado_activo'] ?? 1) ? 1 : 0);
        if (isset($d['id']) && (int) $d['id'] > 0) {
            $st = $this->db->prepare(
                "UPDATE solicitudes_ticket_categorias SET nombre = ?, descripcion = ?, estado_activo = ?,
                        fecha_actualizacion = NOW() WHERE id = ?"
            );
            $st->execute([$d['nombre'], $d['descripcion'] ?? null, $estado, (int) $d['id']]);
            return (int) $d['id'];
        }
        $st = $this->db->prepare(
            "INSERT INTO solicitudes_ticket_categorias (nombre, descripcion, estado_activo) VALUES (?,?,?)"
        );
        $st->execute([$d['nombre'], $d['descripcion'] ?? null, $estado]);
        return (int) $this->db->lastInsertId();
    }

    public function eliminarTicketCategoria(int $id): void
    {
        $this->db->prepare("DELETE FROM solicitudes_ticket_categorias WHERE id = ?")->execute([$id]);
    }
}
