<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/env.php';
require_once dirname(__DIR__) . '/config/ConfigService.php';
require_once dirname(__DIR__) . '/Twilio/TwilioClient.php';
require_once dirname(__DIR__) . '/Twilio/TwilioSignature.php';
require_once dirname(__DIR__) . '/SendGrid/SendGridClient.php';
require_once __DIR__ . '/ContactoService.php';
require_once __DIR__ . '/RemitenteService.php';
require_once __DIR__ . '/PlantillaService.php';
require_once __DIR__ . '/MediaService.php';
require_once __DIR__ . '/CostoService.php';
require_once dirname(__DIR__) . '/Email/EmailTemplates.php';

/**
 * Planes, destinatarios, historial y configuración del módulo.
 * El envío real vive en EnvioService.
 */
final class ComunicacionService
{
    private PDO $db;
    private ConfigService $cfg;
    private ContactoService $contactos;
    private RemitenteService $remitentes;
    private PlantillaService $plantillas;
    private MediaService $media;
    private CostoService $costos;

    private const ORDER_PLANES = ['id', 'nombre', 'canal', 'estado', 'programado_para', 'fecha_creacion', 'costo_total_usd', 'total_destinatarios'];
    private const ORDER_DEST = ['id', 'destino', 'nombre', 'estado', 'fecha_creacion', 'fecha_envio', 'precio_usd'];

    public function __construct(?PDO $db = null, ?ConfigService $cfg = null)
    {
        $this->db  = $db ?? db();
        $this->cfg = $cfg ?? new ConfigService($this->db);
        $this->contactos  = new ContactoService($this->db, $this->cfg);
        $this->remitentes = new RemitenteService($this->db);
        $this->plantillas = new PlantillaService($this->db);
        $this->media      = new MediaService($this->db);
        $this->costos     = new CostoService($this->cfg);
    }

    public function contactos(): ContactoService { return $this->contactos; }
    public function remitentes(): RemitenteService { return $this->remitentes; }
    public function plantillas(): PlantillaService { return $this->plantillas; }
    public function media(): MediaService { return $this->media; }
    public function costos(): CostoService { return $this->costos; }
    public function config(): ConfigService { return $this->cfg; }

    // ── Listados ─────────────────────────────────────────────────────

    public function listarPlanes(array $f): array
    {
        $page = max(0, (int) ($f['page'] ?? 0));
        $size = max(1, min(100, (int) ($f['size'] ?? 20)));
        $offset = $page * $size;
        $order = (string) ($f['order'] ?? 'id');
        if (!in_array($order, self::ORDER_PLANES, true)) { $order = 'id'; }
        $dir = strtoupper((string) ($f['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $where = ['1=1'];
        $params = [];
        if (trim((string) ($f['q'] ?? '')) !== '') {
            $where[] = '(p.nombre LIKE ? OR p.descripcion LIKE ?)';
            $like = '%' . trim((string) $f['q']) . '%';
            $params[] = $like; $params[] = $like;
        }
        foreach (['canal', 'estado', 'tipo'] as $col) {
            if (trim((string) ($f[$col] ?? '')) !== '') {
                $where[] = "p.$col = ?";
                $params[] = trim((string) $f[$col]);
            }
        }
        [$desdeUtc, $hastaUtc] = $this->rangoUtc((string) ($f['desde'] ?? ''), (string) ($f['hasta'] ?? ''));
        if ($desdeUtc !== null) {
            $where[] = 'p.fecha_creacion >= ?';
            $params[] = $desdeUtc;
        }
        if ($hastaUtc !== null) {
            $where[] = 'p.fecha_creacion < ?';
            $params[] = $hastaUtc;
        }
        $wsql = implode(' AND ', $where);

        $st = $this->db->prepare("SELECT COUNT(*) FROM com_planes p WHERE $wsql");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $sql = "SELECT p.*, r.etiqueta AS remitente_etiqueta, r.direccion AS remitente_direccion,
                       t.nombre AS plantilla_nombre, u.nombre_completo AS creado_por_nombre
                  FROM com_planes p
                  LEFT JOIN com_remitentes r ON r.id = p.remitente_id
                  LEFT JOIN com_plantillas t ON t.id = p.plantilla_id
                  LEFT JOIN usuarios u ON u.id = p.creado_por
                 WHERE $wsql
                 ORDER BY p.`$order` $dir
                 LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $size, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        foreach ($rows as &$r) { $this->prepararFechasPlan($r); }
        return ['rows' => $rows, 'total' => $total, 'limit' => $size, 'offset' => $offset];
    }

    public function dashboard(): array
    {
        $tz = new DateTimeZone((string) ($this->cfg->get('com_zona_horaria', 'America/Bogota') ?: 'America/Bogota'));
        $inicio = (new DateTimeImmutable('first day of this month 00:00:00', $tz))
            ->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $st = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(estado IN ('enviado','entregado','leido')) AS enviados,
                SUM(estado = 'entregado' OR estado = 'leido') AS entregados,
                SUM(estado = 'leido') AS leidos,
                SUM(estado = 'fallido') AS fallidos,
                SUM(estado IN ('omitido','cancelado')) AS omitidos,
                COALESCE(SUM(precio_usd), 0) AS costo
             FROM com_destinatarios
             WHERE fecha_creacion >= ?"
        );
        $st->execute([$inicio]);
        $res = $st->fetch() ?: [];
        foreach ($res as $k => $v) { $res[$k] = is_numeric($v) ? (float) $v : (int) $v; }

        $st = $this->db->prepare(
            "SELECT p.*, r.etiqueta AS remitente_etiqueta
               FROM com_planes p LEFT JOIN com_remitentes r ON r.id = p.remitente_id
              WHERE p.estado IN ('programado','en_proceso')
              ORDER BY p.programado_para ASC, p.id ASC LIMIT 8"
        );
        $st->execute();
        $proximos = $st->fetchAll();
        foreach ($proximos as &$p) { $this->prepararFechasPlan($p); }

        $ultimos = $this->historial(['page' => 0, 'size' => 8, 'order' => 'fecha_creacion', 'dir' => 'DESC']);
        require_once dirname(__DIR__) . '/Email/Mailer.php';

        return [
            'mes' => $res,
            'planes_proximos' => $proximos,
            'ultimos' => $ultimos['rows'],
            'twilio_configurado' => TwilioClient::fromEnv()->isConfigured(),
            'smtp_configurado' => Mailer::isConfigured(),
            'sendgrid_configurado' => SendGridClient::fromEnv()->isConfigured(),
            'trial' => (string) env('TWILIO_TRIAL', '1') === '1',
            'dry_run' => (string) env('TWILIO_DRY_RUN', '0') === '1',
            'auth_token_configurado' => trim((string) env('TWILIO_AUTH_TOKEN', '')) !== '',
            'moneda' => 'USD',
        ];
    }

    public function historial(array $f): array
    {
        $page = max(0, (int) ($f['page'] ?? 0));
        $size = max(1, min(100, (int) ($f['size'] ?? 20)));
        $offset = $page * $size;
        $order = (string) ($f['order'] ?? 'fecha_creacion');
        if (!in_array($order, self::ORDER_DEST, true)) { $order = 'fecha_creacion'; }
        $dir = strtoupper((string) ($f['dir'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $where = ['1=1'];
        $params = [];
        if (trim((string) ($f['q'] ?? '')) !== '') {
            $where[] = '(d.destino LIKE ? OR d.nombre LIKE ? OR d.asociado_cedula LIKE ? OR p.nombre LIKE ?)';
            $like = '%' . trim((string) $f['q']) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        foreach (['canal', 'estado'] as $col) {
            if (trim((string) ($f[$col] ?? '')) !== '') {
                $where[] = "d.$col = ?";
                $params[] = trim((string) $f[$col]);
            }
        }
        if ((int) ($f['plan_id'] ?? 0) > 0) {
            $where[] = 'd.plan_id = ?';
            $params[] = (int) $f['plan_id'];
        }
        [$desdeUtc, $hastaUtc] = $this->rangoUtc((string) ($f['desde'] ?? ''), (string) ($f['hasta'] ?? ''));
        if ($desdeUtc !== null) {
            $where[] = 'd.fecha_creacion >= ?';
            $params[] = $desdeUtc;
        }
        if ($hastaUtc !== null) {
            $where[] = 'd.fecha_creacion < ?';
            $params[] = $hastaUtc;
        }
        $wsql = implode(' AND ', $where);
        $from = "FROM com_destinatarios d
                 JOIN com_planes p ON p.id = d.plan_id
                 LEFT JOIN com_remitentes r ON r.id = p.remitente_id";

        $st = $this->db->prepare("SELECT COUNT(*) $from WHERE $wsql");
        $st->execute($params);
        $total = (int) $st->fetchColumn();

        $sql = "SELECT d.*, p.nombre AS plan_nombre, p.tipo AS plan_tipo, p.programado_para,
                       r.etiqueta AS remitente_etiqueta
                $from WHERE $wsql
                ORDER BY d.`$order` $dir, d.id DESC
                LIMIT ? OFFSET ?";
        $st = $this->db->prepare($sql);
        foreach ($params as $i => $v) { $st->bindValue($i + 1, $v); }
        $st->bindValue(count($params) + 1, $size, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        foreach ($rows as &$r) { $this->prepararFechasDest($r); }
        return ['rows' => $rows, 'total' => $total, 'limit' => $size, 'offset' => $offset];
    }

    /** Exportación completa (sin paginación) con los mismos filtros del historial. */
    public function historialExportar(array $f): array
    {
        $where = ['1=1'];
        $params = [];
        if (trim((string) ($f['q'] ?? '')) !== '') {
            $where[] = '(d.destino LIKE ? OR d.nombre LIKE ? OR d.asociado_cedula LIKE ? OR p.nombre LIKE ?)';
            $like = '%' . trim((string) $f['q']) . '%';
            array_push($params, $like, $like, $like, $like);
        }
        foreach (['canal', 'estado'] as $col) {
            if (trim((string) ($f[$col] ?? '')) !== '') {
                $where[] = "d.$col = ?";
                $params[] = trim((string) $f[$col]);
            }
        }
        if ((int) ($f['plan_id'] ?? 0) > 0) {
            $where[] = 'd.plan_id = ?';
            $params[] = (int) $f['plan_id'];
        }
        [$desdeUtc, $hastaUtc] = $this->rangoUtc((string) ($f['desde'] ?? ''), (string) ($f['hasta'] ?? ''));
        if ($desdeUtc !== null) {
            $where[] = 'd.fecha_creacion >= ?';
            $params[] = $desdeUtc;
        }
        if ($hastaUtc !== null) {
            $where[] = 'd.fecha_creacion < ?';
            $params[] = $hastaUtc;
        }
        $sql = "SELECT d.fecha_creacion, p.nombre AS plan_nombre, p.canal, d.destino, d.nombre,
                       d.asociado_cedula, d.estado, d.motivo_omitido, d.proveedor_message_id,
                       d.error_code, d.error_message, d.num_segmentos, d.precio_usd, d.precio_moneda,
                       d.fecha_envio, d.fecha_entrega, d.fecha_lectura
                  FROM com_destinatarios d
                  JOIN com_planes p ON p.id = d.plan_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY d.id DESC
                 LIMIT 50000";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function detallePlan(int $id): array
    {
        $st = $this->db->prepare(
            "SELECT p.*, r.etiqueta AS remitente_etiqueta, r.direccion AS remitente_direccion,
                    t.nombre AS plantilla_nombre, u.nombre_completo AS creado_por_nombre
               FROM com_planes p
               LEFT JOIN com_remitentes r ON r.id = p.remitente_id
               LEFT JOIN com_plantillas t ON t.id = p.plantilla_id
               LEFT JOIN usuarios u ON u.id = p.creado_por
              WHERE p.id = ? LIMIT 1"
        );
        $st->execute([$id]);
        $plan = $st->fetch();
        if (!$plan) {
            throw new DomainException('Plan no encontrado.');
        }
        $this->prepararFechasPlan($plan);

        $st = $this->db->prepare(
            "SELECT estado, COUNT(*) AS n FROM com_destinatarios WHERE plan_id = ? GROUP BY estado"
        );
        $st->execute([$id]);
        $resumen = [];
        foreach ($st->fetchAll() as $r) { $resumen[(string) $r['estado']] = (int) $r['n']; }

        $st = $this->db->prepare(
            "SELECT d.*, c.opt_in, c.opt_out
               FROM com_destinatarios d
               LEFT JOIN com_contactos c ON c.id = d.contacto_id
              WHERE d.plan_id = ?
              ORDER BY d.id ASC LIMIT 2000"
        );
        $st->execute([$id]);
        $destinatarios = $st->fetchAll();
        foreach ($destinatarios as &$d) { $this->prepararFechasDest($d); }

        $st = $this->db->prepare(
            "SELECT * FROM com_eventos WHERE plan_id = ? ORDER BY id DESC LIMIT 300"
        );
        $st->execute([$id]);
        $eventos = $st->fetchAll();

        return ['plan' => $plan, 'resumen_estados' => $resumen, 'destinatarios' => $destinatarios, 'eventos' => $eventos];
    }

    public function detalleMensaje(int $id): array
    {
        $st = $this->db->prepare(
            "SELECT d.*, p.nombre AS plan_nombre, p.canal AS plan_canal, p.cuerpo AS plan_cuerpo,
                    p.cuerpo_html AS plan_cuerpo_html, p.asunto AS plan_asunto, p.programado_para,
                    p.zona_horaria, p.tipo AS plan_tipo
               FROM com_destinatarios d JOIN com_planes p ON p.id = d.plan_id
              WHERE d.id = ? LIMIT 1"
        );
        $st->execute([$id]);
        $msg = $st->fetch();
        if (!$msg) {
            throw new DomainException('Mensaje no encontrado.');
        }
        $this->prepararFechasDest($msg);
        $st = $this->db->prepare("SELECT * FROM com_eventos WHERE destinatario_id = ? ORDER BY id DESC LIMIT 100");
        $st->execute([$id]);
        $eventos = $st->fetchAll();
        $st = $this->db->prepare(
            "SELECT * FROM com_entrantes WHERE de = ? ORDER BY id DESC LIMIT 20"
        );
        $st->execute([(string) $msg['destino']]);
        return ['mensaje' => $msg, 'eventos' => $eventos, 'entrantes' => $st->fetchAll()];
    }

    // ── Crear / editar planes ───────────────────────────────────────

    /**
     * @param array<int,array<string,mixed>> $rows filas validadas (BaseImportService)
     * @param array<string,mixed> $resumen resumen de importación
     */
    public function crearPlan(array $data, array $rows, array $resumen = [], ?array $actor = null): int
    {
        $canal = trim((string) ($data['canal'] ?? ''));
        if (!in_array($canal, ['sms', 'whatsapp', 'email'], true)) {
            throw new DomainException('Canal inválido.');
        }
        $tipo = trim((string) ($data['tipo'] ?? 'masiva'));
        if (!in_array($tipo, ['masiva', 'individual', 'prueba'], true)) { $tipo = 'masiva'; }
        $proveedor = trim((string) ($data['proveedor'] ?? ($canal === 'email' ? (SendGridClient::fromEnv()->isConfigured() ? 'sendgrid' : 'smtp') : 'twilio')));
        if (!in_array($proveedor, ['twilio', 'sendgrid', 'smtp'], true)) { $proveedor = 'twilio'; }
        if ($canal === 'email') { $proveedor = 'smtp'; }
        if ($canal !== 'email' && $proveedor !== 'twilio') { $proveedor = 'twilio'; }

        $remitente = $this->resolverRemitente((int) ($data['remitente_id'] ?? 0), $canal);
        $plantilla = null;
        if ((int) ($data['plantilla_id'] ?? 0) > 0) {
            $plantilla = $this->plantillas->obtener((int) $data['plantilla_id']);
            if (!$plantilla) { throw new DomainException('Plantilla no encontrada.'); }
            if ((string) $plantilla['canal'] !== $canal) { throw new DomainException('La plantilla no corresponde al canal.'); }
            if ($canal === 'whatsapp' && (string) $plantilla['estado_aprobacion'] !== 'approved') {
                throw new DomainException('La plantilla de WhatsApp no está aprobada.');
            }
        }

        $modo = trim((string) ($data['modo_contenido'] ?? ($plantilla ? 'plantilla' : 'texto')));
        if (!in_array($modo, ['texto', 'plantilla'], true)) { $modo = 'texto'; }
        $cuerpo = (string) ($data['cuerpo'] ?? '');
        if ($plantilla && $modo === 'plantilla' && trim($cuerpo) === '') {
            $cuerpo = (string) ($plantilla['cuerpo'] ?? '');
        }
        $asunto = mb_substr(trim((string) ($data['asunto'] ?? ($plantilla['asunto'] ?? ''))), 0, 255);
        $html = (string) ($data['cuerpo_html'] ?? '');
        $mediaIds = $this->ids($data['media_ids'] ?? []);
        $variables = $this->jsonArray($data['variables_map'] ?? $data['variables_json'] ?? []);
        $this->validarContenido($canal, $modo, $cuerpo, $asunto, $html, $mediaIds, $plantilla);
        $html = $this->estilizarHtmlEmail($html);
        $this->validarVariablesPlantilla($plantilla, $modo, $variables);
        if (!$remitente) {
            // En borrador se permite; al programar se vuelve a exigir.
        }

        $zona = trim((string) ($data['zona_horaria'] ?? $this->cfg->get('com_zona_horaria', 'America/Bogota')));
        if ($zona === '') { $zona = 'America/Bogota'; }
        $programarAhora = (int) ($data['programar_ahora'] ?? 0) === 1 || (string) ($data['modo_envio'] ?? '') === 'ahora';
        $programadoPara = null;
        if ($programarAhora) {
            $programadoPara = gmdate('Y-m-d H:i:s');
        } elseif (trim((string) ($data['programado_para'] ?? '')) !== '') {
            $programadoPara = $this->toUtc((string) $data['programado_para'], $zona);
        }
        $estado = $programadoPara !== null ? 'programado' : 'borrador';
        if ($programadoPara !== null && !$remitente) {
            throw new DomainException('Debe configurar un remitente activo antes de programar.');
        }

        $validRows = [];
        foreach ($rows as $r) {
            if (($r['estado'] ?? 'ok') === 'ok' && trim((string) ($r['destino'] ?? '')) !== '') {
                $validRows[] = $r;
            }
        }
        if (!$validRows) {
            throw new DomainException('No hay destinatarios válidos para el plan. Revise la previsualización y el opt-in.');
        }

        $mediaCount = count($mediaIds);
        $estimado = $this->costos->estimarPlan($canal, $modo === 'plantilla' ? (string) ($plantilla['cuerpo'] ?? '') : $cuerpo, count($validRows), $mediaCount);
        $omitidos = max(0, (int) ($resumen['total'] ?? count($rows)) - count($validRows));
        $iaCosto = max(0.0, (float) ($data['ia_costo_usd'] ?? 0));
        $iaModelo = mb_substr(trim((string) ($data['ia_modelo'] ?? '')), 0, 80) ?: null;
        $iaTokensPrompt = isset($data['ia_tokens_prompt']) ? max(0, (int) $data['ia_tokens_prompt']) : null;
        $iaTokensCompletion = isset($data['ia_tokens_completion']) ? max(0, (int) $data['ia_tokens_completion']) : null;
        $iaGeneraciones = max(0, (int) ($data['ia_generaciones'] ?? 0));

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO com_planes
                    (nombre, descripcion, tipo, canal, proveedor, remitente_id, plantilla_id, proveedor_template_id,
                     modo_contenido, asunto, cuerpo, cuerpo_html, media_json, variables_json, origen, filtros_json,
                     programado_para, zona_horaria, estado, total_destinatarios, total_omitidos,
                     costo_estimado_usd, ia_costo_usd, ia_modelo, ia_tokens_prompt, ia_tokens_completion,
                     ia_generaciones, moneda, creado_por, programado_por, error_resumen)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'USD', ?,?,?)"
            )->execute([
                mb_substr(trim((string) ($data['nombre'] ?? 'Comunicación')), 0, 160) ?: 'Comunicación',
                trim((string) ($data['descripcion'] ?? '')) ?: null,
                $tipo, $canal, $proveedor,
                $remitente['id'] ?? null,
                $plantilla['id'] ?? null,
                $plantilla['proveedor_template_id'] ?? null,
                $modo, $asunto ?: null, $cuerpo ?: null, $html ?: null,
                json_encode($mediaIds),
                json_encode($variables, JSON_UNESCAPED_UNICODE),
                trim((string) ($data['origen'] ?? 'manual')) ?: 'manual',
                isset($data['filtros']) ? json_encode($data['filtros'], JSON_UNESCAPED_UNICODE) : null,
                $programadoPara, $zona, $estado,
                count($validRows), $omitidos, $estimado,
                $iaCosto, $iaModelo, $iaTokensPrompt, $iaTokensCompletion, $iaGeneraciones,
                $actor['id'] ?? null,
                $programadoPara !== null ? ($actor['id'] ?? null) : null,
                $resumen ? json_encode($resumen, JSON_UNESCAPED_UNICODE) : null,
            ]);
            $planId = (int) $this->db->lastInsertId();

            $ins = $this->db->prepare(
                "INSERT IGNORE INTO com_destinatarios
                    (plan_id, contacto_id, asociado_cedula, nombre, destino, canal, variables_json, estado)
                 VALUES (?,?,?,?,?,?,?, 'pendiente')"
            );
            foreach ($validRows as $r) {
                $destino = (string) $r['destino'];
                $contactoId = (int) ($r['contacto_id'] ?? 0);
                if ($contactoId <= 0) {
                    $contactoId = $this->contactos->upsert($canal, $destino, [
                        'nombre' => $r['nombre'] ?? null,
                        'asociado_cedula' => $r['cedula'] ?? null,
                    ]);
                }
                if (!empty($r['opt_in'])) {
                    $cedulaRow = trim((string) ($r['cedula'] ?? ''));
                    if ($cedulaRow !== '') {
                        try {
                            $this->contactos->setOptInAsociado($cedulaRow, true, $actor);
                        } catch (DomainException $e) {
                            $this->contactos->registrarOptIn($canal, $destino, 'carga', 'Importación de base', $actor);
                        }
                    } else {
                        $this->contactos->registrarOptIn($canal, $destino, 'carga', 'Importación de base', $actor);
                    }
                }
                if (!empty($r['opt_out'])) {
                    $this->contactos->registrarOptOut($canal, $destino, 'manual', 'Marcado en base', $actor);
                }
                $ins->execute([
                    $planId, $contactoId ?: null,
                    mb_substr(trim((string) ($r['cedula'] ?? '')), 0, 20) ?: null,
                    mb_substr(trim((string) ($r['nombre'] ?? '')), 0, 150) ?: null,
                    $destino, $canal,
                    json_encode($r['variables'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        Logger::log('crear', 'comunicaciones.plan', $actor['id'] ?? null,
            "Plan #$planId ($canal/$tipo) · " . count($validRows) . " destinatarios · estado=$estado");
        $this->recalcularPlan($planId);
        return $planId;
    }

    public function actualizarPlan(int $id, array $data, ?array $actor = null): void
    {
        $plan = $this->planCrudo($id);
        if (!in_array((string) $plan['estado'], ['borrador', 'programado'], true)) {
            throw new DomainException('Solo se pueden editar planes en borrador o programados.');
        }
        $canal = (string) $plan['canal'];
        $remitente = $this->resolverRemitente((int) ($data['remitente_id'] ?? $plan['remitente_id']), $canal);
        $plantilla = null;
        $plantillaId = (int) ($data['plantilla_id'] ?? $plan['plantilla_id']);
        if ($plantillaId > 0) {
            $plantilla = $this->plantillas->obtener($plantillaId);
            if (!$plantilla) { throw new DomainException('Plantilla no encontrada.'); }
        }
        $modo = (string) ($data['modo_contenido'] ?? $plan['modo_contenido']);
        $cuerpo = (string) ($data['cuerpo'] ?? $plan['cuerpo']);
        if ($plantilla && $modo === 'plantilla' && trim($cuerpo) === '') { $cuerpo = (string) $plantilla['cuerpo']; }
        $asunto = mb_substr((string) ($data['asunto'] ?? $plan['asunto']), 0, 255);
        $html = (string) ($data['cuerpo_html'] ?? $plan['cuerpo_html']);
        $mediaIds = array_key_exists('media_ids', $data) ? $this->ids($data['media_ids']) : $this->ids($plan['media_json'] ?? []);
        $this->validarContenido($canal, $modo, $cuerpo, $asunto, $html, $mediaIds, $plantilla);
        $html = $this->estilizarHtmlEmail($html);

        $zona = (string) ($data['zona_horaria'] ?? $plan['zona_horaria']);
        $programadoPara = $plan['programado_para'];
        if (array_key_exists('programado_para', $data)) {
            $programadoPara = trim((string) $data['programado_para']) !== ''
                ? $this->toUtc((string) $data['programado_para'], $zona)
                : null;
        }
        $estado = $programadoPara !== null ? 'programado' : 'borrador';
        if ($estado === 'programado' && !$remitente) {
            throw new DomainException('Debe configurar un remitente activo antes de programar.');
        }
        $variables = array_key_exists('variables_map', $data)
            ? $this->jsonArray($data['variables_map'])
            : $this->jsonArray($plan['variables_json'] ?? []);
        $this->validarVariablesPlantilla($plantilla, $modo, $variables);

        $this->db->prepare(
            "UPDATE com_planes SET
                nombre=?, descripcion=?, remitente_id=?, plantilla_id=?, proveedor_template_id=?,
                modo_contenido=?, asunto=?, cuerpo=?, cuerpo_html=?, media_json=?, variables_json=?,
                programado_para=?, zona_horaria=?, estado=?, fecha_actualizacion=NOW()
              WHERE id=?"
        )->execute([
            mb_substr(trim((string) ($data['nombre'] ?? $plan['nombre'])), 0, 160) ?: $plan['nombre'],
            trim((string) ($data['descripcion'] ?? $plan['descripcion'])) ?: null,
            $remitente['id'] ?? null,
            $plantilla['id'] ?? null,
            $plantilla['proveedor_template_id'] ?? null,
            $modo, $asunto ?: null, $cuerpo ?: null, $html ?: null,
            json_encode($mediaIds), json_encode($variables, JSON_UNESCAPED_UNICODE),
            $programadoPara, $zona, $estado, $id,
        ]);
        Logger::log('editar', 'comunicaciones.plan', $actor['id'] ?? null, "Plan #$id editado");
        $this->recalcularPlan($id);
    }

    public function programarPlan(int $id, ?string $fecha, string $modo, ?array $actor = null): array
    {
        $plan = $this->planCrudo($id);
        if (!in_array((string) $plan['estado'], ['borrador', 'programado'], true)) {
            throw new DomainException('El plan no se puede programar en su estado actual.');
        }
        $remitente = $this->resolverRemitente((int) ($plan['remitente_id'] ?? 0), (string) $plan['canal']);
        if (!$remitente) {
            throw new DomainException('Configure un remitente activo antes de programar.');
        }
        $plantilla = (int) ($plan['plantilla_id'] ?? 0) > 0 ? $this->plantillas->obtener((int) $plan['plantilla_id']) : null;
        $this->validarContenido(
            (string) $plan['canal'], (string) $plan['modo_contenido'], (string) ($plan['cuerpo'] ?? ''),
            (string) ($plan['asunto'] ?? ''), (string) ($plan['cuerpo_html'] ?? ''),
            $this->ids($plan['media_json'] ?? []), $plantilla
        );
        $this->validarVariablesPlantilla($plantilla, (string) $plan['modo_contenido'], $this->jsonArray($plan['variables_json'] ?? []));
        $st = $this->db->prepare("SELECT COUNT(*) FROM com_destinatarios WHERE plan_id = ? AND estado = 'pendiente'");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() === 0) {
            throw new DomainException('El plan no tiene destinatarios pendientes.');
        }

        $zona = (string) ($plan['zona_horaria'] ?: 'America/Bogota');
        $programadoPara = $modo === 'ahora' || trim((string) $fecha) === ''
            ? gmdate('Y-m-d H:i:s')
            : $this->toUtc((string) $fecha, $zona);

        $this->db->prepare(
            "UPDATE com_planes
                SET estado='programado', programado_para=?, programado_por=?, fecha_actualizacion=NOW(), error_resumen=NULL
              WHERE id=?"
        )->execute([$programadoPara, $actor['id'] ?? null, $id]);
        Logger::log('programar', 'comunicaciones.plan', $actor['id'] ?? null,
            "Plan #$id programado para $programadoPara UTC");

        return $this->detallePlan($id)['plan'];
    }

    public function cancelarPlan(int $id, ?array $actor = null): void
    {
        $plan = $this->planCrudo($id);
        if (in_array((string) $plan['estado'], ['completado', 'cancelado'], true)) {
            throw new DomainException('El plan ya está finalizado.');
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "UPDATE com_destinatarios
                    SET estado='cancelado', motivo_omitido='plan cancelado', fecha_actualizacion=NOW()
                  WHERE plan_id = ? AND estado IN ('pendiente','enviando') AND proveedor_message_id IS NULL"
            )->execute([$id]);
            $this->db->prepare(
                "UPDATE com_planes SET estado='cancelado', fecha_cancelacion=NOW(), cancelado_por=?, fecha_actualizacion=NOW()
                  WHERE id=?"
            )->execute([$actor['id'] ?? null, $id]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Logger::log('cancelar', 'comunicaciones.plan', $actor['id'] ?? null, "Plan #$id cancelado", null, null, 'warning');
        $this->recalcularPlan($id);
    }

    public function duplicarPlan(int $id, ?array $actor = null): int
    {
        $plan = $this->planCrudo($id);
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO com_planes
                    (nombre, descripcion, tipo, canal, proveedor, remitente_id, plantilla_id, proveedor_template_id,
                     modo_contenido, asunto, cuerpo, cuerpo_html, media_json, variables_json, origen, filtros_json,
                     zona_horaria, estado, total_destinatarios, costo_estimado_usd, moneda, creado_por)
                 SELECT CONCAT(nombre, ' (copia)'), descripcion, tipo, canal, proveedor, remitente_id, plantilla_id,
                        proveedor_template_id, modo_contenido, asunto, cuerpo, cuerpo_html, media_json, variables_json,
                        origen, filtros_json, zona_horaria, 'borrador', 0, costo_estimado_usd, moneda, ?
                   FROM com_planes WHERE id = ?"
            )->execute([$actor['id'] ?? null, $id]);
            $newId = (int) $this->db->lastInsertId();
            $this->db->prepare(
                "INSERT INTO com_destinatarios
                    (plan_id, contacto_id, asociado_cedula, nombre, destino, canal, variables_json, estado)
                 SELECT ?, contacto_id, asociado_cedula, nombre, destino, canal, variables_json, 'pendiente'
                   FROM com_destinatarios WHERE plan_id = ?"
            )->execute([$newId, $id]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Logger::log('duplicar', 'comunicaciones.plan', $actor['id'] ?? null, "Plan #$id duplicado como #$newId");
        $this->recalcularPlan($newId);
        return $newId;
    }

    public function eliminarPlan(int $id, ?array $actor = null): void
    {
        $plan = $this->planCrudo($id);
        if (!in_array((string) $plan['estado'], ['borrador', 'cancelado'], true)) {
            throw new DomainException('Solo se pueden eliminar planes en borrador o cancelados.');
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM com_eventos WHERE plan_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM com_destinatarios WHERE plan_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM com_planes WHERE id = ?")->execute([$id]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Logger::log('eliminar', 'comunicaciones.plan', $actor['id'] ?? null, "Plan #$id eliminado", null, null, 'warning');
    }

    public function recalcularPlan(int $id): void
    {
        $st = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(estado IN ('enviado','entregado','leido')) AS enviados,
                SUM(estado IN ('entregado','leido')) AS entregados,
                SUM(estado = 'leido') AS leidos,
                SUM(estado = 'fallido') AS fallidos,
                SUM(estado IN ('omitido','cancelado')) AS omitidos,
                COALESCE(SUM(precio_usd), 0) AS costo
             FROM com_destinatarios WHERE plan_id = ?"
        );
        $st->execute([$id]);
        $r = $st->fetch() ?: [];
        // Las filas descartadas en la importación (sin opt-in, inválidas,
        // duplicadas) no viven en com_destinatarios; se conservan en el
        // resumen del plan para que el total de omitidos no se pierda al recalcular.
        $stp = $this->db->prepare("SELECT error_resumen FROM com_planes WHERE id = ? LIMIT 1");
        $stp->execute([$id]);
        $resumen = json_decode((string) ($stp->fetchColumn() ?: ''), true);
        $omitidosImportacion = is_array($resumen)
            ? ((int) ($resumen['omitido'] ?? 0) + (int) ($resumen['error'] ?? 0))
            : 0;

        $this->db->prepare(
            "UPDATE com_planes SET
                total_destinatarios=?, total_enviados=?, total_entregados=?, total_leidos=?,
                total_fallidos=?, total_omitidos=?, costo_total_usd=?, fecha_actualizacion=NOW()
              WHERE id=?"
        )->execute([
            (int) ($r['total'] ?? 0),
            (int) ($r['enviados'] ?? 0),
            (int) ($r['entregados'] ?? 0),
            (int) ($r['leidos'] ?? 0),
            (int) ($r['fallidos'] ?? 0),
            (int) ($r['omitidos'] ?? 0) + $omitidosImportacion,
            (float) ($r['costo'] ?? 0),
            $id,
        ]);
        $this->finalizarPlanSiCorresponde($id);
    }

    public function finalizarPlanSiCorresponde(int $id): void
    {
        $st = $this->db->prepare("SELECT estado FROM com_planes WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $estado = (string) ($st->fetchColumn() ?: '');
        if (!in_array($estado, ['en_proceso', 'programado'], true)) {
            return;
        }
        $st = $this->db->prepare(
            "SELECT SUM(estado IN ('pendiente','enviando')) AS abiertos,
                    SUM(estado IN ('enviado','entregado','leido')) AS ok,
                    SUM(estado = 'fallido') AS fail
               FROM com_destinatarios WHERE plan_id = ?"
        );
        $st->execute([$id]);
        $r = $st->fetch() ?: [];
        if ((int) ($r['abiertos'] ?? 0) > 0) {
            return;
        }
        $ok = (int) ($r['ok'] ?? 0);
        $fail = (int) ($r['fail'] ?? 0);
        if ($ok > 0 && $fail === 0) {
            $final = 'completado';
        } elseif ($ok > 0) {
            $final = 'parcial';
        } elseif ($fail > 0) {
            $final = 'fallido';
        } else {
            $final = 'completado'; // todo omitido (opt-out / simulación / filtros)
        }
        $this->db->prepare(
            "UPDATE com_planes SET estado=?, fecha_fin=NOW(), fecha_actualizacion=NOW() WHERE id=?"
        )->execute([$final, $id]);
    }

    // ── Configuración ───────────────────────────────────────────────

    public function configuracion(): array
    {
        $keys = [
            'com_hora_inicio', 'com_hora_fin', 'com_zona_horaria', 'com_limite_mps', 'com_lote',
            'com_reintentos', 'com_consentimiento_obligatorio', 'com_tarifa_sms_usd',
            'com_tarifa_whatsapp_usd', 'com_tarifa_email_usd', 'com_max_destinatarios',
            'com_optout_keywords', 'com_optin_keywords', 'com_ayuda_texto',
        ];
        $config = [];
        foreach ($keys as $k) {
            $config[$k] = $this->cfg->get($k, '');
        }
        require_once dirname(__DIR__) . '/Email/Mailer.php';
        return [
            'config' => $config,
            'smtp' => [
                'configurado' => Mailer::isConfigured(),
                'disponible' => Mailer::disponible(),
                'host' => Mailer::host(),
            ],
            'twilio' => [
                'configurado' => TwilioClient::fromEnv()->isConfigured(),
                'trial' => (string) env('TWILIO_TRIAL', '1') === '1',
                'dry_run' => (string) env('TWILIO_DRY_RUN', '0') === '1',
                'status_callback_url' => TwilioSignature::statusUrl(),
                'inbound_callback_url' => TwilioSignature::inboundUrl(),
                'validate_signature' => TwilioSignature::validationEnabled(),
                'auth_token_configurado' => trim((string) env('TWILIO_AUTH_TOKEN', '')) !== '',
            ],
            'sendgrid' => [
                'configurado' => SendGridClient::fromEnv()->isConfigured(),
            ],
            'remitentes' => $this->remitentes->listar(),
            'moneda' => 'USD',
        ];
    }

    public function guardarConfiguracion(array $valores, ?array $actor = null): void
    {
        $permitidas = [
            'com_hora_inicio', 'com_hora_fin', 'com_zona_horaria', 'com_limite_mps', 'com_lote',
            'com_reintentos', 'com_consentimiento_obligatorio', 'com_tarifa_sms_usd',
            'com_tarifa_whatsapp_usd', 'com_tarifa_email_usd', 'com_max_destinatarios',
            'com_optout_keywords', 'com_optin_keywords', 'com_ayuda_texto',
        ];
        $cambios = [];
        foreach ($permitidas as $k) {
            if (!array_key_exists($k, $valores)) { continue; }
            $v = trim((string) $valores[$k]);
            if (in_array($k, ['com_hora_inicio', 'com_hora_fin'], true) && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v)) {
                throw new DomainException("Hora inválida para $k (use HH:MM).");
            }
            if (in_array($k, ['com_limite_mps', 'com_lote', 'com_reintentos', 'com_max_destinatarios'], true)) {
                if ($v === '' || !ctype_digit($v) || (int) $v < 1) { throw new DomainException("Valor inválido para $k."); }
            }
            if (str_starts_with($k, 'com_tarifa_') && ($v === '' || !is_numeric($v) || (float) $v < 0)) {
                throw new DomainException("Tarifa inválida para $k.");
            }
            if ($k === 'com_consentimiento_obligatorio') { $v = ((int) $v === 1) ? '1' : '0'; }
            $this->cfg->set($k, $v);
            $cambios[] = "$k=$v";
        }
        Logger::log('editar', 'comunicaciones.configuracion', $actor['id'] ?? null,
            'Parámetros actualizados: ' . implode(', ', $cambios));
    }

    // ── Auxiliares ──────────────────────────────────────────────────

    public function planCrudo(int $id): array
    {
        $st = $this->db->prepare("SELECT * FROM com_planes WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $p = $st->fetch();
        if (!$p) { throw new DomainException('Plan no encontrado.'); }
        return $p;
    }

    public function resolverRemitente(int $id, string $canal): ?array
    {
        if ($id > 0) {
            $r = $this->remitentes->obtener($id);
            if ($r && (string) $r['canal'] === $canal && (int) $r['activo'] === 1) { return $r; }
        }
        return $this->remitentes->predeterminado($canal);
    }

    private function validarContenido(string $canal, string $modo, string $cuerpo, string $asunto, string $html, array $mediaIds, ?array $plantilla): void
    {
        if ($canal === 'email') {
            if (trim($asunto) === '') { throw new DomainException('El asunto del correo es obligatorio.'); }
            if (trim($cuerpo) === '' && trim($html) === '') { throw new DomainException('El cuerpo del correo es obligatorio.'); }
            if (trim($html) !== '' && substr_count($html, '<') !== substr_count($html, '>')) {
                throw new DomainException('El HTML del correo está incompleto (hay etiquetas sin cerrar). Corríjalo o vuelva a generarlo.');
            }
        } else {
            if ($modo === 'plantilla') {
                if (!$plantilla) { throw new DomainException('Seleccione una plantilla.'); }
                if ($canal === 'whatsapp' && (string) $plantilla['estado_aprobacion'] !== 'approved') {
                    throw new DomainException('La plantilla de WhatsApp no está aprobada.');
                }
            } elseif (trim($cuerpo) === '') {
                throw new DomainException('El mensaje es obligatorio.');
            }
        }
        if ($canal === 'sms' && $mediaIds) {
            throw new DomainException('El SMS en Colombia no admite adjuntos. Use WhatsApp para imágenes/PDF.');
        }
        if ($canal === 'whatsapp' && count($mediaIds) > 10) {
            throw new DomainException('WhatsApp admite máximo 10 adjuntos por mensaje.');
        }
        if ($canal === 'email' && count($mediaIds) > 10) {
            throw new DomainException('El correo admite máximo 10 adjuntos.');
        }
    }

    /** Envuelve HTML de email en el diseño corporativo si aún no es un documento. */
    private function estilizarHtmlEmail(string $html): string
    {
        $html = trim($html);
        if ($html === '' || stripos($html, '<html') !== false || stripos($html, '<body') !== false) {
            return $html;
        }
        return EmailTemplates::base('Comunicación Coomultiunion', $html);
    }

    private function validarVariablesPlantilla(?array $plantilla, string $modo, array $variables): void
    {
        if (!$plantilla || $modo !== 'plantilla') {
            return;
        }
        foreach ($this->plantillas->variablesDe($plantilla) as $v) {
            // Las variables numéricas de Twilio Content ({{1}}, {{2}}...) deben
            // tener un mapeo explícito; las nombradas pueden resolverse solas.
            if (ctype_digit((string) $v) && !array_key_exists((string) $v, $variables)) {
                throw new DomainException('Falta mapear la variable {{' . $v . '}} de la plantilla.');
            }
        }
    }

    /** Convierte un rango de fechas locales (Bogotá) a límites UTC. */
    private function rangoUtc(string $desde, string $hasta): array
    {
        try {
            $tz = new DateTimeZone((string) ($this->cfg->get('com_zona_horaria', 'America/Bogota') ?: 'America/Bogota'));
            $desde = trim($desde);
            $hasta = trim($hasta);
            $ini = $desde !== ''
                ? (new DateTimeImmutable($desde . ' 00:00:00', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                : null;
            $fin = $hasta !== ''
                ? (new DateTimeImmutable($hasta . ' 00:00:00', $tz))->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                : null;
            return [$ini, $fin];
        } catch (Throwable $e) {
            throw new DomainException('Rango de fechas inválido.');
        }
    }

    private function toUtc(string $fecha, string $zona): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') { throw new DomainException('Fecha de programación vacía.'); }
        try {
            $tz = new DateTimeZone($zona !== '' ? $zona : 'America/Bogota');
            $dt = new DateTimeImmutable($fecha, $tz);
            $dt = $dt->setTimezone(new DateTimeZone('UTC'));
            return $dt->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
            throw new DomainException('Fecha de programación inválida.');
        }
    }

    private function prepararFechasPlan(array &$r): void
    {
        $r['programado_para_utc'] = $r['programado_para'] ?? null;
        if (!empty($r['programado_para'])) {
            try {
                $dt = new DateTimeImmutable((string) $r['programado_para'], new DateTimeZone('UTC'));
                $dt = $dt->setTimezone(new DateTimeZone((string) ($r['zona_horaria'] ?: 'America/Bogota')));
                $r['programado_para_local'] = $dt->format('Y-m-d H:i');
            } catch (Throwable $e) {
                $r['programado_para_local'] = $r['programado_para'];
            }
        } else {
            $r['programado_para_local'] = null;
        }
    }

    private function prepararFechasDest(array &$r): void
    {
        // Las fechas operativas (envío/entrega/lectura) se guardan con NOW()
        // en la zona local del servidor, igual que el resto de la aplicación.
        foreach (['fecha_envio', 'fecha_entrega', 'fecha_lectura'] as $k) {
            $r[$k . '_local'] = !empty($r[$k]) ? (string) $r[$k] : null;
        }
    }

    /** @return int[] */
    private function ids($v): array
    {
        if (is_string($v)) {
            $v = json_decode($v, true);
        }
        if (!is_array($v)) { return []; }
        $out = [];
        foreach ($v as $x) {
            $id = (int) $x;
            if ($id > 0) { $out[$id] = $id; }
        }
        return array_values($out);
    }

    private function jsonArray($v): array
    {
        if (is_string($v)) {
            $decoded = json_decode($v, true);
            if (is_array($decoded)) { return $decoded; }
        }
        return is_array($v) ? $v : [];
    }
}
