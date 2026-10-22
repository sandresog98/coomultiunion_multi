<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';

/**
 * Validación de X-Twilio-Signature.
 *
 * Twilio firma URL + parámetros POST ordenados alfabéticamente con
 * HMAC-SHA1 y el Auth Token de la cuenta. La URL debe ser EXACTAMENTE la
 * configurada en Twilio (incluyendo esquema, dominio, ruta y query string).
 */
final class TwilioSignature
{
    /** URL pública del webhook de estados. */
    public static function statusUrl(): string
    {
        return self::urlConfigurada('TWILIO_STATUS_CALLBACK_URL', '/api/comunicaciones/webhooks/status.php');
    }

    /** URL pública del webhook de mensajes entrantes. */
    public static function inboundUrl(): string
    {
        return self::urlConfigurada('TWILIO_INBOUND_CALLBACK_URL', '/api/comunicaciones/webhooks/inbound.php');
    }

    /** URL base del proyecto (sin barra final). */
    public static function appUrl(): string
    {
        $url = trim((string) env('APP_URL', ''));
        if ($url === '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
            $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
            $url = ($https ? 'https' : 'http') . '://' . $host;
        }
        return rtrim($url, '/');
    }

    public static function validate(string $url, array $params, ?string $signature = null, ?string $authToken = null): bool
    {
        $signature = trim((string) ($signature ?? ($_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '')));
        $authToken = trim((string) ($authToken ?? env('TWILIO_AUTH_TOKEN', '')));
        if ($signature === '' || $authToken === '') {
            return false;
        }

        // Todos los parámetros, incluyendo los nuevos que Twilio agregue.
        $flat = [];
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                $flat[(string) $k] = implode('', array_map(static fn ($x) => (string) $x, $v));
            } else {
                $flat[(string) $k] = (string) $v;
            }
        }
        ksort($flat, SORT_STRING);

        $data = $url;
        foreach ($flat as $k => $v) {
            $data .= $k . $v;
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $authToken, true));
        return hash_equals($expected, $signature);
    }

    public static function validationEnabled(): bool
    {
        return (string) env('TWILIO_VALIDATE_SIGNATURE', '1') !== '0';
    }

    private static function urlConfigurada(string $envKey, string $defaultPath): string
    {
        $url = trim((string) env($envKey, ''));
        if ($url !== '') {
            return $url;
        }
        return self::appUrl() . $defaultPath;
    }
}
