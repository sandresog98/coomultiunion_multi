<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/Twilio/TwilioClient.php';

/**
 * Plantillas de WhatsApp (Twilio Content API) y plantillas locales de email.
 */
final class PlantillaService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    public function listar(?string $canal = null, bool $soloAprobadas = false): array
    {
        $sql = "SELECT * FROM com_plantillas WHERE activo = 1";
        $params = [];
        if ($canal !== null && $canal !== '') {
            $sql .= " AND canal = ?";
            $params[] = $canal;
        }
        if ($soloAprobadas) {
            $sql .= " AND (estado_aprobacion = 'approved' OR proveedor = 'local')";
        }
        $sql .= " ORDER BY canal, nombre";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['variables'] = $this->variablesDe($r);
            $r['media'] = json_decode((string) ($r['media_json'] ?? '[]'), true) ?: [];
        }
        return $rows;
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM com_plantillas WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $r = $st->fetch();
        if (!$r) {
            return null;
        }
        $r['variables'] = $this->variablesDe($r);
        $r['media'] = json_decode((string) ($r['media_json'] ?? '[]'), true) ?: [];
        return $r;
    }

    /** Sincroniza las plantillas de Twilio Content con la base local. */
    public function sincronizarTwilio(?array $actor = null): array
    {
        $client = TwilioClient::fromEnv();
        if (!$client->isConfigured()) {
            throw new DomainException('Twilio no está configurado. Agregue TWILIO_ACCOUNT_SID y TWILIO_API_KEY_SID/SECRET o AUTH_TOKEN al .env.');
        }

        $contents = $client->listContentAndApprovals(100, 10);
        $upsert = $this->db->prepare(
            "INSERT INTO com_plantillas
                (proveedor, proveedor_template_id, nombre, canal, categoria, idioma, asunto, cuerpo, cuerpo_html,
                 variables_json, media_json, tipos_json, estado_aprobacion, motivo_rechazo, activo,
                 fecha_sincronizacion, creado_por)
             VALUES ('twilio_content', ?, ?, 'whatsapp', ?, ?, NULL, ?, NULL, ?, ?, ?, ?, ?, 1, NOW(), ?)
             ON DUPLICATE KEY UPDATE
                nombre = VALUES(nombre), categoria = VALUES(categoria), idioma = VALUES(idioma),
                cuerpo = VALUES(cuerpo), variables_json = VALUES(variables_json),
                media_json = VALUES(media_json), tipos_json = VALUES(tipos_json),
                estado_aprobacion = VALUES(estado_aprobacion), motivo_rechazo = VALUES(motivo_rechazo),
                activo = 1, fecha_sincronizacion = NOW(), fecha_actualizacion = NOW()"
        );

        $total = 0;
        foreach ($contents as $c) {
            if (!is_array($c)) {
                continue;
            }
            $sid = (string) ($c['sid'] ?? '');
            if ($sid === '') {
                continue;
            }
            $data = $this->parsearContenido($c);
            $upsert->execute([
                $sid,
                mb_substr($data['nombre'], 0, 150),
                mb_substr($data['categoria'], 0, 25),
                mb_substr($data['idioma'], 0, 8),
                $data['cuerpo'],
                json_encode($data['variables'], JSON_UNESCAPED_UNICODE),
                json_encode($data['media'], JSON_UNESCAPED_UNICODE),
                json_encode($c, JSON_UNESCAPED_UNICODE),
                mb_substr($data['estado'], 0, 20),
                mb_substr($data['motivo'], 0, 255),
                $actor['id'] ?? null,
            ]);
            $total++;
        }

        Logger::log('sincronizar', 'comunicaciones.plantilla', $actor['id'] ?? null,
            "Plantillas Twilio sincronizadas: $total");
        return ['total' => $total];
    }

    /** Guarda una plantilla local (email) editable desde la UI. */
    public function guardarLocal(array $data, ?array $actor = null): int
    {
        $id      = (int) ($data['id'] ?? 0);
        $nombre  = mb_substr(trim((string) ($data['nombre'] ?? '')), 0, 150);
        $asunto  = mb_substr(trim((string) ($data['asunto'] ?? '')), 0, 255);
        $cuerpo  = (string) ($data['cuerpo'] ?? '');
        $html    = (string) ($data['cuerpo_html'] ?? '');
        if ($nombre === '' || $asunto === '') {
            throw new DomainException('Nombre y asunto son obligatorios.');
        }
        if ($cuerpo === '' && $html === '') {
            throw new DomainException('El cuerpo de la plantilla es obligatorio.');
        }
        if ($id > 0) {
            $this->db->prepare(
                "UPDATE com_plantillas SET nombre=?, asunto=?, cuerpo=?, cuerpo_html=?, fecha_actualizacion=NOW()
                  WHERE id=? AND proveedor='local'"
            )->execute([$nombre, $asunto, $cuerpo, $html, $id]);
        } else {
            $this->db->prepare(
                "INSERT INTO com_plantillas
                    (proveedor, proveedor_template_id, nombre, canal, asunto, cuerpo, cuerpo_html,
                     variables_json, estado_aprobacion, activo, creado_por)
                 VALUES ('local', NULL, ?, 'email', ?, ?, ?, '[]', 'approved', 1, ?)"
            )->execute([$nombre, $asunto, $cuerpo, $html, $actor['id'] ?? null]);
            $id = (int) $this->db->lastInsertId();
        }
        Logger::log('guardar', 'comunicaciones.plantilla', $actor['id'] ?? null, "Plantilla email #$id");
        return $id;
    }

    public function eliminar(int $id, ?array $actor = null): void
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM com_planes WHERE plantilla_id = ?");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: la plantilla está usada en un plan.');
        }
        $this->db->prepare("DELETE FROM com_plantillas WHERE id = ? AND proveedor = 'local'")->execute([$id]);
        Logger::log('eliminar', 'comunicaciones.plantilla', $actor['id'] ?? null, "Plantilla #$id", null, null, 'warning');
    }

    /** Variables declaradas por la plantilla (o inferidas del cuerpo). */
    public function variablesDe(array $plantilla): array
    {
        $vars = json_decode((string) ($plantilla['variables_json'] ?? '[]'), true);
        if (is_array($vars) && $vars) {
            return array_values(array_map('strval', array_keys($vars)));
        }
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', (string) ($plantilla['cuerpo'] ?? ''), $m)) {
            return array_values(array_unique($m[1]));
        }
        return [];
    }

    private function parsearContenido(array $c): array
    {
        $types = is_array($c['types'] ?? null) ? $c['types'] : [];
        $cuerpo = '';
        $media = [];
        $asunto = null;

        foreach (['twilio/text', 'twilio/media', 'twilio/quick-reply', 'twilio/card', 'twilio/carousel'] as $tipo) {
            if (!isset($types[$tipo]) || !is_array($types[$tipo])) {
                continue;
            }
            $t = $types[$tipo];
            if ($cuerpo === '') {
                $cuerpo = (string) ($t['body'] ?? $t['title'] ?? $t['subtitle'] ?? '');
            }
            if (!$media && !empty($t['media']) && is_array($t['media'])) {
                $media = array_values(array_map('strval', $t['media']));
            }
            if (!$media && !empty($t['cards'][0]['media']) && is_array($t['cards'][0]['media'])) {
                $media = array_values(array_map('strval', $t['cards'][0]['media']));
            }
        }

        $vars = [];
        if (is_array($c['variables'] ?? null)) {
            foreach ($c['variables'] as $k => $v) {
                $vars[(string) $k] = (string) $v;
            }
        } else {
            if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $cuerpo, $m)) {
                foreach (array_unique($m[1]) as $v) {
                    $vars[(string) $v] = '';
                }
            }
        }

        $wa = [];
        if (isset($c['approvals']['whatsapp']) && is_array($c['approvals']['whatsapp'])) {
            $wa = $c['approvals']['whatsapp'];
        } elseif (isset($c['approval_requests']['whatsapp']) && is_array($c['approval_requests']['whatsapp'])) {
            $wa = $c['approval_requests']['whatsapp'];
        }
        $estado = strtolower((string) ($wa['status'] ?? $c['status'] ?? 'unsubmitted'));
        $map = [
            'approved' => 'approved',
            'pending' => 'pending',
            'rejected' => 'rejected',
            'paused' => 'paused',
            'disabled' => 'disabled',
            'unsubmitted' => 'unsubmitted',
            'submitted' => 'pending',
        ];
        $estado = $map[$estado] ?? 'unsubmitted';

        return [
            'nombre'   => (string) ($c['friendly_name'] ?? ($wa['name'] ?? 'Plantilla WhatsApp')),
            'categoria'=> strtolower((string) ($wa['category'] ?? $c['category'] ?? '')),
            'idioma'   => (string) ($c['language'] ?? 'es'),
            'cuerpo'   => $cuerpo,
            'media'    => $media,
            'variables'=> $vars,
            'estado'   => $estado,
            'motivo'   => (string) ($wa['rejection_reason'] ?? $c['rejection_reason'] ?? ''),
        ];
    }
}
