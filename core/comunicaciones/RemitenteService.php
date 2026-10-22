<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/env.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once __DIR__ . '/PhoneNormalizer.php';

/**
 * Remitentes por canal. Los secretos de Twilio/SendGrid viven en .env; aquí
 * solo se guardan números/correos y Messaging Service SID.
 */
final class RemitenteService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Lista remitentes; si la tabla está vacía intenta sembrar desde .env. */
    public function listar(?string $canal = null, bool $soloActivos = false): array
    {
        $this->sembrarDesdeEnv();
        $sql = "SELECT * FROM com_remitentes WHERE 1=1";
        $params = [];
        if ($canal !== null && $canal !== '') {
            $sql .= " AND canal = ?";
            $params[] = $canal;
        }
        if ($soloActivos) {
            $sql .= " AND activo = 1";
        }
        $sql .= " ORDER BY canal, predeterminado DESC, id";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public function obtener(int $id): ?array
    {
        $st = $this->db->prepare("SELECT * FROM com_remitentes WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public function predeterminado(string $canal): ?array
    {
        $this->sembrarDesdeEnv();
        $st = $this->db->prepare(
            "SELECT * FROM com_remitentes
              WHERE canal = ? AND activo = 1
              ORDER BY predeterminado DESC, id
              LIMIT 1"
        );
        $st->execute([$canal]);
        return $st->fetch() ?: null;
    }

    public function guardar(array $data, ?array $actor = null): int
    {
        $id       = (int) ($data['id'] ?? 0);
        $canal    = trim((string) ($data['canal'] ?? ''));
        $proveedor = trim((string) ($data['proveedor'] ?? ''));
        $etiqueta = mb_substr(trim((string) ($data['etiqueta'] ?? '')), 0, 100);
        $direccion = mb_substr(trim((string) ($data['direccion'] ?? '')), 0, 160);
        $nombre   = mb_substr(trim((string) ($data['nombre_remitente'] ?? '')), 0, 150);
        $msid     = trim((string) ($data['messaging_service_sid'] ?? ''));
        $media    = (int) ($data['soporta_media'] ?? 0) === 1 ? 1 : 0;
        $limite   = max(1, min(1000, (int) ($data['limite_mps'] ?? 1)));
        $activo   = (int) ($data['activo'] ?? 1) === 1 ? 1 : 0;
        $predet   = (int) ($data['predeterminado'] ?? 0) === 1 ? 1 : 0;

        if (!in_array($canal, ['sms', 'whatsapp', 'email'], true)) {
            throw new DomainException('Canal inválido.');
        }
        if ($proveedor === '') {
            $proveedor = $canal === 'email' ? 'smtp' : 'twilio';
        }
        if (!in_array($proveedor, ['twilio', 'sendgrid', 'smtp'], true)) {
            throw new DomainException('Proveedor inválido.');
        }
        if ($canal === 'email' && $proveedor === 'twilio') {
            throw new DomainException('El email usa SMTP, no Twilio Messaging.');
        }
        if ($canal !== 'email' && $proveedor !== 'twilio') {
            throw new DomainException('SMS y WhatsApp usan Twilio.');
        }
        if ($etiqueta === '' || $direccion === '') {
            throw new DomainException('Etiqueta y dirección son obligatorias.');
        }

        if ($canal === 'email') {
            $direccion = (string) PhoneNormalizer::normalizeEmail($direccion);
            if ($direccion === '') {
                throw new DomainException('Correo remitente inválido.');
            }
        } else {
            $tel = PhoneNormalizer::normalize($direccion);
            if ($tel === null) {
                throw new DomainException('Teléfono remitente inválido.');
            }
            $direccion = $canal === 'whatsapp' ? 'whatsapp:' . $tel : $tel;
        }

        if ($msid !== '' && !preg_match('/^MG[0-9a-fA-F]{32}$/', $msid)) {
            throw new DomainException('Messaging Service SID inválido (debe iniciar con MG).');
        }

        if ($predet === 1) {
            $this->db->prepare("UPDATE com_remitentes SET predeterminado = 0 WHERE canal = ?")->execute([$canal]);
        }

        if ($id > 0) {
            $this->db->prepare(
                "UPDATE com_remitentes
                    SET canal=?, proveedor=?, etiqueta=?, direccion=?, nombre_remitente=?,
                        messaging_service_sid=?, soporta_media=?, limite_mps=?, activo=?, predeterminado=?,
                        fecha_actualizacion=NOW()
                  WHERE id=?"
            )->execute([$canal, $proveedor, $etiqueta, $direccion, $nombre ?: null, $msid ?: null,
                        $media, $limite, $activo, $predet, $id]);
        } else {
            $this->db->prepare(
                "INSERT INTO com_remitentes
                    (canal, proveedor, etiqueta, direccion, nombre_remitente, messaging_service_sid,
                     soporta_media, limite_mps, activo, predeterminado, creado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([$canal, $proveedor, $etiqueta, $direccion, $nombre ?: null, $msid ?: null,
                        $media, $limite, $activo, $predet, $actor['id'] ?? null]);
            $id = (int) $this->db->lastInsertId();
        }

        Logger::log($id > 0 ? 'guardar' : 'crear', 'comunicaciones.remitente', $actor['id'] ?? null,
            "Remitente $canal $direccion");
        return $id;
    }

    public function eliminar(int $id, ?array $actor = null): void
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM com_planes WHERE remitente_id = ?");
        $st->execute([$id]);
        if ((int) $st->fetchColumn() > 0) {
            throw new DomainException('No se puede eliminar: el remitente ya tiene planes asociados.');
        }
        $this->db->prepare("DELETE FROM com_remitentes WHERE id = ?")->execute([$id]);
        Logger::log('eliminar', 'comunicaciones.remitente', $actor['id'] ?? null, "Remitente #$id", null, null, 'warning');
    }

    /** Si un canal no tiene remitentes, crea los configurados en .env. */
    public function sembrarDesdeEnv(): void
    {
        $sms = trim((string) env('TWILIO_SMS_FROM', ''));
        $wa  = trim((string) env('TWILIO_WHATSAPP_FROM', ''));
        $ms  = trim((string) env('TWILIO_MESSAGING_SERVICE_SID', ''));
        $msWa = trim((string) (env('TWILIO_WHATSAPP_MESSAGING_SERVICE_SID') ?? '')) ?: $ms;
        $mail = trim((string) (env('SENDGRID_FROM') ?? env('SMTP_FROM') ?? ''));
        $mailName = trim((string) (env('SENDGRID_FROM_NAME') ?? 'Coomultiunion'));
        // El canal email usa SMTP (PHPMailer). SendGrid queda opcional/futuro.
        $mailProvider = 'smtp';

        try {
            if ($sms !== '' && !$this->existeCanal('sms')) {
                $tel = PhoneNormalizer::normalize($sms);
                if ($tel) {
                    $this->insertarSeed('sms', 'twilio', 'SMS Twilio', $tel, null, $ms ?: null, 0);
                }
            }
            if ($wa !== '' && !$this->existeCanal('whatsapp')) {
                $tel = PhoneNormalizer::normalize($wa);
                if ($tel) {
                    $this->insertarSeed('whatsapp', 'twilio', 'WhatsApp Twilio', 'whatsapp:' . $tel, null, $msWa ?: null, 1);
                }
            }
            if ($mail !== '' && PhoneNormalizer::isEmail($mail) && !$this->existeCanal('email')) {
                $this->insertarSeed('email', $mailProvider, 'Correo institucional', strtolower($mail), $mailName, null, 1);
            }
        } catch (Throwable $e) {
            error_log('[comunicaciones] sembrar remitentes: ' . $e->getMessage());
        }
    }

    private function existeCanal(string $canal): bool
    {
        $st = $this->db->prepare("SELECT COUNT(*) FROM com_remitentes WHERE canal = ?");
        $st->execute([$canal]);
        return (int) $st->fetchColumn() > 0;
    }

    private function insertarSeed(string $canal, string $proveedor, string $etiqueta, string $direccion, ?string $nombre, ?string $msid, int $media): void
    {
        $this->db->prepare(
            "INSERT INTO com_remitentes
                (canal, proveedor, etiqueta, direccion, nombre_remitente, messaging_service_sid,
                 soporta_media, limite_mps, activo, predeterminado)
             VALUES (?,?,?,?,?,?,?,?,1,1)
             ON DUPLICATE KEY UPDATE canal = canal"
        )->execute([$canal, $proveedor, $etiqueta, $direccion, $nombre, $msid, $media, 5]);
    }
}
