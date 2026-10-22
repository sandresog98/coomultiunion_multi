<?php
declare(strict_types=1);

require_once __DIR__ . '/TwilioException.php';
require_once dirname(__DIR__) . '/env.php';

/**
 * Cliente REST mínimo de Twilio (sin Composer / sin SDK).
 *
 * Autenticación:
 *   - API Key SID + Secret (recomendado para REST).
 *   - Account SID + Auth Token (fallback).
 * El Auth Token se necesita igualmente para validar la firma de los webhooks.
 *
 * APIs cubiertas:
 *   - Programmable Messaging (Messages)
 *   - Balance / Account
 *   - Content API (plantillas de WhatsApp)
 *   - Usage Records (conciliación de costos)
 */
final class TwilioClient
{
    private string $accountSid;
    private string $username;
    private string $password;
    private string $apiBase;
    private string $contentBase;
    private int $timeout;
    private int $connectTimeout;

    public function __construct(
        ?string $accountSid = null,
        ?string $apiKeySid = null,
        ?string $apiSecret = null,
        ?string $authToken = null
    ) {
        $this->accountSid = trim((string) ($accountSid ?? env('TWILIO_ACCOUNT_SID', '')));
        $apiKeySid        = trim((string) ($apiKeySid ?? env('TWILIO_API_KEY_SID', '')));
        $apiSecret        = trim((string) ($apiSecret ?? env('TWILIO_API_SECRET', '')));
        $authToken        = trim((string) ($authToken ?? env('TWILIO_AUTH_TOKEN', '')));

        if ($apiKeySid !== '' && $apiSecret !== '') {
            $this->username = $apiKeySid;
            $this->password = $apiSecret;
        } else {
            $this->username = $this->accountSid;
            $this->password = $authToken;
        }

        $this->apiBase     = 'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($this->accountSid);
        $this->contentBase = 'https://content.twilio.com/v1';
        $this->timeout        = max(5, (int) env('TWILIO_TIMEOUT', '30'));
        $this->connectTimeout = max(3, (int) env('TWILIO_CONNECT_TIMEOUT', '10'));
    }

    public static function fromEnv(): self
    {
        return new self();
    }

    public function isConfigured(): bool
    {
        return $this->accountSid !== '' && $this->username !== '' && $this->password !== '';
    }

    public function accountSid(): string
    {
        return $this->accountSid;
    }

    /** Cuenta (tipo/estado: útil para detectar trial). */
    public function account(): array
    {
        return $this->request(
            'GET',
            'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode($this->accountSid) . '.json'
        );
    }

    /** Saldo disponible de la cuenta. */
    public function balance(): array
    {
        return $this->request('GET', $this->apiBase . '/Balance.json');
    }

    /** Crea un mensaje SMS/WhatsApp. */
    public function createMessage(array $params): array
    {
        return $this->request('POST', $this->apiBase . '/Messages.json', $params);
    }

    public function fetchMessage(string $sid): array
    {
        return $this->request('GET', $this->apiBase . '/Messages/' . rawurlencode($sid) . '.json');
    }

    /** Lista mensajes (filtros Twilio: To, From, DateSent, PageSize...). */
    public function listMessages(array $query = []): array
    {
        return $this->request('GET', $this->apiBase . '/Messages.json' . $this->query($query));
    }

    /** Plantillas con su estado de aprobación de WhatsApp. */
    public function listContentAndApprovals(int $pageSize = 100, int $maxPages = 10): array
    {
        $url = $this->contentBase . '/ContentAndApprovals?PageSize=' . max(1, min(500, $pageSize));
        $out = [];
        for ($i = 0; $i < $maxPages && $url !== ''; $i++) {
            $res = $this->request('GET', $url);
            foreach (($res['contents'] ?? []) as $c) {
                if (is_array($c)) {
                    $out[] = $c;
                }
            }
            $next = (string) ($res['meta']['next_page_url'] ?? '');
            if ($next !== '' && str_starts_with($next, '/')) {
                $next = 'https://content.twilio.com' . $next;
            }
            $url = $next !== '' ? $next : '';
        }
        return $out;
    }

    public function createContent(array $payload): array
    {
        return $this->request('POST', $this->contentBase . '/Content', null, $payload);
    }

    public function submitWhatsAppApproval(string $contentSid, string $name, string $category = 'UTILITY'): array
    {
        return $this->request(
            'POST',
            $this->contentBase . '/Content/' . rawurlencode($contentSid) . '/ApprovalRequests/whatsapp',
            null,
            ['name' => $name, 'category' => strtoupper($category)]
        );
    }

    public function deleteContent(string $contentSid, bool $deleteInWaba = false): array
    {
        $q = $deleteInWaba ? '?deleteInWaba=true' : '';
        return $this->request(
            'DELETE',
            $this->contentBase . '/Content/' . rawurlencode($contentSid) . $q
        );
    }

    /** Usage Records diarios (opcional, para conciliación). */
    public function listUsageRecords(array $query = []): array
    {
        return $this->request('GET', $this->apiBase . '/Usage/Records/Daily.json' . $this->query($query));
    }

    // ──────────────────────────────────────────────────────────────────

    private function query(array $query): string
    {
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        return $query ? ('?' . http_build_query($query)) : '';
    }

    /**
     * Petición HTTP con reintento para fallos de red/429/5xx.
     * @param array<string,mixed>|null $form
     * @param array<string,mixed>|null $json
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, ?array $form = null, ?array $json = null): array
    {
        if (!$this->isConfigured()) {
            throw new TwilioException('Twilio no está configurado (faltan credenciales en .env).');
        }

        $intento = 0;
        do {
            $intento++;
            [$http, $body, $err] = $this->curl($method, $url, $form, $json);
            $reintentar = ($http === 0 || $http === 429 || $http >= 500) && $intento < 3;
            if ($reintentar) {
                usleep(300000 * $intento);
            }
        } while ($reintentar);

        $decoded = null;
        if ($body !== '') {
            $tmp = json_decode($body, true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }

        if ($http >= 200 && $http < 300) {
            return is_array($decoded) ? $decoded : ['raw' => $body];
        }

        if ($http === 0) {
            throw new TwilioException('No se pudo contactar a Twilio: ' . $err);
        }

        $code = isset($decoded['code']) ? (int) $decoded['code'] : null;
        $msg  = (string) ($decoded['message'] ?? 'Twilio respondió con HTTP ' . $http);
        error_log('[twilio] HTTP ' . $http . ' code=' . (string) $code . ' ' . mb_substr($msg, 0, 250));
        throw new TwilioException($msg, $http, $code, is_array($decoded) ? $decoded : null);
    }

    /**
     * @return array{0:int,1:string,2:string} [http, body, curlError]
     */
    private function curl(string $method, string $url, ?array $form, ?array $json): array
    {
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        $payload = null;

        if ($json !== null) {
            $payload = json_encode($json, JSON_UNESCAPED_UNICODE);
            $headers[] = 'Content-Type: application/json';
        } elseif ($form !== null) {
            $payload = $this->encodeForm($form);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERPWD        => $this->username . ':' . $this->password,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string) curl_error($ch);
        curl_close($ch);

        return [$http, $body === false ? '' : (string) $body, $err];
    }

    /**
     * Twilio acepta MediaUrl repetido (no MediaUrl[]). http_build_query
     * genera corchetes; este encoder mantiene la forma que espera la API.
     * @param array<string,mixed> $data
     */
    private function encodeForm(array $data): string
    {
        $pairs = [];
        foreach ($data as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (is_array($v)) {
                foreach ($v as $item) {
                    if ($item !== null && $item !== '') {
                        $pairs[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $item);
                    }
                }
            } elseif (is_bool($v)) {
                $pairs[] = rawurlencode((string) $k) . '=' . ($v ? 'true' : 'false');
            } else {
                $pairs[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
            }
        }
        return implode('&', $pairs);
    }
}
