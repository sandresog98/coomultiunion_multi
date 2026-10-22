<?php
declare(strict_types=1);

require_once __DIR__ . '/ChatProvider.php';
require_once dirname(__DIR__) . '/env.php';

/**
 * Proveedor SiliconFlow (API compatible con OpenAI).
 *
 * La API key y la conexión viven SIEMPRE en `.env` (nunca en código ni en la BD):
 *   SILICONFLOW_API_KEY, SILICONFLOW_BASE_URL, SILICONFLOW_MODEL
 *
 * El modelo es configurable para poder cambiar a uno más económico/superior
 * sin re-desplegar lógica. La respuesta se normaliza al contrato `ChatProvider`.
 */
final class SiliconFlowProvider implements ChatProvider
{
    private string $apiKey;
    private string $baseUrl;
    private string $model;
    private int $timeout;
    private int $connectTimeout;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, ?string $model = null)
    {
        $this->apiKey         = trim((string) ($apiKey  ?? env('SILICONFLOW_API_KEY', '')));
        $this->baseUrl        = rtrim((string) ($baseUrl ?? env('SILICONFLOW_BASE_URL', 'https://api.siliconflow.com/v1')), '/');
        $this->model          = trim((string) ($model   ?? env('SILICONFLOW_MODEL', 'deepseek-ai/DeepSeek-V4.1-Flash')));
        $this->timeout        = max(5, (int) env('AI_CHAT_TIMEOUT', '30'));
        $this->connectTimeout = max(3, (int) env('AI_CHAT_CONNECT_TIMEOUT', '10'));
    }

    public function model(): string
    {
        return $this->model;
    }

    public function chat(array $messages, array $opts = []): array
    {
        if ($this->apiKey === '') {
            throw new DomainException('El asistente no está configurado (falta SILICONFLOW_API_KEY).');
        }

        $payload = [
            'model'       => $this->model,
            'messages'    => $messages,
            'temperature' => (float) ($opts['temperature'] ?? env('AI_CHAT_TEMPERATURE', '0.2')),
            'max_tokens'  => (int) ($opts['max_tokens'] ?? (int) env('AI_CHAT_MAX_TOKENS', '800')),
            'stream'      => false,
        ];
        if (!empty($opts['tools'])) {
            $payload['tools']       = $opts['tools'];
            $payload['tool_choice'] = $opts['tool_choice'] ?? 'auto';
        }

        $inicio = microtime(true);
        // 1 reintento ante fallo de red / 5xx (no en errores de validación 4xx).
        $intento = 0;
        do {
            $intento++;
            $res = $this->post('/chat/completions', $payload);
            $reintentar = ($res['red'] === false) || ($res['http'] >= 500);
        } while ($reintentar && $intento < 2);

        $latencia = (int) round((microtime(true) - $inicio) * 1000);

        if ($res['red'] === false) {
            error_log('[chat-ai] curl: ' . $res['error']);
            throw new RuntimeException('No se pudo contactar el servicio de IA.');
        }

        $data = json_decode((string) $res['body'], true);
        if ($res['http'] !== 200 || !is_array($data)) {
            // Se registra el cuerpo del error SIN la API key (nunca viaja en el body).
            error_log('[chat-ai] HTTP ' . $res['http'] . ' ' . mb_substr((string) $res['body'], 0, 500));
            throw new RuntimeException('El servicio de IA respondió con un error.');
        }

        $message   = $data['choices'][0]['message'] ?? [];
        $content   = trim((string) ($message['content'] ?? ''));
        $toolCalls = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];
        if ($content === '' && !$toolCalls) {
            throw new RuntimeException('El servicio de IA devolvió una respuesta vacía.');
        }

        return [
            'content'           => $content,
            'tool_calls'        => $toolCalls,
            'model'             => (string) ($data['model'] ?? $this->model),
            'tokens_prompt'     => (int) ($data['usage']['prompt_tokens'] ?? 0),
            'tokens_completion' => (int) ($data['usage']['completion_tokens'] ?? 0),
            'tokens_cached'     => (int) ($data['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
            'latencia_ms'       => $latencia,
        ];
    }

    /**
     * POST JSON vía cURL.
     * @return array{http:int,body:?string,red:bool,error:string}
     */
    private function post(string $ruta, array $payload): array
    {
        $ch = curl_init($this->baseUrl . $ruta);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = (string) curl_error($ch);
        curl_close($ch);

        return [
            'http'  => $http,
            'body'  => $body === false ? null : (string) $body,
            'red'   => $body !== false,
            'error' => $err,
        ];
    }
}
