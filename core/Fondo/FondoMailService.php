<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Email/EmailCola.php';
require_once dirname(__DIR__) . '/Email/EmailTemplates.php';

/**
 * Emails del Fondo de Bienestar.
 *
 * ENCOLA el correo en la cola general `email_cola` (HTML vía EmailTemplates;
 * la infra de envío es Mailer + worker). Nunca rompe el flujo (try/catch).
 */
final class FondoMailService
{
    public function __construct(private ?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /** Encola un correo (estado 'pendiente'). Nunca lanza excepciones. */
    public function encolar(string $tipo, string $destino, ?string $asunto, ?string $cuerpo, ?int $solicitudId = null): void
    {
        try {
            EmailCola::encolar($tipo, $destino, $asunto, $cuerpo, $solicitudId);
        } catch (Throwable $e) {
            error_log('[fondo-mail] no se pudo encolar: ' . $e->getMessage());
        }
    }

    /** Notificación de aprobación a la entidad del convenio (si aplica). */
    public function notificarEntidad(string $emailEntidad, array $convenio, array $solicitud, ?string $comentarios, ?int $solicitudId = null): void
    {
        $asunto = 'Solicitud de Fondo de Bienestar aprobada';
        $cuerpo = EmailTemplates::fondoEntidadAprobada($convenio, $solicitud, $comentarios);
        $this->encolar('entidad', $emailEntidad, $asunto, $cuerpo, $solicitudId);
    }
}