<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/creditos/CreditoService.php';
require_once dirname(__DIR__) . '/creditos/CreditoConfigService.php';

/**
 * Servicio del PORTAL de asociados (cx/). Solo LECTURA de datos civicos del asociado
 * autenticado + alta de solicitudes de crédito (origen='portal').
 *
 * REGLA DE ORO: TODA consulta se filtra estrictamente por la cédula de la sesión
 * (pasada como $cedula). Nunca se confía en un parámetro del cliente.
 */
final class PortalService
{
    private CreditoService $cred;
    private CreditoConfigService $cfg;

    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
        $this->cfg = new CreditoConfigService($this->db);
        $this->cred = new CreditoService($this->db, $this->cfg);
    }

    // ── Mi información ───────────────────────────────────────────────────
    public function perfil(string $cedula): array
    {
        $st = $this->db->prepare(
            'SELECT cedula, nombre, email, celular, telefono, ciudad, direccion,
                    barrio, fecha_nacimiento, fecha_ingreso, estado_civil, ocupacion, empresa
             FROM vw_asociados WHERE cedula = ?'
        );
        $st->execute([$cedula]);
        $r = $st->fetch();
        if (!$r) {
            throw new DomainException('Asociado no encontrado.');
        }
        return $r;
    }

    // ── Aportes (resumen) ────────────────────────────────────────────────
    public function aportes(string $cedula): array
    {
        $st = $this->db->prepare(
            'SELECT aportes_ordinarios, aportes_sociales, aportes_totales, aportes_revalorizaciones,
                    plan_futuro, bolsillos, bolsillos_ultima_fecha, comisiones, aportes_incentivos,
                    total_saldos_favor, total_incentivos
             FROM vw_productos WHERE cedula = ?'
        );
        $st->execute([$cedula]);
        $r = $st->fetch();
        if (!$r) {
            return [];
        }
        return $r;
    }

    // ── Extractos (sifone_movimientos_tributarios) ───────────────────────
    public const CUENTAS_EXTRACTOS = [31050501, 31050503, 31050504, 42309501];
    private const LABELS_EXTRACTOS = [
        31050501 => 'Aportes Ordinarios',
        31050503 => 'Plan Futuro',
        31050504 => 'Otros aportes',
        42309501 => 'Bolsillo Solidario',
    ];

    public function extractos(string $cedula, int $cuenta = 0, string $mes = ''): array
    {
        $where = 'cedula = ?';
        $params = [$cedula];
        if ($cuenta > 0) {
            $where .= ' AND cuenta = ?';
            $params[] = $cuenta;
        }
        if ($mes !== '' && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $where .= " AND DATE_FORMAT(fecham, '%Y-%m') = ?";
            $params[] = $mes;
        }
        $st = $this->db->prepare(
            "SELECT fecham AS fecha, cuenta,
                    COALESCE(credit, 0) AS credit, COALESCE(debito, 0) AS debito, detall,
                    numero, hora
             FROM sifone_movimientos_tributarios
             WHERE $where AND detall NOT LIKE '%CIERRE DEL PYG%'
             ORDER BY fecham DESC, hora DESC, cuenta DESC"
        );
        $st->execute($params);
        $rows = $st->fetchAll();
        $list = [];
        foreach ($rows as $r) {
            $r['cuenta_label'] = self::LABELS_EXTRACTOS[(int) $r['cuenta']] ?? ('Cuenta ' . $r['cuenta']);
            $r['detall'] = str_replace(['Cuota contribución', 'Fondo Solidario'], ['Fondo de Bienestar', 'Bolsillo'], (string) $r['detall']);
            $r['valor'] = ($r['credit'] != 0) ? (float) $r['credit'] : -(float) $r['debito'];
            unset($r['credit'], $r['debito']);
            $list[] = $r;
        }
        $cuentas = $this->db->prepare('SELECT DISTINCT cuenta FROM sifone_movimientos_tributarios WHERE cedula = ? ORDER BY cuenta');
        $cuentas->execute([$cedula]);
        return [
            'cuentas' => array_map(fn($c) => ['cuenta' => (int) $c['cuenta'], 'label' => self::LABELS_EXTRACTOS[(int) $c['cuenta']] ?? ('Cuenta ' . $c['cuenta'])], $cuentas->fetchAll()),
            'rows'    => $list,
        ];
    }

    // ── Elegibilidad crédito cooperativa para boletas (convenios) ─────────
    /** Antigüedad del asociado en meses (desde vw_asociados.fecha_ingreso). */
    public function antiguedadMeses(string $cedula): int
    {
        $st = $this->db->prepare('SELECT fecha_ingreso FROM vw_asociados WHERE cedula = ? LIMIT 1');
        $st->execute([$cedula]);
        $f = $st->fetchColumn();
        if (!$f) {
            return 0;
        }
        try {
            $desde = new DateTime((string) $f);
        } catch (Throwable $e) {
            return 0;
        }
        $d = $desde->diff(new DateTime('today'));
        return ((int) $d->y) * 12 + (int) $d->m;
    }

    /** Cantidad de pagos de aportes ordinarios positivos (cuenta 31050501) en toda la historia. */
    public function aportesOrdinariosPositivos(string $cedula): int
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM sifone_movimientos_tributarios
              WHERE cedula = ? AND cuenta = 31050501 AND credit > 0"
        );
        $st->execute([$cedula]);
        return (int) $st->fetchColumn();
    }

    /** Reglas del crédito cooperativa para boletas: N meses de antigüedad O N aportes; límite configurable. */
    public function elegibleCreditoBoleta(string $cedula): array
    {
        require_once dirname(__DIR__) . '/config/ConfigService.php';
        $cfg     = (new ConfigService())->creditoBoleta();
        $meses   = $this->antiguedadMeses($cedula);
        $aportes = $this->aportesOrdinariosPositivos($cedula);
        $cumpleAntig   = $meses >= $cfg['meses_min'];
        $cumpleAportes = $aportes >= $cfg['aportes_min'];
        return [
            'apto'               => $cumpleAntig || $cumpleAportes,
            'cumple_antiguedad'  => $cumpleAntig,
            'cumple_aportes'     => $cumpleAportes,
            'antiguedad_meses'   => $meses,
            'aportes'            => $aportes,
            'requisitos'         => ['meses_min' => $cfg['meses_min'], 'aportes_min' => $cfg['aportes_min']],
            'limite'             => $cfg['limite'],
        ];
    }

    // ── Movimientos (pagos/transacciones) ────────────────────────────────
    public function movimientos(string $cedula, string $mes = ''): array
    {
        $where = 't.cedula = ?';
        $params = [$cedula];
        if ($mes !== '' && preg_match('/^\d{4}-\d{2}$/', $mes)) {
            $where .= " AND DATE_FORMAT(t.fecha_creacion, '%Y-%m') = ?";
            $params[] = $mes;
        }
        $st = $this->db->prepare(
            "SELECT t.id, t.origen, t.pse_id, t.confiar_id, t.recibo_caja, t.valor_total,
                    t.fecha_creacion,
                    COALESCE(SUM(d.valor_asignado), 0) AS total_asignado,
                    COUNT(d.id) AS items
             FROM transacciones t
             LEFT JOIN transacciones_detalle d ON d.transaccion_id = t.id
             WHERE $where
             GROUP BY t.id, t.origen, t.pse_id, t.confiar_id, t.recibo_caja, t.valor_total, t.fecha_creacion
             ORDER BY t.fecha_creacion DESC"
        );
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['detalles'] = $this->movimientoDetalles((int) $r['id']);
        }
        return $rows;
    }

    private function movimientoDetalles(int $transaccionId): array
    {
        $st = $this->db->prepare(
            'SELECT id, tipo_rubro, referencia_credito, producto_id, descripcion, valor_asignado
             FROM transacciones_detalle WHERE transaccion_id = ? ORDER BY id'
        );
        $st->execute([$transaccionId]);
        return $st->fetchAll();
    }

    // ── Créditos (vw_creditos + estado) ──────────────────────────────────
    /**
     * vw_creditos no expone estado; activo vs finalizado sale de
     * `sifone_datacredito.estado_credito` (5 = finalizado), codeudor = 0.
     * Join por CAST numérico: Sifone trae cedula/numero_credito sin ceros a la izquierda.
     */
    private function creditosQuery(string $cedula): array
    {
        $st = $this->db->prepare(
            "SELECT vc.*, dc.estado_credito
             FROM vw_creditos vc
             LEFT JOIN sifone_datacredito dc
                ON CAST(dc.cedula AS UNSIGNED) = CAST(vc.cedula AS UNSIGNED)
               AND CAST(dc.numero_credito AS UNSIGNED) = CAST(vc.numero_credito AS UNSIGNED)
               AND dc.codeudor = 0
             WHERE vc.cedula = ?"
        );
        $st->execute([$cedula]);
        return $st->fetchAll();
    }

    public function creditos(string $cedula): array
    {
        $rows = $this->creditosQuery($cedula);
        $activos = [];
        $finalizados = [];
        foreach ($rows as $r) {
            $finalizado = (int) ($r['estado_credito'] ?? 0) === 5;
            $item = [
                'numero_credito'     => $r['numero_credito'],
                'tipo_prestamo'      => $r['tipo_prestamo'],
                'fecha_emision'      => $r['fecha_emision'],
                'fecha_vencimiento'  => $r['fecha_vencimiento'],
                'desembolso_inicial' => (float) $r['desembolso_inicial'],
                'saldo_capital'      => (float) $r['saldo_capital'],
                'valor_cuota'        => (float) $r['valor_cuota'],
                'cuotas_totales'     => (int) $r['cuotas_totales'],
                'cuotas_pendientes'  => (int) $r['cuotas_pendientes'],
                'saldo_mora'         => (float) $r['saldo_mora'],
                'dias_mora'          => (int) $r['dias_mora'],
                'fecha_pago'         => $r['fecha_pago'],
            ];
            if ($finalizado) {
                $finalizados[] = $item;
            } else {
                $activos[] = $item;
            }
        }
        return ['activos' => $activos, 'finalizados' => $finalizados];
    }

    public function creditoDetalle(string $cedula, string $numeroCredito): array
    {
        $st = $this->db->prepare(
            "SELECT vc.*, dc.estado_credito
             FROM vw_creditos vc
             LEFT JOIN sifone_datacredito dc
                ON CAST(dc.cedula AS UNSIGNED) = CAST(vc.cedula AS UNSIGNED)
               AND CAST(dc.numero_credito AS UNSIGNED) = CAST(vc.numero_credito AS UNSIGNED)
               AND dc.codeudor = 0
             WHERE vc.cedula = ? AND vc.numero_credito = ?"
        );
        $st->execute([$cedula, $numeroCredito]);
        $r = $st->fetch();
        if (!$r) {
            throw new DomainException('Crédito no encontrado.');
        }
        return $r;
    }

    // ── Líneas para el formulario de solicitud ───────────────────────────
    public function lineas(): array
    {
        return $this->cfg->lineas(true, true);
    }

    // ── Solicitudes (solo las del asociado) ──────────────────────────────
    public function solicitudesDe(string $cedula, int $limit = 50, int $offset = 0): array
    {
        // Filtro estricto por la cédula de la sesión (dueño).
        return $this->cred->listar('', '', '', 'id', 'DESC', $limit, $offset, $cedula);
    }

    public function solicitudDetalle(int $id, string $cedula): array
    {
        $d = $this->cred->detalle($id);
        $this->verificarDueno($d, $cedula);
        return $d;
    }

    /** Crea una solicitud a nombre del asociado; SIEMPRE fuerza cédula/origen de sesión. */
    public function solicitudCrear(string $cedula, array $d): int
    {
        if (empty($d['acepta_tratamiento_datos'])) {
            throw new DomainException('Debe aceptar el tratamiento de datos personales.');
        }
        $payload = $d;
        $payload['origen']          = 'portal';
        $payload['cedula_asociado'] = $cedula;   // identidad de sesión, ignora lo que mande el cliente
        if (empty($payload['nombre_solicitante'])) {
            $p = $this->perfil($cedula);
            $payload['nombre_solicitante'] = $p['nombre'] ?? '';
        }
        if (empty($payload['numero_identificacion'])) $payload['numero_identificacion'] = $cedula;
        if (empty($payload['numero_telefono']))       $payload['numero_telefono']       = (string) ($this->perfil($cedula)['celular'] ?? '');
        if (empty($payload['correo_electronico']))    $payload['correo_electronico']    = (string) ($this->perfil($cedula)['email'] ?? '');

        $id = $this->cred->crear($payload, ['tipo' => 'portal', 'id' => $cedula]);

        // Consentimientos vía EAV (crédito: acepta_tratamiento_datos + ofertas).
        // Los ids se resuelven por `clave` (NUNCA hardcodeados): la configuración
        // de aceptaciones puede recrearse y cambiar sus ids.
        $items = [];
        $idTrat = $this->idAceptacionPorClave('acepta_tratamiento_datos');
        if ($idTrat > 0) {
            $items[] = ['config_aceptacion_id' => $idTrat, 'aceptado' => true];
        }
        if (!empty($payload['acepta_ofertas_comerciales'])) {
            $idOf = $this->idAceptacionPorClave('acepta_ofertas_comerciales');
            if ($idOf > 0) {
                $items[] = ['config_aceptacion_id' => $idOf, 'aceptado' => true];
            }
        }
        if ($items) {
            $this->cred->guardarAceptaciones($id, $items, ['id' => $cedula]);
        }
        return $id;
    }

    /** Id de una aceptación configurada por su `clave` (0 si no existe/está inactiva). */
    private function idAceptacionPorClave(string $clave): int
    {
        $st = $this->db->prepare('SELECT id FROM credito_config_aceptacion WHERE clave = ? AND estado_activo = 1 LIMIT 1');
        $st->execute([$clave]);
        return (int) ($st->fetchColumn() ?: 0);
    }

    /** Aceptación/autorización de la solicitud por el asociado (guardarAceptaciones + marca). */
    public function solicitudAceptar(int $id, string $cedula, array $items): void
    {
        $d = $this->solicitudDetalle($id, $cedula);
        $this->cred->guardarAceptaciones($id, $items, ['id' => $cedula]);
        if ($items) {
            $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
            $this->db->prepare(
                "UPDATE credito_solicitudes SET aceptado_por_asociado = 1, fecha_aceptacion = NOW(), ip_aceptacion = ?
                 WHERE id = ?"
            )->execute([$ip, $id]);
        }
        Logger::log('editar', 'creditos.aceptar', $cedula, "Solicitud #$id aceptada por el asociado");
    }

    // ── Documentos ───────────────────────────────────────────────────────
    /** Una solicitud pertenece al asociado (`cedula_asociado` o `numero_identificacion`). */
    public function verificarDueno(array $d, string $cedula): void
    {
        $owned = (string) ($d['cedula_asociado'] ?? '');
        $ident = (string) ($d['numero_identificacion'] ?? '');
        if ($owned !== $cedula && $ident !== $cedula) {
            throw new DomainException('La solicitud no le pertenece.');
        }
    }

    /** ¿El documento pertenece a una solicitud del asociado? */
    public function documentoOwnedBy(int $docId, string $cedula): bool
    {
        $st = $this->db->prepare(
            'SELECT s.cedula_asociado, s.numero_identificacion
             FROM credito_documentos d
             JOIN credito_solicitudes s ON s.id = d.solicitud_id
             WHERE d.id = ?'
        );
        $st->execute([$docId]);
        $r = $st->fetch();
        if (!$r) {
            return false;
        }
        return (string) $r['cedula_asociado'] === $cedula || (string) $r['numero_identificacion'] === $cedula;
    }

    public function documentoGuardar(int $solicitudId, string $etapa, string $tipo, array $file, string $cedula): array
    {
        $this->solicitudDetalle($solicitudId, $cedula);
        return $this->cred->documentoGuardar($solicitudId, $etapa, $tipo, $file, ['id' => $cedula]);
    }

    public function documentoGet(int $docId, string $cedula): ?array
    {
        $d = $this->cred->getDocumento($docId);
        if (!$d || !$this->documentoOwnedBy($docId, $cedula)) {
            throw new DomainException('Documento no encontrado.', 404);
        }
        return $d;
    }

    /** Config de documentos requeridos para la etapa y tipo de solicitante (canal cx). */
    public function documentosRequeridos(string $ocasion, ?string $tipoSolicitante): array
    {
        // Muestra TODAS las secciones/documentos con 'visible_en' incl. cx, sin importar la
        // etapa (ocasion) de la sección: en CX el asociado ve todo de una vez. La subida se
        // habilita solo en la etapa 'creacion' (se controla en la página/documentos).
        return $this->cfg->renderSeccionesAll('cx', $tipoSolicitante);
    }

    // ── Notificaciones (feed "Mis avisos", solo cédula de sesión) ──────
    /**
     * Feed de avisos del asociado: últimos eventos de sus solicitudes
     * (`solicitudes_eventos`) + correos enviados/encolados
     * (`email_cola` ligados a solicitudes propias).
     * Todo filtrado estrictamente por $cedula (identidad de sesión).
     */
    public function notificaciones(string $cedula, int $limit = 50): array
    {
        $limit = min(100, max(1, $limit));
        $pdo = $this->db;

        $st = $pdo->prepare(
            "SELECT ev.id AS orig_id, 'evento' AS tipo, s.tipo_codigo, s.numero_radicado,
                    ev.tipo_evento, ev.estado_anterior, ev.estado_nuevo, ev.mensaje,
                    ev.actor_tipo, ev.fecha
             FROM solicitudes_eventos ev
             JOIN solicitudes s ON s.id = ev.solicitud_id
             WHERE s.cedula_solicitante = ?
             ORDER BY ev.fecha DESC, ev.id DESC
             LIMIT " . (int) $limit
        );
        $st->execute([$cedula]);
        $eventos = $st->fetchAll();

        $st = $pdo->prepare(
            "SELECT mc.id AS orig_id, 'correo' AS tipo, s.tipo_codigo, s.numero_radicado,
                    mc.tipo AS tipo_evento, NULL AS estado_anterior, mc.estado AS estado_nuevo,
                    mc.asunto AS mensaje, 'sistema' AS actor_tipo, mc.fecha_creacion AS fecha
             FROM email_cola mc
             JOIN solicitudes s ON s.id = mc.solicitud_id
             WHERE s.cedula_solicitante = ?
             ORDER BY mc.fecha_creacion DESC, mc.id DESC
             LIMIT " . (int) $limit
        );
        $st->execute([$cedula]);
        $correos = $st->fetchAll();

        $items = array_merge($eventos, $correos);
        usort($items, static fn($a, $b) => strtotime($b['fecha']) <=> strtotime($a['fecha']));
        return array_slice($items, 0, $limit);
    }

    // ── Convenios con productos tipo boleta (maneja_stock=1) ────────────
    /** Convenios activos y, por cada uno, sus productos-boleta ofertables. */
    public function convenios(): array
    {
        $pdo = $this->db;
        $st = $pdo->query(
            "SELECT c.id, c.nombre, c.descripcion, c.imagen, c.activo
             FROM convenios c
             WHERE c.activo = 1
             ORDER BY c.nombre"
        );
        $out = [];
        foreach ($st->fetchAll() as $c) {
            $sp = $pdo->prepare(
                "SELECT p.id, p.nombre, p.precio_venta, p.precio_compra,
                        (SELECT COUNT(*) FROM boleteria_boletas b
                         WHERE b.producto_id = p.id AND b.estado = 'disponible') AS disponibles
                 FROM convenios_productos p
                 WHERE p.convenio_id = ? AND p.maneja_stock = 1 AND p.activo = 1
                 ORDER BY p.nombre"
            );
            $sp->execute([(int) $c['id']]);
            $productos = array_map(static fn($r) => [
                'id' => $r['id'],
                'nombre' => $r['nombre'],
                'precio_venta' => (float) ($r['precio_venta'] ?? 0),
                'disponibles' => (int) $r['disponibles'],
            ], $sp->fetchAll());
            if ($productos) {
                $out[] = [
                    'id' => (int) $c['id'],
                    'nombre' => $c['nombre'],
                    'descripcion' => $c['descripcion'],
                    'tiene_imagen' => trim((string) ($c['imagen'] ?? '')) !== '',
                    'productos' => $productos,
                ];
            }
        }
        return $out;
    }
}