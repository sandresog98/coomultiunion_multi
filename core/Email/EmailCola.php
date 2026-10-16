<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Cola general de salida de correos (`email_cola`).
 *
 * Punto único para ENCOLAR un email en toda la app (evita repetir el INSERT en
 * cada api/servicio). El envío SMTP real lo hace `core/Email/worker.php`.
 * NUNCA lanza: el encolado falla sin romper el flujo de negocio (try/catch).
 * La identidad/destinatario se resuelve y pasa quien llama; aquí solo se persiste.
 */
final class EmailCola
{
    /**
     * Encola un correo (estado 'pendiente').
     * @param int|null $solicitudId  id de `solicitudes` al que ligar (para el feed).
     */
    public static function encolar(string $tipo, string $destino, ?string $asunto, ?string $cuerpo, ?int $solicitudId = null): void
    {
        try {
            if (trim($destino) === '' || $destino === null) {
                return;
            }
            $st = db()->prepare(
                "INSERT INTO email_cola (tipo, destino, asunto, cuerpo, solicitud_id, estado)
                 VALUES (?,?,?,?,?,'pendiente')"
            );
            $st->execute([$tipo, $destino, $asunto, $cuerpo, $solicitudId]);
            error_log('[email-cola] encolado (' . $tipo . ' → ' . $destino . '): ' . substr((string) $asunto, 0, 80));
        } catch (Throwable $e) {
            error_log('[email-cola] no se pudo encolar: ' . $e->getMessage());
        }
    }

    /** Email del asociado (vw_asociados.mail) o null. Útil para destinataros por cédula. */
    public static function emailAsociado(string $cedula): ?string
    {
        try {
            $st = db()->prepare('SELECT email FROM vw_asociados WHERE cedula = ? LIMIT 1');
            $st->execute([$cedula]);
            $v = $st->fetchColumn();
            return $v !== false && $v !== null && trim((string) $v) !== '' ? (string) $v : null;
        } catch (Throwable $e) {
            error_log('[email-cola] emailAsociado: ' . $e->getMessage());
            return null;
        }
    }

    /** Email de un usuario staff (usuarios.email) o null. */
    public static function emailResponsable(int|string $userId): ?string
    {
        try {
            $st = db()->prepare('SELECT email FROM usuarios WHERE id = ? LIMIT 1');
            $st->execute([(int) $userId]);
            $v = $st->fetchColumn();
            return $v !== false && $v !== null && trim((string) $v) !== '' ? (string) $v : null;
        } catch (Throwable $e) {
            error_log('[email-cola] emailResponsable: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Aviso de NUEVA solicitud → responsable (si aplica). Se invoca tras crear.
     * @param array $d  detalle de la solicitud (solicitud.responsable_id, tipo_codigo, numero_radicado, cedula_solicitante).
     */
    public static function notificarSolicitudCreada(array $d): void
    {
        self::requireTemplates();
        $s = $d['solicitud'] ?? $d;
        $rid = isset($s['responsable_id']) && $s['responsable_id'] !== null ? (int) $s['responsable_id'] : null;
        if ($rid <= 0) {
            return; // sin responsable asignado aún; no hay a quién avisar.
        }
        $email = self::emailResponsable($rid);
        if (!$email) {
            return;
        }
        $categoria = (string) ($s['tipo_codigo'] ?? 'solicitud');
        $asunto = 'Nueva solicitud ' . strtoupper($categoria) . ' #' . ($s['id'] ?? '');
        $cuerpo = EmailTemplates::solicitudNuevaAviso(
            $categoria,
            (int) ($s['id'] ?? 0),
            (string) ($s['cedula_solicitante'] ?? '')
        );
        self::encolar('crear', $email, $asunto, $cuerpo, isset($s['id']) ? (int) $s['id'] : null);
    }

    /**
     * Notificación al asociado cuando su solicitud se RESUELVE (aprobada/rechazada).
     * Se invoca tras una transición/resolución en fase RESUELTA_*.
     * @param array $d     detalle de la solicitud.
     * @param string|null $comentario  comentario de la respuesta.
     */
    public static function notificarSolicitudResuelta(array $d, ?string $comentario = null): void
    {
        self::requireTemplates();
        $s = $d['solicitud'] ?? $d;
        // retiro_aporte ya notifica al asociado su propio efecto (retiroDesembolsado).
        if (($s['tipo_codigo'] ?? '') === 'retiro_aporte') {
            return;
        }
        $cedula = (string) ($s['cedula_solicitante'] ?? '');
        if ($cedula === '') {
            return;
        }
        $email = self::emailAsociado($cedula);
        if (!$email) {
            return;
        }
        $categoria = (string) ($s['tipo_codigo'] ?? 'solicitud');
        $resultado = (string) ($s['resultado'] ?? ($s['estado_codigo'] ?? ''));
        $asunto = 'Solicitud ' . strtoupper($categoria) . ' #' . ($s['id'] ?? '') . ' — respondida';

        // Enlace de calificación (token) si la solicitud lo generó.
        $urlCalificar = null;
        $token = (string) ($d['calificacion']['token_acceso'] ?? '');
        if ($token !== '') {
            $base = rtrim((string) (env('APP_URL') ?? ''), '/');
            $urlCalificar = $base . '/cx/calificar?t=' . urlencode($token);
        }

        $cuerpo = EmailTemplates::solicitudRespondida(
            (string) ($s['asociado_nombre'] ?? ''),
            $categoria,
            (int) ($s['id'] ?? 0),
            $resultado !== '' ? $resultado : 'respondida',
            $comentario,
            $urlCalificar
        );
        self::encolar('respondida', $email, $asunto, $cuerpo, isset($s['id']) ? (int) $s['id'] : null);
    }

    /** Carga EmailTemplates lazily (para no acoplar el require a la cola básica). */
    private static function requireTemplates(): void
    {
        require_once dirname(__DIR__) . '/Email/EmailTemplates.php';
    }
}