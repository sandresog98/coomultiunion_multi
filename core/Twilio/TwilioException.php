<?php
declare(strict_types=1);

/**
 * Error normalizado de la API de Twilio.
 * Conserva el código HTTP y el código de error de Twilio para poder mapear
 * errores permanentes (número inválido, opt-out, ventana de WhatsApp…) a
 * estados de negocio sin exponer credenciales.
 */
final class TwilioException extends RuntimeException
{
    public int $httpStatus;
    public ?int $twilioCode;
    public string $twilioMessage;
    /** @var array<string,mixed>|null */
    public ?array $response;

    /** @param array<string,mixed>|null $response */
    public function __construct(string $message, int $httpStatus = 0, ?int $twilioCode = null, ?array $response = null)
    {
        parent::__construct($message);
        $this->httpStatus    = $httpStatus;
        $this->twilioCode    = $twilioCode;
        $this->twilioMessage = $message;
        $this->response      = $response;
    }

    public function esTransitorio(): bool
    {
        return $this->httpStatus === 0 || $this->httpStatus === 429 || $this->httpStatus >= 500;
    }
}
