<?php
declare(strict_types=1);

require_once __DIR__ . '/SendGridException.php';
require_once dirname(__DIR__) . '/env.php';

/**
 * Cliente mínimo de SendGrid (API de email de Twilio), sin Composer.
 * Envía correos transaccionales con HTML/texto y adjuntos base64.
 */
final class SendGridClient
{
    private string $apiKey;
    private int $timeout;
    private int $connectTimeout;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey         = trim((string) ($apiKey ?? env('SENDGRID_API_KEY', '')));
        $this->timeout        = max(5, (int) env('SENDGRID_TIMEOUT', '30'));
        $this->connectTimeout = max(3, (int) env('SENDGRID_CONNECT_TIMEOUT', '10'));
    }

    public static function fromEnv(): self
    {
        return new self();
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    /** Información básica de la cuenta (prueba de conexión). */
    public function account(): array
    {
        return $this->request('GET', 'https://api.sendgrid.com/v3/user/account');
    }

    /**
     * Envía un correo. Devuelve ['message_id'=>string,'http'=>int].
     * @param array<string,mixed> $opts
     */
    public function sendEmail(array $opts): array
    {
        $to      = trim((string) ($opts['to'] ?? ''));
        $from    = trim((string) ($opts['from'] ?? env('SENDGRID_FROM', '')));
        $subject = trim((string) ($opts['subject'] ?? ''));
        $html    = (string) ($opts['html'] ?? '');
        $text    = (string) ($opts['text'] ?? '');
        if ($to === '' || $from === '' || $subject === '') {
            throw new SendGridException('Faltan destinatario, remitente o asunto.');
        }
        if ($html === '' && $text === '') {
            $text = ' ';
        }

        $content = [];
        if ($text !== '') {
            $content[] = ['type' => 'text/plain', 'value' => $text];
        }
        if ($html !== '') {
            $content[] = ['type' => 'text/html', 'value' => $html];
        }

        $payload = [
            'personalizations' => [[
                'to' => [['email' => $to, 'name' => (string) ($opts['to_name'] ?? '')]],
            ]],
            'from' => [
                'email' => $from,
                'name'  => (string) ($opts['from_name'] ?? 'Coomultiunion'),
            ],
            'subject'  => $subject,
            'content'  => $content,
        ];

        if (!empty($opts['attachments']) && is_array($opts['attachments'])) {
            $payload['attachments'] = $opts['attachments'];
        }
        if (!empty($opts['custom_args']) && is_array($opts['custom_args'])) {
            $payload['custom_args'] = $opts['custom_args'];
        }
        if (!empty($opts['headers']) && is_array($opts['headers'])) {
            $payload['headers'] = $opts['headers'];
        }
        if (!empty($opts['categories']) && is_array($opts['categories'])) {
            $payload['categories'] = array_values(array_map('strval', $opts['categories']));
        }
        if (!empty($opts['asm'])) {
            $payload['asm'] = $opts['asm'];
        }
        if (!empty($opts['mail_settings'])) {
            $payload['mail_settings'] = $opts['mail_settings'];
        }
        if (!empty($opts['tracking_settings'])) {
            $payload['tracking_settings'] = $opts['tracking_settings'];
        }

        $headers = [];
        $res = $this->request('POST', 'https://api.sendgrid.com/v3/mail/send', $payload, $headers);
        $messageId = '';
        foreach ($headers as $h) {
            if (stripos($h, 'X-Message-Id:') === 0) {
                $messageId = trim(substr($h, strlen('X-Message-Id:')));
                break;
            }
        }
        return ['message_id' => $messageId, 'http' => (int) ($res['_http'] ?? 202)];
    }

    /**
     * @param array<string,mixed>|null $json
     * @param array<int,string> $responseHeaders
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, ?array $json = null, array &$responseHeaders = []): array
    {
        if ($this->apiKey === '') {
            throw new SendGridException('SendGrid no está configurado (falta SENDGRID_API_KEY).');
        }

        $ch = curl_init($url);
        $payload = $json !== null ? json_encode($json, JSON_UNESCAPED_UNICODE) : null;
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                $responseHeaders[] = trim($line);
                return strlen($line);
            },
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string) curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new SendGridException('No se pudo contactar a SendGrid: ' . $err);
        }
        if ($http < 200 || $http >= 300) {
            $decoded = json_decode((string) $body, true);
            $msg = is_array($decoded) && isset($decoded['errors'])
                ? (string) ($decoded['errors'][0]['message'] ?? 'SendGrid respondió con error')
                : 'SendGrid respondió con HTTP ' . $http;
            error_log('[sendgrid] HTTP ' . $http . ' ' . mb_substr($msg, 0, 250));
            throw new SendGridException($msg, $http, is_array($decoded) ? $decoded : null);
        }

        $decoded = json_decode((string) $body, true);
        if (is_array($decoded)) {
            $decoded['_http'] = $http;
            return $decoded;
        }
        return ['_http' => $http];
    }
}
