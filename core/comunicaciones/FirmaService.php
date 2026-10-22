<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';

/**
 * Tokens HMAC para URLs públicas del módulo (media y baja de email).
 * El secreto vive en .env; no se expone en la URL.
 */
final class FirmaService
{
    public static function sign(array $payload, int $ttl = 3600): string
    {
        $payload['exp'] = $ttl > 0 ? (time() + $ttl) : 0;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $b64  = self::b64url((string) $json);
        $sig  = hash_hmac('sha256', $b64, self::secret());
        return $b64 . '.' . $sig;
    }

    /** @return array<string,mixed>|null */
    public static function verify(string $token): ?array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$b64, $sig] = $parts;
        $expected = hash_hmac('sha256', $b64, self::secret());
        if (!hash_equals($expected, $sig)) {
            return null;
        }
        $json = self::b64urlDecode($b64);
        $data = json_decode($json, true);
        if (!is_array($data)) {
            return null;
        }
        $exp = (int) ($data['exp'] ?? 0);
        if ($exp > 0 && $exp < time()) {
            return null;
        }
        return $data;
    }

    private static function secret(): string
    {
        $s = trim((string) env('COM_MEDIA_SECRET', ''));
        if ($s !== '') {
            return $s;
        }
        // Fallback determinista para instalaciones existentes. Se recomienda
        // definir COM_MEDIA_SECRET en .env para poder rotarlo sin tocar Twilio.
        $s = trim((string) env('TWILIO_AUTH_TOKEN', ''));
        if ($s !== '') {
            return hash('sha256', 'com-media|' . $s);
        }
        return hash('sha256', 'com-media|' . (string) env('APP_URL', 'multi'));
    }

    private static function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $data): string
    {
        $pad = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return (string) base64_decode(strtr($data, '-_', '+/'), true);
    }
}
