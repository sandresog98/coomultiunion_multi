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
require_once __DIR__ . '/FirmaService.php';
require_once __DIR__ . '/PhoneNormalizer.php';
require_once __DIR__ . '/ComunicacionService.php';

/**
 * Ejecución de envíos, callbacks de estado, inbound y conciliación.
 */
final class EnvioService
{
    private PDO $db;
    private ConfigService $cfg;
    private ContactoService $contactos;
    private RemitenteService $remitentes;
    private PlantillaService $plantillas;
    private MediaService $media;
    private CostoService $costos;
    private ComunicacionService $planes;

    public function __construct(?PDO $db = null, ?ConfigService $cfg = null, ?ComunicacionService $planes = null)
    {
        $this->db  = $db ?? db();
        $this->cfg = $cfg ?? new ConfigService($this->db);
        $this->contactos  = new ContactoService($this->db, $this->cfg);
        $this->remitentes = new RemitenteService($this->db);
        $this->plantillas = new PlantillaService($this->db);
        $this->media      = new MediaService($this->db);
        $this->costos     = new CostoService($this->cfg);
        $this->planes     = $planes ?? new ComunicacionService($this->db, $this->cfg);
    }

    /** Procesa planes vencidos (cron cada 15 minutos). */
    public function procesarPendientes(int $limit = 200, ?int $planId = null): array
    {
        $lotes = 0;
        $enviados = 0;
        if ($planId !== null) {
            $ids = [$planId];
        } else {
            $lim = max(1, min(50, (int) $this->cfg->get('com_lote', '50')));
            $st = $this->db->prepare(
                "SELECT id FROM com_planes
                  WHERE (estado='programado' AND (programado_para IS NULL OR programado_para <= UTC_TIMESTAMP()))
                     OR (estado='en_proceso' AND (fecha_ejecucion IS NULL OR fecha_ejecucion < (UTC_TIMESTAMP() - INTERVAL 15 MINUTE)))
                  ORDER BY COALESCE(programado_para, fecha_creacion), id
                  LIMIT ?"
            );
            $st->bindValue(1, $lim, PDO::PARAM_INT);
            $st->execute();
            $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        }

        foreach ($ids as $id) {
            $r = $this->procesarPlan((int) $id, $limit);
            $lotes++;
            $enviados += (int) ($r['enviados'] ?? 0);
        }
        return ['planes' => $lotes, 'enviados' => $enviados];
    }

    /**
     * Procesa un plan. Usa GET_LOCK para que dos workers/cron no dupliquen.
     * @return array{plan_id:int,enviados:int,pendientes:int,estado:string}
     */
    public function procesarPlan(int $planId, int $limit = 200, bool $ignorarHorario = false): array
    {
        $lockName = 'com_plan_' . $planId;
        $st = $this->db->prepare("SELECT GET_LOCK(?, 0)");
        $st->execute([$lockName]);
        if ((int) $st->fetchColumn() !== 1) {
            return ['plan_id' => $planId, 'enviados' => 0, 'pendientes' => 0, 'estado' => 'bloqueado'];
        }

        try {
            $plan = $this->planes->planCrudo($planId);
            if (!in_array((string) $plan['estado'], ['programado', 'en_proceso'], true)) {
                return ['plan_id' => $planId, 'enviados' => 0, 'pendientes' => 0, 'estado' => (string) $plan['estado']];
            }

            if (!$ignorarHorario && (string) $plan['tipo'] === 'masiva' && !$this->dentroHorario((string) $plan['zona_horaria'])) {
                return ['plan_id' => $planId, 'enviados' => 0, 'pendientes' => 0, 'estado' => 'fuera_horario'];
            }

            // Recuperación conservadora: un envío interrumpido se marca fallido
            // para no arriesgar un duplicado. El usuario puede reintentar.
            $this->db->prepare(
                "UPDATE com_destinatarios
                    SET estado='fallido', error_message='Intento interrumpido; verifique en Twilio antes de reintentar.',
                        fecha_actualizacion=NOW()
                  WHERE plan_id=? AND estado='enviando'
                    AND (ultimo_intento IS NULL OR ultimo_intento < (UTC_TIMESTAMP() - INTERVAL 10 MINUTE))"
            )->execute([$planId]);

            if ((string) $plan['estado'] === 'programado') {
                $this->db->prepare(
                    "UPDATE com_planes SET estado='en_proceso', fecha_ejecucion=NOW(), fecha_actualizacion=NOW()
                      WHERE id=? AND estado='programado'"
                )->execute([$planId]);
            }

            $st = $this->db->prepare(
                "SELECT * FROM com_destinatarios
                  WHERE plan_id=? AND estado='pendiente'
                  ORDER BY id LIMIT ?"
            );
            $st->bindValue(1, $planId, PDO::PARAM_INT);
            $st->bindValue(2, max(1, min(1000, $limit)), PDO::PARAM_INT);
            $st->execute();
            $pendientes = $st->fetchAll();

            $enviados = 0;
            $mps = max(1, (int) $this->cfg->get('com_limite_mps', '5'));
            foreach ($pendientes as $dest) {
                $claim = $this->db->prepare(
                    "UPDATE com_destinatarios
                        SET estado='enviando', intentos=intentos+1, ultimo_intento=NOW(), fecha_actualizacion=NOW()
                      WHERE id=? AND estado='pendiente'"
                );
                $claim->execute([(int) $dest['id']]);
                if ($claim->rowCount() === 0) {
                    continue;
                }
                $dest['intentos'] = (int) $dest['intentos'] + 1;
                $this->enviarDestinatario($dest, $plan);
                $enviados++;
                usleep((int) (1000000 / $mps));
            }

            $this->planes->recalcularPlan($planId);
            $st = $this->db->prepare(
                "SELECT COUNT(*) FROM com_destinatarios WHERE plan_id=? AND estado IN ('pendiente','enviando')"
            );
            $st->execute([$planId]);
            $quedan = (int) $st->fetchColumn();
            if ($quedan === 0) {
                $this->planes->finalizarPlanSiCorresponde($planId);
            }
            $st = $this->db->prepare("SELECT estado FROM com_planes WHERE id=?");
            $st->execute([$planId]);

            return [
                'plan_id' => $planId,
                'enviados' => $enviados,
                'pendientes' => $quedan,
                'estado' => (string) $st->fetchColumn(),
            ];
        } finally {
            $st = $this->db->prepare("SELECT RELEASE_LOCK(?)");
            $st->execute([$lockName]);
        }
    }

    /** Envía un destinatario ya reclamado (estado='enviando'). */
    public function enviarDestinatario(array $dest, array $plan): void
    {
        $canal = (string) $plan['canal'];
        $direccion = (string) $dest['destino'];

        try {
            $permiso = $this->contactos->puedeEnviar($canal, $direccion);
            if (!$permiso['ok']) {
                $this->marcarOmitido((int) $dest['id'], (string) $permiso['motivo']);
                return;
            }

            if ((string) env('TWILIO_DRY_RUN', '0') === '1') {
                $this->marcarOmitido((int) $dest['id'], 'simulacion');
                return;
            }

            if ($canal === 'email') {
                $this->enviarEmail($dest, $plan);
            } else {
                $this->enviarTwilio($dest, $plan);
            }
        } catch (DomainException $e) {
            $motivo = $e->getMessage();
            if (stripos($motivo, 'opt-in') !== false || stripos($motivo, 'opt out') !== false || stripos($motivo, 'opt-out') !== false) {
                $this->marcarOmitido((int) $dest['id'], 'sin_opt_in');
            } elseif (stripos($motivo, 'ventana de 24') !== false) {
                $this->marcarFallido((int) $dest['id'], null, $motivo);
            } else {
                $this->marcarFallido((int) $dest['id'], null, $motivo);
            }
        } catch (TwilioException $e) {
            $this->marcarErrorTwilio((int) $dest['id'], $e);
        } catch (SendGridException $e) {
            $this->marcarFallido((int) $dest['id'], $e->httpStatus, $e->getMessage());
        } catch (Throwable $e) {
            error_log('[comunicaciones] envío: ' . $e->getMessage());
            $this->marcarFallido((int) $dest['id'], null, $e->getMessage());
        }
    }

    private function enviarTwilio(array $dest, array $plan): void
    {
        $canal = (string) $plan['canal'];
        $direccion = (string) $dest['destino'];
        $sender = $this->planes->resolverRemitente((int) ($plan['remitente_id'] ?? 0), $canal);
        if (!$sender) {
            throw new DomainException('No hay remitente activo para ' . $canal . '.');
        }

        if ($canal === 'whatsapp' && (string) $plan['modo_contenido'] !== 'plantilla') {
            if (!$this->ventanaWhatsappAbierta($direccion)) {
                throw new DomainException('Fuera de la ventana de 24 horas de WhatsApp: use una plantilla aprobada.');
            }
        }

        $params = [
            'To' => $canal === 'whatsapp' ? ('whatsapp:' . $direccion) : $direccion,
            'StatusCallback' => TwilioSignature::statusUrl(),
        ];
        if (!empty($sender['messaging_service_sid'])) {
            $params['MessagingServiceSid'] = (string) $sender['messaging_service_sid'];
        }
        if (!empty($sender['direccion'])) {
            $params['From'] = (string) $sender['direccion'];
        }

        if ((string) $plan['modo_contenido'] === 'plantilla') {
            $plantilla = (int) ($plan['plantilla_id'] ?? 0) > 0
                ? $this->plantillas->obtener((int) $plan['plantilla_id'])
                : null;
            $contentSid = (string) ($plan['proveedor_template_id'] ?? ($plantilla['proveedor_template_id'] ?? ''));
            if ($contentSid === '') {
                throw new DomainException('El plan no tiene ContentSid de plantilla.');
            }
            $params['ContentSid'] = $contentSid;
            $vars = $this->variablesContenido($dest, $plan, $plantilla);
            if ($vars) {
                $params['ContentVariables'] = json_encode($vars, JSON_UNESCAPED_UNICODE);
            }
        } else {
            $body = $this->render($this->textoPlan($plan), $dest, $plan);
            if ($body === '') {
                throw new DomainException('El mensaje está vacío.');
            }
            $params['Body'] = $body;
            $mediaIds = $this->ids($plan['media_json'] ?? []);
            if ($mediaIds && $canal === 'whatsapp') {
                $params['MediaUrl'] = $this->media->urlsParaPlan($mediaIds, 3600);
            }
        }

        $client = TwilioClient::fromEnv();
        $res = $client->createMessage($params);
        $sid = (string) ($res['sid'] ?? '');
        $status = strtolower((string) ($res['status'] ?? 'queued'));
        $this->db->prepare(
            "UPDATE com_destinatarios
                SET estado='enviado', estado_proveedor=?, proveedor_message_id=?, fecha_envio=NOW(),
                    num_segmentos=?, error_code=NULL, error_message=NULL, fecha_actualizacion=NOW()
              WHERE id=?"
        )->execute([$status, $sid ?: null, isset($res['num_segments']) ? (int) $res['num_segments'] : null, (int) $dest['id']]);
        $this->logEvento((int) $plan['id'], (int) $dest['id'], 'twilio', $sid, 'send', $status, null, null, $res);
    }

    private function enviarEmail(array $dest, array $plan): void
    {
        $sender = $this->planes->resolverRemitente((int) ($plan['remitente_id'] ?? 0), 'email');
        if (!$sender) {
            throw new DomainException('No hay remitente de correo activo.');
        }
        $from = (string) $sender['direccion'];
        $fromName = (string) ($sender['nombre_remitente'] ?? 'Coomultiunion');
        $subject = $this->render((string) ($plan['asunto'] ?? ''), $dest, $plan);
        $text = $this->render((string) ($plan['cuerpo'] ?? ''), $dest, $plan);
        $html = $this->render((string) ($plan['cuerpo_html'] ?? ''), $dest, $plan);
        if ($subject === '') {
            throw new DomainException('El asunto del correo está vacío.');
        }
        if ($text === '' && $html === '') {
            throw new DomainException('El cuerpo del correo está vacío.');
        }

        $mediaIds = $this->ids($plan['media_json'] ?? []);
        $attachments = $mediaIds ? $this->media->adjuntosParaSmtp($mediaIds) : [];
        $baja = $this->unsubscribeUrl((string) $dest['destino'], (int) $plan['id']);
        if ($html !== '') {
            $bajaHtml = '<p style="font-size:12px;color:#666">Si no desea recibir más correos, <a href="' . htmlspecialchars($baja, ENT_QUOTES, 'UTF-8') . '">dése de baja aquí</a>.</p>';
            $html = stripos($html, '</body>') !== false
                ? (string) preg_replace('/<\/body>/i', $bajaHtml . '</body>', $html, 1)
                : $html . $bajaHtml;
        } else {
            $text .= "\n\nSi no desea recibir más correos, abra: " . $baja;
        }

        // El canal email usa el SMTP institucional (PHPMailer). SendGrid queda
        // como integración opcional futura, no como dependencia del envío.
        require_once dirname(__DIR__) . '/Email/Mailer.php';
        if (!Mailer::isConfigured()) {
            throw new DomainException('SMTP no está configurado (SMTP_HOST/SMTP_USER/SMTP_PASS).');
        }
        $ok = Mailer::sendAdvanced((string) $dest['destino'], $subject, $html !== '' ? $html : $text, [
            'from' => $from,
            'fromName' => $fromName,
            'toName' => (string) ($dest['nombre'] ?? ''),
            'isHtml' => $html !== '',
            'altBody' => $text,
            'attachments' => $attachments,
            'headers' => [
                'List-Unsubscribe' => '<' . $baja . '>',
                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
            ],
        ]);
        if (!$ok) {
            throw new DomainException('SMTP no pudo enviar el correo.');
        }
        $this->db->prepare(
            "UPDATE com_destinatarios
                SET estado='enviado', estado_proveedor='smtp', fecha_envio=NOW(),
                    error_code=NULL, error_message=NULL, fecha_actualizacion=NOW()
              WHERE id=?"
        )->execute([(int) $dest['id']]);
        $this->logEvento((int) $plan['id'], (int) $dest['id'], 'smtp', null, 'send', 'smtp', null, null, null);
    }

    // ── Estados / conciliación ──────────────────────────────────────

    public function sincronizarEstados(int $limit = 100): array
    {
        $client = TwilioClient::fromEnv();
        if (!$client->isConfigured()) {
            return ['actualizados' => 0, 'error' => 'Twilio no configurado'];
        }
        $st = $this->db->prepare(
            "SELECT d.id, d.proveedor_message_id, d.estado_proveedor, d.precio_usd
               FROM com_destinatarios d
               JOIN com_planes p ON p.id = d.plan_id
              WHERE p.proveedor = 'twilio'
                AND d.proveedor_message_id IS NOT NULL AND d.proveedor_message_id <> ''
                AND (d.precio_usd IS NULL OR d.estado_proveedor IS NULL
                     OR d.estado_proveedor NOT IN ('delivered','read','failed','undelivered','canceled'))
              ORDER BY d.id DESC LIMIT ?"
        );
        $st->bindValue(1, max(1, min(500, $limit)), PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll();
        $n = 0;
        foreach ($rows as $r) {
            try {
                $m = $client->fetchMessage((string) $r['proveedor_message_id']);
                $this->actualizarDesdeMensaje((int) $r['id'], $m);
                $n++;
            } catch (TwilioException $e) {
                if ($e->httpStatus === 404) {
                    $this->marcarFallido((int) $r['id'], 404, 'Mensaje no encontrado en Twilio.');
                } else {
                    error_log('[comunicaciones] sync #' . $r['id'] . ': ' . $e->getMessage());
                }
            }
        }
        return ['actualizados' => $n];
    }

    public function procesarStatus(array $params): array
    {
        $sid = (string) ($params['MessageSid'] ?? $params['SmsSid'] ?? '');
        $status = strtolower((string) ($params['MessageStatus'] ?? $params['SmsStatus'] ?? ''));
        if ($sid === '' || $status === '') {
            return ['ok' => false, 'motivo' => 'sin_sid_o_estado'];
        }
        $st = $this->db->prepare("SELECT * FROM com_destinatarios WHERE proveedor_message_id = ? LIMIT 1");
        $st->execute([$sid]);
        $dest = $st->fetch();
        if (!$dest) {
            $this->logEvento(null, null, 'twilio', $sid, 'status', $status, null, null, $params);
            return ['ok' => true, 'motivo' => 'no_encontrado'];
        }

        $rank = ['queued' => 10, 'accepted' => 15, 'sending' => 20, 'sent' => 30, 'delivered' => 40, 'read' => 50,
                 'failed' => 60, 'undelivered' => 60, 'canceled' => 60];
        $nuevo = $rank[$status] ?? 0;
        $actual = $rank[(string) ($dest['estado_proveedor'] ?? '')] ?? 0;
        if ($nuevo > 0 && $nuevo < $actual) {
            return ['ok' => true, 'motivo' => 'fuera_de_orden'];
        }

        $estado = (string) $dest['estado'];
        $fechaEntrega = $dest['fecha_entrega'];
        $fechaLectura = $dest['fecha_lectura'];
        if ($status === 'delivered') { $estado = 'entregado'; $fechaEntrega = date('Y-m-d H:i:s'); }
        elseif ($status === 'read') { $estado = 'leido'; $fechaLectura = date('Y-m-d H:i:s'); }
        elseif (in_array($status, ['failed', 'undelivered', 'canceled'], true)) { $estado = 'fallido'; }
        elseif ($status === 'sent') { $estado = 'enviado'; }

        $errCode = isset($params['ErrorCode']) && $params['ErrorCode'] !== '' ? (int) $params['ErrorCode'] : null;
        $errMsg = (string) ($params['ErrorMessage'] ?? $params['ChannelStatusMessage'] ?? '');
        if ($errMsg === '' && $errCode !== null) { $errMsg = 'Twilio error ' . $errCode; }

        $this->db->prepare(
            "UPDATE com_destinatarios
                SET estado=?, estado_proveedor=?, error_code=?, error_message=?, fecha_entrega=?, fecha_lectura=?,
                    fecha_actualizacion=NOW()
              WHERE id=?"
        )->execute([$estado, $status, $errCode, $errMsg ?: null, $fechaEntrega, $fechaLectura, (int) $dest['id']]);

        $this->logEvento((int) $dest['plan_id'], (int) $dest['id'], 'twilio', $sid, 'status', $status, $errCode, $errMsg, $params);
        $this->planes->recalcularPlan((int) $dest['plan_id']);
        return ['ok' => true, 'estado' => $estado];
    }

    public function procesarInbound(array $params): array
    {
        $sid = (string) ($params['MessageSid'] ?? $params['SmsSid'] ?? '');
        $fromRaw = (string) ($params['From'] ?? '');
        $toRaw = (string) ($params['To'] ?? '');
        if ($sid === '' || $fromRaw === '') {
            return ['ok' => false, 'motivo' => 'sin_datos'];
        }
        $canal = stripos($fromRaw, 'whatsapp:') === 0 ? 'whatsapp' : 'sms';
        $de = (string) PhoneNormalizer::normalize(preg_replace('/^whatsapp:/i', '', $fromRaw) ?? '');
        $para = (string) PhoneNormalizer::normalize(preg_replace('/^whatsapp:/i', '', $toRaw) ?? '');
        $body = (string) ($params['Body'] ?? '');
        $num = (int) ($params['NumMedia'] ?? 0);
        $media = [];
        for ($i = 0; $i < $num; $i++) {
            $u = (string) ($params['MediaUrl' . $i] ?? '');
            if ($u !== '') { $media[] = $u; }
        }

        $cedula = $this->contactos->asociadoPorDireccion($canal, $de);
        $contactoId = $this->contactos->upsert($canal, $de, [
            'nombre' => (string) ($params['ProfileName'] ?? ''),
            'asociado_cedula' => $cedula,
        ]);
        $st = $this->db->prepare(
            "SELECT d.id, d.plan_id FROM com_destinatarios d
              WHERE d.destino = ? AND d.canal = ? ORDER BY d.id DESC LIMIT 1"
        );
        $st->execute([$de, $canal]);
        $last = $st->fetch() ?: [];

        try {
            $this->db->prepare(
                "INSERT IGNORE INTO com_entrantes
                    (proveedor, proveedor_message_id, canal, de, para, cuerpo, media_json,
                     asociado_cedula, contacto_id, plan_id, destinatario_id, fecha_proveedor)
                 VALUES ('twilio', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            )->execute([
                $sid, $canal, $de, $para ?: null, $body,
                $media ? json_encode($media, JSON_UNESCAPED_UNICODE) : null,
                $cedula, $contactoId ?: null,
                $last['plan_id'] ?? null, $last['id'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('[comunicaciones] inbound: ' . $e->getMessage());
        }

        $texto = $this->normalizarKeyword($body);
        $optout = $this->listaConfig('com_optout_keywords', 'STOP,BAJA,CANCELAR,SALIR,UNSUBSCRIBE,NO MAS');
        $optin  = $this->listaConfig('com_optin_keywords', 'ALTA,SI,START,UNSTOP');
        $respuesta = null;
        $accion = 'ninguna';

        if ($texto !== '' && in_array($texto, $optout, true)) {
            $this->contactos->registrarOptOut($canal, $de, 'inbound', 'Palabra: ' . $body);
            $this->cancelarPendientesContacto($canal, $de, 'opt_out');
            $accion = 'opt_out';
            $respuesta = 'Hemos registrado su solicitud. No recibirá más mensajes.';
        } elseif ($texto !== '' && in_array($texto, $optin, true)) {
            $this->contactos->registrarOptIn($canal, $de, 'inbound', 'Palabra: ' . $body);
            $accion = 'opt_in';
            $respuesta = 'Su consentimiento fue registrado. Gracias.';
        } elseif ($texto !== '' && in_array($texto, ['AYUDA', 'HELP', 'INFO'], true)) {
            $accion = 'ayuda';
            $respuesta = (string) $this->cfg->get('com_ayuda_texto', 'Para ayuda comuníquese con la cooperativa.');
        }

        return ['ok' => true, 'accion' => $accion, 'respuesta' => $respuesta, 'canal' => $canal];
    }

    public function procesarSendGrid(array $eventos): array
    {
        $n = 0;
        foreach ($eventos as $ev) {
            if (!is_array($ev)) { continue; }
            $tipo = strtolower((string) ($ev['event'] ?? ''));
            $email = (string) PhoneNormalizer::normalizeEmail((string) ($ev['email'] ?? '')) ?: '';
            $sgId = (string) ($ev['sg_message_id'] ?? '');
            $baseId = $sgId !== '' ? explode('.', $sgId)[0] : '';
            if ($email !== '') {
                $contacto = $this->contactos->obtener('email', $email);
                if (in_array($tipo, ['unsubscribe', 'spamreport', 'bounce', 'dropped'], true)) {
                    $this->contactos->registrarOptOut('email', $email, 'sendgrid', $tipo);
                }
            }
            if ($baseId !== '') {
                $st = $this->db->prepare("SELECT * FROM com_destinatarios WHERE proveedor_message_id = ? OR proveedor_message_id LIKE ? LIMIT 1");
                $st->execute([$baseId, $baseId . '%']);
                $dest = $st->fetch();
                if ($dest) {
                    $estado = (string) $dest['estado'];
                    if ($tipo === 'delivered') { $estado = 'entregado'; }
                    elseif ($tipo === 'open') { $estado = 'leido'; }
                    elseif (in_array($tipo, ['bounce', 'dropped'], true)) { $estado = 'fallido'; }
                    elseif (in_array($tipo, ['unsubscribe', 'spamreport'], true)) { $estado = 'omitido'; }
                    $this->db->prepare(
                        "UPDATE com_destinatarios
                            SET estado=?, estado_proveedor=?, error_message=?, fecha_entrega=IF(? IN ('delivered','open'), COALESCE(fecha_entrega,NOW()), fecha_entrega),
                                fecha_lectura=IF(?='open', COALESCE(fecha_lectura,NOW()), fecha_lectura),
                                fecha_actualizacion=NOW()
                          WHERE id=?"
                    )->execute([$estado, $tipo ?: null, (string) ($ev['reason'] ?? '') ?: null, $tipo, $tipo, (int) $dest['id']]);
                    $this->logEvento((int) $dest['plan_id'], (int) $dest['id'], 'sendgrid', $sgId, 'sendgrid', $tipo, null, (string) ($ev['reason'] ?? ''), $ev);
                    $this->planes->recalcularPlan((int) $dest['plan_id']);
                }
            }
            $n++;
        }
        return ['eventos' => $n];
    }

    public function reintentar(int $destId, ?array $actor = null, bool $forzar = false): void
    {
        $st = $this->db->prepare("SELECT * FROM com_destinatarios WHERE id = ? LIMIT 1");
        $st->execute([$destId]);
        $d = $st->fetch();
        if (!$d) { throw new DomainException('Mensaje no encontrado.'); }
        if (!in_array((string) $d['estado'], ['fallido', 'omitido'], true) && !$forzar) {
            throw new DomainException('Solo se pueden reintentar mensajes fallidos u omitidos.');
        }
        if ($forzar && in_array((string) $d['estado'], ['pendiente', 'enviando'], true)) {
            throw new DomainException('El mensaje todavía está en proceso.');
        }
        $this->db->prepare(
            "UPDATE com_destinatarios
                SET estado='pendiente', estado_proveedor=NULL, proveedor_message_id=NULL, error_code=NULL,
                    error_message=NULL, motivo_omitido=NULL, precio_usd=NULL, precio_moneda=NULL,
                    fecha_envio=NULL, fecha_entrega=NULL, fecha_lectura=NULL, fecha_actualizacion=NOW()
              WHERE id=?"
        )->execute([$destId]);
        $this->db->prepare(
            "UPDATE com_planes SET estado='programado', programado_para=UTC_TIMESTAMP(), fecha_fin=NULL,
                    error_resumen=NULL, fecha_actualizacion=NOW() WHERE id=?"
        )->execute([(int) $d['plan_id']]);
        Logger::log('reintentar', 'comunicaciones.mensaje', $actor['id'] ?? null, "Mensaje #$destId reencolado");
        $this->planes->recalcularPlan((int) $d['plan_id']);
    }

    public function cancelarPendientesContacto(string $canal, string $direccion, string $motivo): void
    {
        $st = $this->db->prepare(
            "SELECT DISTINCT plan_id FROM com_destinatarios
              WHERE canal=? AND destino=? AND estado IN ('pendiente','enviando')"
        );
        $st->execute([$canal, $direccion]);
        $planes = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        $this->db->prepare(
            "UPDATE com_destinatarios
                SET estado='omitido', motivo_omitido=?, fecha_actualizacion=NOW()
              WHERE canal=? AND destino=? AND estado IN ('pendiente','enviando')"
        )->execute([$motivo, $canal, $direccion]);
        foreach ($planes as $pid) {
            $this->planes->recalcularPlan($pid);
        }
    }

    // ── Auxiliares ──────────────────────────────────────────────────

    private function actualizarDesdeMensaje(int $destId, array $m): void
    {
        $status = strtolower((string) ($m['status'] ?? ''));
        $estado = null;
        if ($status === 'delivered') { $estado = 'entregado'; }
        elseif ($status === 'read') { $estado = 'leido'; }
        elseif (in_array($status, ['failed', 'undelivered', 'canceled'], true)) { $estado = 'fallido'; }
        elseif (in_array($status, ['sent', 'queued', 'accepted', 'sending'], true)) { $estado = 'enviado'; }
        $price = isset($m['price']) && $m['price'] !== null ? abs((float) $m['price']) : null;
        $this->db->prepare(
            "UPDATE com_destinatarios
                SET estado=COALESCE(?, estado), estado_proveedor=?, error_code=?, error_message=?,
                    num_segmentos=?, precio_usd=COALESCE(?, precio_usd), precio_moneda=?,
                    fecha_entrega=IF(?='delivered', COALESCE(fecha_entrega,NOW()), fecha_entrega),
                    fecha_lectura=IF(?='read', COALESCE(fecha_lectura,NOW()), fecha_lectura),
                    fecha_actualizacion=NOW()
              WHERE id=?"
        )->execute([
            $estado,
            $status ?: null,
            isset($m['error_code']) ? (int) $m['error_code'] : null,
            (string) ($m['error_message'] ?? '') ?: null,
            isset($m['num_segments']) ? (int) $m['num_segments'] : null,
            $price,
            (string) ($m['price_unit'] ?? '') ?: null,
            $status, $status, $destId,
        ]);
        $st = $this->db->prepare("SELECT plan_id FROM com_destinatarios WHERE id = ?");
        $st->execute([$destId]);
        $pid = (int) ($st->fetchColumn() ?: 0);
        if ($pid > 0) {
            $this->logEvento($pid, $destId, 'twilio', (string) ($m['sid'] ?? ''), 'status', $status,
                isset($m['error_code']) ? (int) $m['error_code'] : null, (string) ($m['error_message'] ?? ''), $m);
            $this->planes->recalcularPlan($pid);
        }
    }

    private function marcarOmitido(int $destId, string $motivo): void
    {
        $this->db->prepare(
            "UPDATE com_destinatarios SET estado='omitido', motivo_omitido=?, fecha_actualizacion=NOW() WHERE id=?"
        )->execute([mb_substr($motivo, 0, 160), $destId]);
    }

    private function marcarFallido(int $destId, ?int $code, string $msg): void
    {
        $this->db->prepare(
            "UPDATE com_destinatarios SET estado='fallido', error_code=?, error_message=?, fecha_actualizacion=NOW() WHERE id=?"
        )->execute([$code, mb_substr($msg, 0, 250), $destId]);
    }

    private function marcarErrorTwilio(int $destId, TwilioException $e): void
    {
        $code = $e->twilioCode;
        // Errores permanentes de destinatario/consentimiento → omitido.
        if (in_array($code, [21211, 21614, 21610, 21612], true)) {
            $this->marcarOmitido($destId, match ($code) {
                21610 => 'opt_out',
                21614, 21211 => 'telefono_invalido',
                default => 'destinatario_invalido',
            });
            return;
        }
        $this->marcarFallido($destId, $code, $e->getMessage());
    }

    private function logEvento(?int $planId, ?int $destId, string $proveedor, ?string $sid, string $tipo, ?string $estado, ?int $code, ?string $msg, ?array $payload): void
    {
        try {
            $this->db->prepare(
                "INSERT INTO com_eventos
                    (plan_id, destinatario_id, proveedor, proveedor_message_id, tipo, estado, error_code, error_message, payload_json, fecha_evento)
                 VALUES (?,?,?,?,?,?,?,?,?,NOW())"
            )->execute([
                $planId, $destId, $proveedor, $sid, $tipo, $estado, $code,
                $msg !== null ? mb_substr($msg, 0, 250) : null,
                $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (Throwable $e) {
            error_log('[comunicaciones] evento: ' . $e->getMessage());
        }
    }

    /** Ventana horaria para campañas masivas (hora local de la zona del plan). */
    private function dentroHorario(string $zona): bool
    {
        $inicio = (string) $this->cfg->get('com_hora_inicio', '08:00');
        $fin = (string) $this->cfg->get('com_hora_fin', '20:00');
        try {
            $tz = new DateTimeZone($zona !== '' ? $zona : 'America/Bogota');
            $ahora = new DateTimeImmutable('now', $tz);
            $hi = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ahora->format('Y-m-d') . ' ' . $inicio, $tz);
            $hf = DateTimeImmutable::createFromFormat('Y-m-d H:i', $ahora->format('Y-m-d') . ' ' . $fin, $tz);
            if (!$hi || !$hf) { return true; }
            return $ahora >= $hi && $ahora <= $hf;
        } catch (Throwable $e) {
            return true;
        }
    }

    private function ventanaWhatsappAbierta(string $direccion): bool
    {
        $st = $this->db->prepare(
            "SELECT COUNT(*) FROM com_entrantes
              WHERE canal='whatsapp' AND de=? AND fecha_creacion >= (UTC_TIMESTAMP() - INTERVAL 24 HOUR)"
        );
        $st->execute([$direccion]);
        return (int) $st->fetchColumn() > 0;
    }

    private function textoPlan(array $plan): string
    {
        return (string) ($plan['cuerpo'] ?? '');
    }

    private function render(string $texto, array $dest, array $plan): string
    {
        if ($texto === '') { return ''; }
        $vars = json_decode((string) ($dest['variables_json'] ?? '[]'), true);
        if (!is_array($vars)) { $vars = []; }
        $data = array_merge([
            'nombre' => (string) ($dest['nombre'] ?? ''),
            'cedula' => (string) ($dest['asociado_cedula'] ?? ''),
            'destino' => (string) ($dest['destino'] ?? ''),
            'telefono' => (string) ($dest['destino'] ?? ''),
            'email' => (string) ($dest['destino'] ?? ''),
        ], $vars);

        $map = json_decode((string) ($plan['variables_json'] ?? '[]'), true);
        if (is_array($map)) {
            foreach ($map as $k => $campo) {
                $campo = (string) $campo;
                if (array_key_exists($campo, $data)) {
                    $data[(string) $k] = $data[$campo];
                } elseif ($campo !== '') {
                    $data[(string) $k] = $campo;
                }
            }
        }

        foreach ($data as $k => $v) {
            $texto = str_replace('{{' . $k . '}}', (string) $v, $texto);
            $texto = str_replace('{{ ' . $k . ' }}', (string) $v, $texto);
        }
        return $texto;
    }

    private function variablesContenido(array $dest, array $plan, ?array $plantilla): array
    {
        $vars = [];
        $map = json_decode((string) ($plan['variables_json'] ?? '[]'), true);
        if (!is_array($map)) { $map = []; }
        foreach ($map as $k => $campo) {
            $vars[(string) $k] = $this->valorVariable((string) $campo, $dest);
        }
        if ($plantilla) {
            $req = $this->plantillas->variablesDe($plantilla);
            foreach ($req as $k) {
                if (!array_key_exists((string) $k, $vars)) {
                    $vars[(string) $k] = $this->valorVariable((string) $k, $dest);
                }
            }
        }
        return $vars;
    }

    private function valorVariable(string $campo, array $dest): string
    {
        $vars = json_decode((string) ($dest['variables_json'] ?? '[]'), true);
        if (!is_array($vars)) { $vars = []; }
        $data = array_merge([
            'nombre' => (string) ($dest['nombre'] ?? ''),
            'cedula' => (string) ($dest['asociado_cedula'] ?? ''),
            'destino' => (string) ($dest['destino'] ?? ''),
            'telefono' => (string) ($dest['destino'] ?? ''),
            'email' => (string) ($dest['destino'] ?? ''),
        ], $vars);
        return (string) ($data[$campo] ?? $campo);
    }

    private function unsubscribeUrl(string $email, int $planId): string
    {
        $token = FirmaService::sign(['email' => $email, 'plan' => $planId, 'tipo' => 'baja'], 0);
        require_once dirname(__DIR__) . '/Twilio/TwilioSignature.php';
        return TwilioSignature::appUrl() . '/api/comunicaciones/baja.php?t=' . rawurlencode($token);
    }

    private function listaConfig(string $clave, string $default): array
    {
        $raw = (string) $this->cfg->get($clave, $default);
        $out = [];
        foreach (explode(',', $raw) as $x) {
            $x = $this->normalizarKeyword($x);
            if ($x !== '') { $out[] = $x; }
        }
        return $out;
    }

    private function normalizarKeyword(string $s): string
    {
        $s = strtoupper(trim($s));
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);
        $s = preg_replace('/[^A-Z0-9 ]+/', '', $s) ?? $s;
        return trim($s);
    }

    /** @return int[] */
    private function ids($v): array
    {
        if (is_string($v)) { $v = json_decode($v, true); }
        if (!is_array($v)) { return []; }
        $out = [];
        foreach ($v as $x) {
            $id = (int) $x;
            if ($id > 0) { $out[$id] = $id; }
        }
        return array_values($out);
    }
}
