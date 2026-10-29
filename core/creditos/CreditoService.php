<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/Storage/Storage.php';
require_once __DIR__ . '/CreditoConfigService.php';
require_once __DIR__ . '/AmortizacionService.php';
require_once dirname(__DIR__) . '/Email/EmailCola.php';
require_once dirname(__DIR__) . '/Email/EmailTemplates.php';

/**
 * Fábrica de Créditos (B7a). Soporta el flujo de solicitudes de crédito con
 * campos dinámicos (EAV), documentos por etapa y "Aceptación y Autorizaciones".
 */
final class CreditoService
{
    private const ESTADO_POR_ETAPA = [
        'creacion' => 'solicitado', 'revision' => 'revisado',
        'estudio' => 'con_estudio', 'consideracion' => 'con_estudio',
        'estudio_aprobado' => 'con_estudio', 'radicado_tesoreria' => 'con_estudio',
        'estudio_rechazado' => 'rechazado', 'final' => 'desembolsado',
    ];
    private const ETAPAS = ['creacion', 'revision', 'estudio', 'consideracion', 'estudio_aprobado', 'radicado_tesoreria', 'estudio_rechazado', 'final'];
    private const TIPOS_SOLICITANTE = ['estudiante', 'empleado_descuento_nomina', 'empleado_sin_descuento', 'independiente', 'pensionado_descuento_libranza', 'pensionado_sin_descuento_libranza'];

    public function __construct(private ?PDO $db = null, private ?CreditoConfigService $cfg = null)
    {
        $this->db = $db ?? db();
        $this->cfg = $cfg ?? new CreditoConfigService($this->db);
    }

    // ── Crear / listar / detalle ─────────────────────────────────────────
    public function crear(array $d, ?array $actor = null): int
    {
        $tipo = trim((string) ($d['tipo_solicitante'] ?? ''));
        if ($tipo === '') throw new DomainException('El tipo de solicitante es obligatorio.');
        $nombre = trim((string) ($d['nombre_solicitante'] ?? ''));
        if ($nombre === '') throw new DomainException('El nombre del solicitante es obligatorio.');
        $monto = (float) ($d['monto_deseado'] ?? 0);
        if ($monto < 100000) throw new DomainException('El monto deseado debe ser al menos $100.000.');
        $cuotas = (int) ($d['numero_cuotas_deseadas'] ?? 0);
        if ($cuotas < 1 || $cuotas > 72) throw new DomainException('Las cuotas deseadas deben estar entre 1 y 72.');
        $lineaId = (int) ($d['tasa_interes_id'] ?? 0);
        if ($lineaId > 0) {
            $lin = $this->cfg->lineas(true, true);
            $l = array_values(array_filter($lin, fn($x) => (int) $x['id'] === $lineaId));
            if (!$l) throw new DomainException('La línea de crédito no es válida.');
            if ($cuotas > (int) $l[0]['limite_meses']) throw new DomainException('Las cuotas superan el límite de la línea (' . $l[0]['limite_meses'] . ').');
        }
        $ced = trim((string) ($d['cedula_asociado'] ?? ''));
        if ($ced === '') throw new DomainException('Falta la cédula del asociado.');
        // El portal (cx/) crea solicitudes con origen='portal' y cédula de la sesión del asociado.
        $origen = in_array(($d['origen'] ?? 'ui'), ['ui', 'portal'], true) ? (string) $d['origen'] : 'ui';

        $numero = $this->generarNumero($origen === 'portal' ? 'CD' : 'FC');
        // El codeudor se decide en UI/staff; si no se envía, queda "no necesita" por defecto.
        $desCodeudor  = (int) !empty($d['desea_codeudor']);
        $tipoCodeudor = trim((string) ($d['tipo_codeudor'] ?? ''));
        if ($tipoCodeudor === '') $tipoCodeudor = 'no_necesita';
        $this->db->prepare(
            "INSERT INTO credito_solicitudes (numero_solicitud, origen, cedula_asociado, tipo_solicitante, nombre_solicitante, numero_identificacion, numero_telefono, correo_electronico, monto_deseado, numero_cuotas_deseadas, desea_codeudor, tipo_codeudor, tasa_interes_id, estado, etapa_actual, creado_por)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'solicitado','creacion',?)"
        )->execute([$numero, $origen, $ced, $tipo, $nombre, trim((string) ($d['numero_identificacion'] ?? '')), trim((string) ($d['numero_telefono'] ?? '')), trim((string) ($d['correo_electronico'] ?? '')), $monto, $cuotas, $desCodeudor, $tipoCodeudor, $lineaId ?: null, $actor['id'] ?? null]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'creditos', $actor['id'] ?? null, "Creada solicitud $numero #$id para $ced");
        if (!empty($d['correo_electronico'])) {
            EmailCola::encolar(
                'credito',
                trim((string) $d['correo_electronico']),
                'Solicitud de crédito recibida',
                EmailTemplates::creditoEstado($nombre, $numero, 'Su solicitud fue recibida y está en estudio.'),
                null
            );
        }
        return $id;
    }

    public function listar(string $q = '', string $estado = '', string $etapa = '', string $orderBy = 'id', string $orderDir = 'DESC', int $limit = 20, int $offset = 0, ?string $cedula = null): array
    {
        $orderBy = in_array($orderBy, ['id', 'numero_solicitud', 'cedula_asociado', 'nombre_solicitante', 'monto_deseado', 'fecha_creacion'], true) ? $orderBy : 'id';
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $where = []; $params = [];
        if ($q !== '') { $where[] = '(numero_solicitud LIKE ? OR cedula_asociado LIKE ? OR nombre_solicitante LIKE ?)'; $l = '%' . $q . '%'; array_push($params, $l, $l, $l); }
        if ($estado !== '') { $where[] = 'estado = ?'; $params[] = $estado; }
        if ($etapa !== '') { $where[] = 'etapa_actual = ?'; $params[] = $etapa; }
        // Filtro estricto por cédula (solo lo del asociado): portal usa esto para no listar lo de los demás.
        if ($cedula !== null && $cedula !== '') { $where[] = '(cedula_asociado = ? OR numero_identificacion = ?)'; array_push($params, $cedula, $cedula); }
        $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        $st = $this->db->prepare("SELECT COUNT(*) FROM credito_solicitudes $w"); $st->execute($params); $total = (int) $st->fetchColumn();
        $st = $this->db->prepare("SELECT s.*, l.nombre_credito AS linea_aprobada, li.nombre_credito AS linea_solicitada,
                (SELECT COUNT(*) FROM credito_documentos x WHERE x.solicitud_id=s.id) AS num_docs
             FROM credito_solicitudes s LEFT JOIN credito_lineas l ON l.id = s.linea_credito_aprobada_id
             LEFT JOIN credito_lineas li ON li.id = s.tasa_interes_id
             $w ORDER BY `$orderBy` $orderDir LIMIT ? OFFSET ?");
        foreach ($params as $i => $v) $st->bindValue($i + 1, $v);
        $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        return ['rows' => $st->fetchAll(), 'total' => $total, 'limit' => $limit, 'offset' => $offset];
    }

    public function detalle(int $id): array
    {
        $st = $this->db->prepare("SELECT s.*, l.nombre_credito AS linea_solicitada_nombre, la.nombre_credito AS linea_aprobada_nombre
             FROM credito_solicitudes s
             LEFT JOIN credito_lineas l ON l.id = s.tasa_interes_id
             LEFT JOIN credito_lineas la ON la.id = s.linea_credito_aprobada_id
             WHERE s.id = ?");
        $st->execute([$id]);
        $s = $st->fetch();
        if (!$s) throw new DomainException('Solicitud no encontrada.');
        $s['lineas'] = $this->cfg->lineas(true, true);
        // EAV
        $st = $this->db->prepare("SELECT v.*, c.campo, c.nombre AS campo_nombre, c.tipo AS campo_tipo
             FROM credito_campos_valores v LEFT JOIN credito_config_campo c ON c.id = v.config_campo_id WHERE v.solicitud_id=?");
        $st->execute([$id]);
        $s['valores'] = $st->fetchAll();
        $st = $this->db->prepare("SELECT v.*, a.clave, a.titulo, a.texto, a.tipo AS a_tipo
             FROM credito_campos_valores v LEFT JOIN credito_config_aceptacion a ON a.id = v.config_aceptacion_id WHERE v.solicitud_id=?");
        $st->execute([$id]);
        $s['aceptaciones'] = $st->fetchAll();
        $st = $this->db->prepare("SELECT * FROM credito_documentos WHERE solicitud_id=? ORDER BY etapa, tipo_documento");
        $st->execute([$id]);
        $s['documentos'] = $st->fetchAll();
        $s['plan'] = $this->planDatos($id);
        return $s;
    }

    // ── Etapas ──────────────────────────────────────────────────────────
    public function cambiarEtapa(int $id, string $destino, ?array $actor = null): void
    {
        if (!in_array($destino, self::ETAPAS, true)) throw new DomainException('Etapa inválida.');
        $s = $this->uno($id);
        $s = $this->exigirEditable($s);
        // Gate por reglas de negocio (estilo legacy con validación): para pasar a etapas de
        // aprobación/desembolso se requieren los valores aprobados; a "final" además el desembolso.
        $requiereAprobados = in_array($destino, ['estudio_aprobado', 'radicado_tesoreria', 'final'], true);
        if ($requiereAprobados && ($s['monto_aprobado'] === null || $s['linea_credito_aprobada_id'] === null || $s['cuotas_aprobadas'] === null || $s['codeudor_aprobado'] === null)) {
            throw new DomainException('Debe capturar los valores aprobados antes de pasar a esta etapa.');
        }
        if ($destino === 'final' && ((float) $s['valor_real_desembolso']) <= 0) {
            throw new DomainException('Debe registrar el valor real del desembolso antes de pasar a "Final".');
        }
        if ($destino === 'final' && empty($s['fecha_desembolso'])) {
            throw new DomainException('Debe registrar la fecha de desembolso antes de pasar a "Final".');
        }
        $estado = self::ESTADO_POR_ETAPA[$destino];
        $this->db->prepare("UPDATE credito_solicitudes SET etapa_actual=?, estado=?, fecha_actualizacion=NOW() WHERE id=?")->execute([$destino, $estado, $id]);
        Logger::log('editar', 'creditos.etapa', $actor['id'] ?? null, "Solicitud #$id → $destino ($estado)");
        $this->notificar($s['correo_electronico'] ?? null, "Solicitud #{$s['numero_solicitud']} ahora en etapa $destino.");
    }
    public function cancelar(int $id, string $motivo, ?array $actor = null): void
    {
        $this->exigirEditable($this->uno($id));
        if (trim($motivo) === '') throw new DomainException('El motivo de cancelación es obligatorio.');
        $this->db->prepare("UPDATE credito_solicitudes SET estado='cancelado', comentarios_rechazo=?, fecha_actualizacion=NOW() WHERE id=?")->execute([$motivo, $id]);
        Logger::log('eliminar', 'creditos', $actor['id'] ?? null, "Cancelada solicitud #$id: $motivo", null, null, 'warning');
    }
    public function rechazar(int $id, string $comentario, ?array $actor = null): void
    {
        $this->exigirEditable($this->uno($id));
        if (trim($comentario) === '') throw new DomainException('Debe indicar un comentario del rechazo.');
        $this->db->prepare("UPDATE credito_solicitudes SET estado='rechazado', etapa_actual='estudio_rechazado', comentarios_rechazo=?, fecha_actualizacion=NOW() WHERE id=?")->execute([$comentario, $id]);
        Logger::log('editar', 'creditos', $actor['id'] ?? null, "Rechazada solicitud #$id", null, null, 'warning');
        $this->notificarNota($id, 'Su solicitud de crédito fue RECHAZADA. Motivo: ' . $comentario);
    }

    /** Edita los datos de creación de una solicitud UI (solicitante, valores y línea original). */
    public function actualizarDatosBasicos(int $id, array $d, ?array $actor = null): void
    {
        $s = $this->exigirEditable($this->uno($id));
        if ($s['origen'] !== 'ui') {
            throw new DomainException('Solo se pueden editar los datos de solicitudes creadas en UI (origen=ui).');
        }
        $nombre = trim((string) ($d['nombre_solicitante'] ?? ($s['nombre_solicitante'] ?? '')));
        if ($nombre === '') throw new DomainException('El nombre del solicitante es obligatorio.');

        $tipo = trim((string) ($d['tipo_solicitante'] ?? ($s['tipo_solicitante'] ?? '')));
        if (!in_array($tipo, self::TIPOS_SOLICITANTE, true)) {
            throw new DomainException('El tipo de solicitante no es válido.');
        }

        $monto = (float) ($d['monto_deseado'] ?? ($s['monto_deseado'] ?? 0));
        if ($monto < 100000) throw new DomainException('El monto deseado debe ser al menos $100.000.');
        $cuotas = (int) ($d['numero_cuotas_deseadas'] ?? ($s['numero_cuotas_deseadas'] ?? 0));
        if ($cuotas < 1 || $cuotas > 72) throw new DomainException('Las cuotas deseadas deben estar entre 1 y 72.');

        $lineaActual = (int) ($s['tasa_interes_id'] ?? 0);
        $lineaId = array_key_exists('tasa_interes_id', $d) ? (int) $d['tasa_interes_id'] : $lineaActual;
        if ($lineaId > 0) {
            $linea = null;
            foreach ($this->cfg->lineas(true, true) as $l) {
                if ((int) $l['id'] === $lineaId) { $linea = $l; break; }
            }
            // La línea original puede estar inactiva u oculta: se permite conservarla.
            if ($linea === null && $lineaId === $lineaActual) {
                $st = $this->db->prepare("SELECT * FROM credito_lineas WHERE id=?");
                $st->execute([$lineaId]);
                $linea = $st->fetch() ?: null;
            }
            if ($linea === null) throw new DomainException('La línea de crédito no es válida.');
            if ((int) ($linea['limite_meses'] ?? 0) > 0 && $cuotas > (int) $linea['limite_meses']) {
                throw new DomainException('Las cuotas superan el límite de la línea (' . (int) $linea['limite_meses'] . ').');
            }
        }

        $desear = array_key_exists('desea_codeudor', $d) ? (int) !empty($d['desea_codeudor']) : (int) !empty($s['desea_codeudor']);
        $tipoCodeudor = trim((string) ($d['tipo_codeudor'] ?? ($s['tipo_codeudor'] ?? '')));
        if (!$desear) {
            $tipoCodeudor = 'no_necesita';
        } elseif (!in_array($tipoCodeudor, ['codeudor_dependiente', 'codeudor_pensionado'], true)) {
            throw new DomainException('Debe indicar el tipo de codeudor.');
        }

        $identificacion = trim((string) ($d['numero_identificacion'] ?? ($s['numero_identificacion'] ?? '')));
        $telefono = trim((string) ($d['numero_telefono'] ?? ($s['numero_telefono'] ?? '')));
        $correo = trim((string) ($d['correo_electronico'] ?? ($s['correo_electronico'] ?? '')));

        $this->db->prepare(
            "UPDATE credito_solicitudes SET tipo_solicitante=?, nombre_solicitante=?, numero_identificacion=?, numero_telefono=?, correo_electronico=?, monto_deseado=?, numero_cuotas_deseadas=?, tasa_interes_id=?, desea_codeudor=?, tipo_codeudor=?, fecha_actualizacion=NOW() WHERE id=?"
        )->execute([$tipo, $nombre, $identificacion, $telefono, $correo, $monto, $cuotas, $lineaId > 0 ? $lineaId : null, $desear, $tipoCodeudor, $id]);
        Logger::log('editar', 'creditos', $actor['id'] ?? null, "Datos de creación solicitud #$id");
    }

    public function aprobar(int $id, array $d, ?array $actor = null): void
    {
        $this->exigirEditable($this->uno($id));
        $monto = (float) ($d['monto_aprobado'] ?? 0);
        $linea = (int) ($d['linea_credito_aprobada_id'] ?? 0);
        $cuotas = (int) ($d['cuotas_aprobadas'] ?? 0);
        $codeudor = trim((string) ($d['codeudor_aprobado'] ?? ''));
        if ($monto <= 0 || $linea <= 0 || $cuotas <= 0 || $codeudor === '') throw new DomainException('Valores aprobados incompletos.');
        $this->db->prepare("UPDATE credito_solicitudes SET monto_aprobado=?, linea_credito_aprobada_id=?, cuotas_aprobadas=?, codeudor_aprobado=?, fecha_actualizacion=NOW() WHERE id=?")
            ->execute([$monto, $linea, $cuotas, $codeudor, $id]);
        Logger::log('editar', 'creditos', $actor['id'] ?? null, "Aprobada solicitud #$id", null, null, 'warning');
        $this->notificarNota($id, 'Su solicitud de crédito fue APROBADA.');
    }
    public function costosPac(int $id, array $d, ?array $actor = null): void
    {
        $this->exigirEditable($this->uno($id));
        $this->db->prepare("UPDATE credito_solicitudes SET base_aportes=?, fianza=?, prestamos_recoger=?, dto_papeleria=?, desembolsado_pac=?, fecha_actualizacion=NOW() WHERE id=?")
            ->execute([(float) ($d['base_aportes'] ?? 0), (float) ($d['fianza'] ?? 0), (float) ($d['prestamos_recoger'] ?? 0), (float) ($d['dto_papeleria'] ?? 0), (float) ($d['desembolsado_pac'] ?? 0), $id]);
        Logger::log('editar', 'creditos', $actor['id'] ?? null, "Costos/PAC solicitud #$id");
    }
    public function desembolso(int $id, array $d, ?array $actor = null): void
    {
        $s = $this->exigirEditable($this->uno($id));
        if ($s['monto_aprobado'] === null || $s['linea_credito_aprobada_id'] === null || $s['cuotas_aprobadas'] === null) {
            throw new DomainException('Debe aprobar el crédito antes de registrar el desembolso.');
        }
        $valor = (float) ($d['valor_real_desembolso'] ?? 0);
        $fecha = ($d['fecha_desembolso'] ?? null);
        if ($valor <= 0) throw new DomainException('El valor real del desembolso es obligatorio.');
        if (empty($fecha)) throw new DomainException('La fecha de desembolso es obligatoria.');
        $this->db->prepare("UPDATE credito_solicitudes SET numero_credito_sifone=?, valor_real_desembolso=?, fecha_desembolso=?, estado='desembolsado', etapa_actual='final', fecha_actualizacion=NOW() WHERE id=?")
            ->execute([trim((string) ($d['numero_credito_sifone'] ?? '')) ?: null, $valor, $fecha, $id]);
        Logger::log('editar', 'creditos', $actor['id'] ?? null, "Desembolso solicitud #$id", null, null, 'warning');
        $this->notificarNota($id, 'Su crédito ha sido DESEMBOLSADO.');
    }

    // ── EAV / aceptación / documentos ────────────────────────────────────
    /**
     * Mapeo entre la clave de un campo configurable (credito_config_campo.campo)
     * y la columna TIPADA de credito_solicitudes. Permite gestionar los campos del
     * crédito desde config_secciones (EAV) manteniendo sincronizadas las columnas
     * que usa la lógica (plan de pagos, gates de etapas, desembolso).
     */
    public const CAMPOS_TIPADOS = [
        'monto_aprobado' => 'monto_aprobado',
        'linea_aprobada' => 'linea_credito_aprobada_id',
        'cuotas_aprobadas' => 'cuotas_aprobadas',
        'codeudor_aprobado' => 'codeudor_aprobado',
        'desea_codeudor' => 'desea_codeudor',
        'tipo_codeudor' => 'tipo_codeudor',
        'base_aportes' => 'base_aportes',
        'fianza' => 'fianza',
        'prestamos_recoger' => 'prestamos_recoger',
        'dto_papeleria' => 'dto_papeleria',
        'numero_sifone' => 'numero_credito_sifone',
        'fecha_desembolso' => 'fecha_desembolso',
        'valor_desembolso' => 'valor_real_desembolso',
        'desembolsado_a_pac' => 'desembolsado_pac',
    ];

    public function guardarValores(int $solicitudId, array $valores, ?array $actor = null): void
    {
        $this->exigirEditable($this->uno($solicitudId));
        // índice clave-campo para resolver el mapeo tipado
        $keys = [];
        if ($valores) {
            $st = $this->db->prepare("SELECT id, campo FROM credito_config_campo WHERE id IN (" . implode(',', array_map('intval', array_column($valores, 'config_campo_id'))) . ")");
            $st->execute();
            foreach ($st->fetchAll() as $c) $keys[(int) $c['id']] = $c['campo'];
        }
        foreach ($valores as $val) {
            $campoId = (int) ($val['config_campo_id'] ?? 0);
            $valor = ($val['valor'] ?? null);
            if ($campoId <= 0 || isset($val['borrar']) && $val['borrar']) {
                if ($campoId > 0) {
                    $this->db->prepare("DELETE FROM credito_campos_valores WHERE solicitud_id=? AND config_campo_id=?")->execute([$solicitudId, $campoId]);
                    $this->sincronizarTipado($solicitudId, $keys[$campoId] ?? null, null);
                }
                continue;
            }
            $st = $this->db->prepare("SELECT id FROM credito_campos_valores WHERE solicitud_id=? AND config_campo_id=? AND config_aceptacion_id IS NULL");
            $st->execute([$solicitudId, $campoId]);
            if ($st->fetch()) {
                $this->db->prepare("UPDATE credito_campos_valores SET valor=? WHERE solicitud_id=? AND config_campo_id=?")->execute([$valor, $solicitudId, $campoId]);
            } else {
                $this->db->prepare("INSERT INTO credito_campos_valores (solicitud_id, config_campo_id, valor) VALUES (?,?,?)")->execute([$solicitudId, $campoId, $valor]);
            }
            $this->sincronizarTipado($solicitudId, $keys[$campoId] ?? null, $valor);
        }
        Logger::log('editar', 'creditos', $actor['id'] ?? null, "Campos EAV solicitud #$solicitudId");
    }

    /** Sincroniza la columna tipada de una solicitud a partir de un campo EAV conocido. */
    private function sincronizarTipado(int $solicitudId, ?string $campo, $valor): void
    {
        if ($campo === null || !isset(self::CAMPOS_TIPADOS[$campo])) return;
        if ($valor === null || $valor === '') {
            $this->db->prepare("UPDATE credito_solicitudes SET `" . self::CAMPOS_TIPADOS[$campo] . "`=NULL, fecha_actualizacion=NOW() WHERE id=?")->execute([$solicitudId]);
            return;
        }
        $col = self::CAMPOS_TIPADOS[$campo];
        $typed = match ($col) {
            'desea_codeudor', 'desembolsado_pac' => (int) ($valor == '1' || $valor === true),
            'linea_credito_aprobada_id', 'cuotas_aprobadas' => (int) $valor,
            default => $valor,
        };
        $this->db->prepare("UPDATE credito_solicitudes SET `$col`=?, fecha_actualizacion=NOW() WHERE id=?")->execute([$typed, $solicitudId]);
    }
    public function guardarAceptaciones(int $solicitudId, array $items, ?array $actor = null): void
    {
        $this->exigirEditable($this->uno($solicitudId));
        foreach ($items as $it) {
            $acaId = (int) ($it['config_aceptacion_id'] ?? 0);
            if ($acaId <= 0) continue;
            $aceptado = ($it['aceptado'] ?? false) ? '1' : null;
            $st = $this->db->prepare("SELECT id FROM credito_campos_valores WHERE solicitud_id=? AND config_aceptacion_id=?");
            $st->execute([$solicitudId, $acaId]);
            if ($st->fetch()) {
                $this->db->prepare("UPDATE credito_campos_valores SET valor=? WHERE solicitud_id=? AND config_aceptacion_id=?")->execute([$aceptado, $solicitudId, $acaId]);
            } else {
                $this->db->prepare("INSERT INTO credito_campos_valores (solicitud_id, config_aceptacion_id, valor) VALUES (?,?,?)")->execute([$solicitudId, $acaId, $aceptado]);
            }
        }
        // marca fecha de decisión de aceptación en la cabecera si hubo alguna
        if ($items) { $this->db->prepare("UPDATE credito_solicitudes SET fecha_aceptacion=NOW() WHERE id=?")->execute([$solicitudId]); Logger::log('editar', 'creditos', $actor['id'] ?? null, "Aceptaciones solicitud #$solicitudId"); }
    }

    public function documentoGuardar(int $solicitudId, string $etapa, string $tipo, array $file, ?array $actor = null): array
    {
        $this->exigirEditable($this->uno($solicitudId));
        if ($file['error'] ?? 1 !== UPLOAD_ERR_OK) throw new DomainException('Archivo no recibido.');
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['pdf', 'png', 'jpg', 'jpeg'], true)) throw new DomainException('Formato no permitido. Use PDF, PNG o JPG.');
        $clave = $etapa . '_' . substr(hash('sha256', (string) microtime(true)), 0, 12);
        $nombre = Storage::nombreOpaco('cred', (string) $solicitudId, $clave, $ext);
        Storage::for('creditos')->put('creditos/' . $nombre, $file['tmp_name'], ['mime' => $file['type'] ?? 'application/octet-stream']);
        $mime = (function_exists('finfo_open') && is_file($file['tmp_name'])) ? (finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']) ?: null) : null;
        $this->db->prepare("DELETE FROM credito_documentos WHERE solicitud_id=? AND etapa=? AND tipo_documento=?")->execute([$solicitudId, $etapa, $tipo]);
        $this->db->prepare("INSERT INTO credito_documentos (solicitud_id, etapa, tipo_documento, nombre_archivo, ruta_archivo, tamaño_archivo, tipo_mime, subido_por) VALUES (?,?,?,?,?,?,?,?)")
            ->execute([$solicitudId, $etapa, $tipo, $file['name'], $nombre, (int) $file['size'], $mime, $actor['id'] ?? null]);
        Logger::log('crear', 'creditos.documento', $actor['id'] ?? null, "Doc $tipo en etapa $etapa (solicitud #$solicitudId)");
        return ['ruta' => $nombre];
    }
    public function documentoEliminar(int $docId, ?array $actor = null): void
    {
        $st = $this->db->prepare("SELECT ruta_archivo FROM credito_documentos WHERE id=?");
        $st->execute([$docId]);
        $r = $st->fetch();
        if ($r) {
            Storage::for('creditos')->delete('creditos/' . $r['ruta_archivo']);
            $this->db->prepare("DELETE FROM credito_documentos WHERE id=?")->execute([$docId]);
            Logger::log('eliminar', 'creditos.documento', $actor['id'] ?? null, "Eliminado doc #$docId", null, null, 'warning');
        }
    }
    public function getDocumento(int $docId): ?array
    {
        $st = $this->db->prepare("SELECT * FROM credito_documentos WHERE id=?");
        $st->execute([$docId]);
        return $st->fetch() ?: null;
    }

    /** Historial de cobranza de la cédula de la solicitud (comunicaciones + indagaciones). */
    public function historialCobranza(int $solicitudId): array
    {
        $s = $this->uno($solicitudId);
        return ['cedula' => $s['cedula_asociado'] ?? null, 'numero_solicitud' => $s['numero_solicitud'] ?? null];
    }

    // ── Plan de pagos ────────────────────────────────────────────────────
    /**
     * Plan de pagos alineado con el legacy (descargar_plan_pagos.php):
     * - tasas/seguros de la línea como PERCENTAJE → se convierten a decimal /100.
     * - fechaInicio = fecha_desembolso (o la provista), primerPago = +1 mes.
     * - aportes fijos por cuota (default 65000) más "otros" (bolsillo, fondo_solidario)
     *   de forma opcional, igual que el modal legacy.
     */
    public function planDatos(int $solicitudId, array $opts = []): array
    {
        $s = $this->uno($solicitudId);
        $linea = null;
        if ($s['linea_credito_aprobada_id']) {
            $st = $this->db->prepare("SELECT * FROM credito_lineas WHERE id=?"); $st->execute([$s['linea_credito_aprobada_id']]); $linea = $st->fetch() ?: null;
        }
        $monto = (float) ($s['monto_aprobado'] ?? $s['monto_deseado'] ?? 0);
        $cuotas = (int) ($s['cuotas_aprobadas'] ?? $s['numero_cuotas_deseadas'] ?? 0);
        if ($monto <= 0 || $cuotas <= 0 || !$linea) {
            return ['disponible' => false, 'mensaje' => 'Faltan monto/cuotas/línea para calcular el plan.'];
        }
        // Tasa mensual como decimal: el legacy divide el porcentaje por 100.
        $tasaInteres = (float) $linea['tasa'] / 100;
        $tasaSeguro  = ((float) $linea['seguro_vida'] + (float) $linea['seguro_deudores']) / 100;
        // Fechas: default fecha_desembolso (o hoy) y primer pago +1 mes, alineado con el modal legacy.
        $fechaInicio = trim((string) ($opts['fecha_inicio'] ?? ''));
        if ($fechaInicio === '') $fechaInicio = (string) ($s['fecha_desembolso'] ?? '') ?: date('Y-m-d');
        $primer = trim((string) ($opts['primer_pago'] ?? ''));
        if ($primer === '') $primer = date('Y-m-d', strtotime('+1 month', strtotime($fechaInicio)));
        $aportes = (float) ($opts['aportes'] ?? 65000);
        $otros = (float) ($opts['bolsillo'] ?? 0) + (float) ($opts['fondo_solidario'] ?? 0);
        $aportesTotal = $aportes + $otros;
        $cuotaFija = AmortizacionService::cuotaFija($monto, $tasaInteres, $tasaSeguro, $cuotas, $fechaInicio, $primer);
        $plan = AmortizacionService::calcular($monto, $tasaInteres, $tasaSeguro, $cuotaFija, $aportesTotal, $cuotas, $fechaInicio, $primer);
        $desembolsar = AmortizacionService::valorDesembolsar($monto, (float) $s['base_aportes'], (float) $s['fianza'], (float) $s['prestamos_recoger'], (float) $s['dto_papeleria']);
        return ['disponible' => true, 'cuota_fija' => $cuotaFija, 'aportes' => (int) round($aportesTotal), 'plan' => $plan, 'valor_a_desembolsar' => $desembolsar, 'linea' => $linea];
    }

    public function planPdf(int $solicitudId, array $opts = []): void
    {
        $s = $this->uno($solicitudId);
        $d = $this->planDatos($solicitudId, $opts);
        $nombre = $s['nombre_solicitante'] ?? '';
        require_once dirname(__DIR__, 2) . '/utils/vendor/tcpdf/tcpdf.php';
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(6, 6, 6);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        // Encabezado institucional: logos de Multi y de la cooperativa.
        $root = dirname(__DIR__, 2);
        $logoMulti = $root . '/assets/img/logo.png';
        $logoCoop  = $root . '/assets/img/logo_coomultiunion.png';
        $logoMultiJpg = $root . '/assets/img/logo_pdf.jpg';
        $logoCoopJpg  = $root . '/assets/img/logo_coomultiunion_pdf.jpg';
        // TCPDF necesita GD o Imagick para PNG con canal alfa; si no hay,
        // se usa una versión JPEG aplanada sobre blanco (sin alpha).
        $puedeImagen = extension_loaded('gd') || extension_loaded('imagick');
        if ($puedeImagen && is_file($logoMulti)) {
            $pdf->Image($logoMulti, 6, 5, 14, 0, 'PNG', '', 'T', true, 300, '', false, false, 0, false, false, false);
        } elseif (is_file($logoMultiJpg)) {
            $pdf->Image($logoMultiJpg, 6, 5, 14, 0, 'JPG', '', 'T', true, 300, '', false, false, 0, false, false, false);
        }
        if ($puedeImagen && is_file($logoCoop)) {
            $pdf->Image($logoCoop, 180, 5, 24, 0, 'PNG', '', 'T', true, 300, '', false, false, 0, false, false, false);
        } elseif (is_file($logoCoopJpg)) {
            $pdf->Image($logoCoopJpg, 180, 5, 24, 0, 'JPG', '', 'T', true, 300, '', false, false, 0, false, false, false);
        }

        $pdf->SetY(7);
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->Cell(0, 6, 'PLAN DE PAGOS', 0, 1, 'C');
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->Cell(0, 4, 'Solicitud #' . $s['numero_solicitud'], 0, 1, 'C');

        $pdf->SetY(18);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(0, 4, 'Asociado: ' . $nombre, 0, 1);
        $pdf->Cell(0, 4, 'Cédula: ' . ($s['cedula_asociado'] ?? ''), 0, 1);
        $pdf->Cell(0, 4, 'Monto: $' . number_format((float) ($d['plan']['total_capital'] ?? 0)) . '  ·  Cuota fija: $' . number_format((float) ($d['cuota_fija'] ?? 0)) . ' (+$65.000 aportes)', 0, 1);
        $pdf->Cell(0, 4, 'Fecha de generación: ' . date('d/m/Y'), 0, 1);

        // Tabla compacta: ancho útil 198 mm. El alto de fila se calcula para
        // que todos los planes (hasta 72 cuotas) quepan en una sola página.
        $tabla = $d['plan']['tabla'] ?? [];
        $totalCuotas = count($tabla);
        $rowH = min(4.2, max(2.5, 217 / max(1, $totalCuotas)));
        $fontSize = $rowH >= 3.8 ? 6.2 : ($rowH >= 3.2 ? 5.9 : 5.4);

        $pdf->SetY(34);
        $pdf->SetFont('helvetica', 'B', 5.2);
        $headers = ['N°', 'FECHA', 'SALDO INICIAL', 'INTERÉS', 'SEGURO', 'ABONO CAPITAL', 'CUOTA', 'APORTES', 'CUOTA TOTAL', 'SALDO FINAL'];
        $widths = [9, 18, 23, 20, 17, 26, 21, 19, 23, 22];
        foreach ($headers as $i => $h) {
            $align = $i <= 1 ? 'C' : 'R';
            $pdf->Cell($widths[$i], 5, $h, 1, 0, $align);
        }
        $pdf->Ln();

        $pdf->SetFont('helvetica', '', $fontSize);
        foreach ($tabla as $c) {
            $pdf->Cell(9, $rowH, $c['cuota'], 1, 0, 'C');
            $pdf->Cell(18, $rowH, $c['fecha_pago'], 1, 0, 'C');
            $pdf->Cell(23, $rowH, number_format((float) $c['saldo_inicial']), 1, 0, 'R');
            $pdf->Cell(20, $rowH, number_format((float) $c['interes']), 1, 0, 'R');
            $pdf->Cell(17, $rowH, number_format((float) $c['seguro']), 1, 0, 'R');
            $pdf->Cell(26, $rowH, number_format((float) $c['abono_capital']), 1, 0, 'R');
            $pdf->Cell(21, $rowH, number_format((float) $c['cuota_fija']), 1, 0, 'R');
            $pdf->Cell(19, $rowH, number_format((float) $c['aportes']), 1, 0, 'R');
            $pdf->Cell(23, $rowH, number_format((float) $c['cuota_total']), 1, 0, 'R');
            $pdf->Cell(22, $rowH, number_format((float) $c['saldo_final']), 1, 0, 'R');
            $pdf->Ln();
        }

        // Firma del asociado, inmediatamente debajo de la última cuota.
        $pdf->SetY($pdf->GetY() + 4);
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(60, 4, 'Firma del asociado', 0, 1);
        $pdf->Ln(13);
        $pdf->Cell(70, 1, '', 'T', 1);
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', '', 7.5);
        $pdf->Cell(0, 4, 'Nombre: ' . $nombre, 0, 1);
        $pdf->Cell(0, 4, 'Cédula: ' . ($s['cedula_asociado'] ?? ''), 0, 1);
        $pdf->Cell(0, 4, 'Fecha: ' . date('d/m/Y'), 0, 1);

        if (ob_get_level()) ob_end_clean();
        $pdf->Output('plan_pagos_' . $s['numero_solicitud'] . '.pdf', 'D');
        exit;
    }

    // ── helpers ──────────────────────────────────────────────────────────
    private function uno(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM credito_solicitudes WHERE id=?"); $st->execute([$id]);
        return $st->fetch() ?: null;
    }
    /** Una solicitud en estado terminal (cancelado/rechazado/desembolsado) ya no admite cambios. */
    private function exigirEditable(?array $s): array
    {
        if (!$s || !isset($s['estado'])) throw new DomainException('Solicitud no encontrada.');
        if (in_array($s['estado'], ['cancelado', 'rechazado', 'desembolsado'], true)) {
            throw new DomainException('La solicitud ya no admite cambios en su estado actual.');
        }
        return $s;
    }
    private function generarNumero(string $prefijo): string
    {
        do {
            $num = $prefijo . date('Ymd') . str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $st = $this->db->prepare("SELECT numero_solicitud FROM credito_solicitudes WHERE numero_solicitud=?");
            $st->execute([$num]);
        } while ($st->fetch());
        return $num;
    }
    /** Notifica por email (cola) el cambio de etapa de una solicitud; no rompe el flujo. */
    private function notificar(?string $email, string $mensaje): void
    {
        if (!$email) {
            return;
        }
        EmailCola::encolar('credito', $email, 'Solicitud de crédito — Actualización', EmailTemplates::creditoEstado('', '', $mensaje), null);
    }

    /** Encola una nota de estado (aprobado/rechazado/desembolso) al correo del solicitante. */
    private function notificarNota(int $id, string $mensaje): void
    {
        try {
            $s = $this->uno($id);
            if (!$s || empty($s['correo_electronico'])) {
                return;
            }
            $nombre = (string) ($s['nombre_solicitante'] ?? '');
            $numero = (string) ($s['numero_solicitud'] ?? '');
            EmailCola::encolar(
                'credito',
                trim((string) $s['correo_electronico']),
                'Solicitud de crédito — Actualización',
                EmailTemplates::creditoEstado($nombre, $numero, $mensaje),
                null
            );
        } catch (Throwable $e) {
            error_log('[creditos-email] notificarNota: ' . $e->getMessage());
        }
    }
}