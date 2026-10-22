<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/RateLimit.php';
require_once dirname(__DIR__) . '/config/ConfigService.php';
require_once dirname(__DIR__) . '/env.php';
require_once __DIR__ . '/ChatProvider.php';
require_once __DIR__ . '/SiliconFlowProvider.php';
require_once __DIR__ . '/ChatLimitException.php';
require_once __DIR__ . '/SesionCerradaException.php';
require_once __DIR__ . '/PricingService.php';
require_once __DIR__ . '/KnowledgeRepository.php';
require_once __DIR__ . '/PromptBuilder.php';
require_once __DIR__ . '/Guardrails.php';
require_once __DIR__ . '/tools/AsistenteTools.php';

/**
 * Servicio del asistente IA del PORTAL de asociados (cx/).
 *
 * REGLA DE ORO: TODA consulta se filtra por la cédula de la sesión (recibida
 * como $cedula). Nunca se confía en un parámetro del cliente para la identidad.
 *
 * Orquesta: guardarraíles → RAG → prompt → proveedor → post-filtro →
 * persistencia y contabilidad (tokens + costo USD).
 */
final class ChatService
{
    private PDO $db;
    private ChatProvider $provider;
    private PricingService $pricing;
    private KnowledgeRepository $knowledge;
    private PromptBuilder $prompt;
    private ConfigService $cfg;

    public function __construct(?PDO $db = null, ?ChatProvider $provider = null)
    {
        $this->db        = $db ?? db();
        $this->provider  = $provider ?? new SiliconFlowProvider();
        $this->pricing   = new PricingService($this->db);
        $this->knowledge = new KnowledgeRepository($this->db);
        $this->prompt    = new PromptBuilder();
        $this->cfg       = new ConfigService($this->db);
    }

    private function habilitado(): bool
    {
        return (string) $this->cfg->get('chat_ia_habilitado', '1') === '1';
    }

    // ── Sesiones ─────────────────────────────────────────────────────────
    /** Conversaciones del asociado (abiertas y cerradas; más recientes primero). */
    public function sesiones(string $cedula): array
    {
        $this->cerrarVencidas($cedula);
        $st = $this->db->prepare(
            "SELECT id, titulo, estado, fecha_creacion, fecha_actualizacion
               FROM chat_sesiones
              WHERE cedula = ? AND estado IN ('activa','cerrada')
              ORDER BY fecha_actualizacion DESC, id DESC
              LIMIT 100"
        );
        $st->execute([$cedula]);
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['abierta'] = ((string) $r['estado'] === 'activa');
        }
        unset($r);
        return $rows;
    }

    public function crearSesion(string $cedula, ?string $titulo = null): int
    {
        // Un asociado solo puede tener una conversación abierta: al crear una
        // nueva, la anterior se cierra.
        $this->cerrarActivas($cedula);

        $titulo = trim((string) $titulo);
        if ($titulo === '') {
            $titulo = 'Nueva conversación';
        }
        $st = $this->db->prepare('INSERT INTO chat_sesiones (cedula, titulo) VALUES (?, ?)');
        $st->execute([$cedula, mb_substr($titulo, 0, 120)]);
        $id = (int) $this->db->lastInsertId();
        Logger::log('crear', 'portal.chat', $cedula, "Conversación #$id creada");
        return $id;
    }

    /** Valida que la sesión exista y pertenezca al asociado. */
    private function sesionDe(int $sesionId, string $cedula): array
    {
        // La vigencia (estado activa y sin vencer por inactividad) se calcula con
        // NOW() del motor para no mezclar husos horarios entre PHP y la base de datos.
        $st = $this->db->prepare(
            'SELECT id, cedula, titulo, estado, fecha_actualizacion,
                    (estado = ? AND fecha_actualizacion >= (NOW() - INTERVAL ? MINUTE)) AS vigente
               FROM chat_sesiones WHERE id = ? LIMIT 1'
        );
        $st->execute(['activa', $this->ttlMinutos(), $sesionId]);
        $r = $st->fetch();
        if (!$r || (string) $r['cedula'] !== $cedula) {
            throw new DomainException('Conversación no encontrada.');
        }
        return $r;
    }

    /** Mensajes de una conversación propia (con la valoración del asociado). */
    public function mensajes(int $sesionId, string $cedula): array
    {
        $this->sesionDe($sesionId, $cedula);
        $st = $this->db->prepare(
            "SELECT m.id, m.rol, m.contenido, m.estado, m.fecha_creacion, f.util
               FROM chat_mensajes m
               LEFT JOIN chat_feedback f ON f.mensaje_id = m.id AND f.cedula = ?
              WHERE m.sesion_id = ? AND m.rol IN ('user','assistant')
              ORDER BY m.id ASC"
        );
        $st->execute([$cedula, $sesionId]);
        return $st->fetchAll();
    }

    public function eliminar(int $sesionId, string $cedula): void
    {
        $this->sesionDe($sesionId, $cedula);
        $this->db->prepare("UPDATE chat_sesiones SET estado = 'archivada' WHERE id = ? AND cedula = ?")
                 ->execute([$sesionId, $cedula]);
        Logger::log('eliminar', 'portal.chat', $cedula, "Conversación #$sesionId archivada", null, null, 'warning');
    }

    /** Marca actividad reciente en la conversación (para el TTL de inactividad). */
    private function tocarSesion(int $sesionId): void
    {
        $this->db->prepare('UPDATE chat_sesiones SET fecha_actualizacion = NOW() WHERE id = ?')
                 ->execute([$sesionId]);
    }

    /** ¿La conversación sigue abierta (estado activa y sin vencer por inactividad)? */
    private function esAbierta(array $sesion): bool
    {
        // Valor calculado en SQL (NOW() del motor); nunca se compara con time() de PHP.
        return (bool) ($sesion['vigente'] ?? false);
    }

    /** Cierra las conversaciones activas que superaron el TTL de inactividad. */
    private function cerrarVencidas(string $cedula): void
    {
        $this->db->prepare(
            "UPDATE chat_sesiones
                SET estado = 'cerrada'
              WHERE cedula = ? AND estado = 'activa'
                AND fecha_actualizacion < (NOW() - INTERVAL ? MINUTE)"
        )->execute([$cedula, $this->ttlMinutos()]);
    }

    /** Cierra todas las conversaciones activas del asociado (una sola a la vez). */
    private function cerrarActivas(string $cedula): void
    {
        $this->db->prepare("UPDATE chat_sesiones SET estado = 'cerrada' WHERE cedula = ? AND estado = 'activa'")
                 ->execute([$cedula]);
    }

    /** Minutos de inactividad tras los que se cierra una conversación. */
    private function ttlMinutos(): int
    {
        $v = (int) $this->cfg->get('chat_ia_sesion_ttl', (string) env('AI_CHAT_SESSION_TTL', '15'));
        return max(1, min(720, $v));
    }

    /** ¿El asistente puede ejecutar herramientas (consultar datos / crear solicitudes)? */
    private function herramientasHabilitadas(): bool
    {
        return (string) $this->cfg->get('chat_ia_herramientas', (string) env('AI_CHAT_TOOLS', '1')) === '1';
    }

    /** Nombre visible del asistente (configurable; se usa en el prompt). */
    private function nombre(): string
    {
        $v = trim((string) $this->cfg->get('chat_ia_nombre', (string) env('AI_CHAT_NOMBRE', 'Soli')));
        return $v !== '' ? mb_substr($v, 0, 40) : 'Soli';
    }

    // ── Envío de mensaje (núcleo) ────────────────────────────────────────
    /**
     * Procesa el mensaje del asociado y devuelve la respuesta del asistente.
     * @return array{sesion_id:int,respuesta:string,mensaje_id:int}
     */
    public function enviar(string $cedula, ?int $sesionId, string $mensaje): array
    {
        if (!$this->habilitado()) {
            throw new DomainException('El asistente no está disponible por ahora.');
        }
        Guardrails::validar($mensaje);
        $mensaje = trim($mensaje);

        // Rate-limit por asociado ANTES de persistir o rechazar: protege también
        // los mensajes filtrados por el guardarraíl (evita DoS de almacenamiento).
        $clave = 'chat:' . $cedula;
        if (RateLimit::bloqueado($clave, $this->rateMax(), $this->rateVentana())) {
            throw new ChatLimitException('Has enviado demasiados mensajes seguidos. Espera un momento e inténtalo de nuevo.');
        }
        RateLimit::registrar($clave);

        if ($sesionId === null || $sesionId <= 0) {
            $sesionId = $this->crearSesion($cedula, mb_substr($mensaje, 0, 60));
        } else {
            $sesion = $this->sesionDe($sesionId, $cedula);
            if (!$this->esAbierta($sesion)) {
                // Conversación cerrada por inactividad: se marca y se pide abrir una nueva.
                $this->db->prepare("UPDATE chat_sesiones SET estado = 'cerrada' WHERE id = ?")->execute([$sesionId]);
                throw new SesionCerradaException('Esta conversación se cerró por inactividad. Abre una nueva conversación para seguir.');
            }
        }

        // Marca de actividad reciente (TTL) y persistencia del turno del usuario.
        $this->tocarSesion($sesionId);
        $this->guardarMensaje($sesionId, 'user', $mensaje);

        // Guardarraíl estático: rechazo temprano sin gastar tokens.
        $rechazo = Guardrails::rechazo($mensaje);
        if ($rechazo !== null) {
            $mid = $this->guardarMensaje($sesionId, 'assistant', $rechazo, 'filtrado');
            return ['sesion_id' => $sesionId, 'respuesta' => $rechazo, 'mensaje_id' => $mid];
        }

        // Cupo diario (solo mensajes que realmente llegan al modelo).
        $this->verificarCupo($cedula);

        // Contexto: RAG ligero + historial reciente + prompt versionado.
        $fragmentos = $this->knowledge->buscar($mensaje, 4);
        $historial  = $this->historial($sesionId, $this->historialLimite());
        $contacto   = (string) $this->cfg->get('chat_ia_contacto_soporte', '');
        $version    = (string) $this->cfg->get('chat_ia_prompt_version', '');
        $maxTokens  = $this->maxTokens();
        $messages   = $this->prompt->mensajes($fragmentos, $historial, $contacto, $version, $this->nombre());

        try {
            $res = $this->procesarConHerramientas($messages, $cedula, $maxTokens);
        } catch (Throwable $e) {
            $this->guardarMensaje(
                $sesionId, 'assistant',
                'No pude procesar tu solicitud en este momento. Intenta de nuevo en unos minutos.',
                'error'
            );
            Logger::log('chat_ia', 'portal.chat', $cedula, 'Error del proveedor: ' . $e->getMessage(), null, null, 'warning');
            // Se normaliza a RuntimeException para que el endpoint responda 503 sin filtrar detalles internos.
            throw new RuntimeException('El asistente no está disponible en este momento.', 0, $e);
        }

        $texto = Guardrails::limpiarSalida($res['content']);
        $costo = $this->pricing->costoUsd(
            $res['model'],
            (int) $res['tokens_prompt'],
            (int) $res['tokens_completion'],
            (int) ($res['tokens_cached'] ?? 0)
        );
        $mid   = $this->guardarMensaje($sesionId, 'assistant', $texto, 'ok', $res, $costo);
        $this->registrarUso($cedula, $res, $costo);

        Logger::log(
            'chat_ia', 'portal.chat', $cedula,
            sprintf('sesion=%d modelo=%s tokens=%d/%d usd=%.6f', $sesionId, $res['model'], $res['tokens_prompt'], $res['tokens_completion'], $costo)
        );

        return ['sesion_id' => $sesionId, 'respuesta' => $texto, 'mensaje_id' => $mid];
    }

    /**
     * Bucle de function calling: el modelo puede consultar los datos del asociado
     * o crear solicitudes (a nombre propio) antes de redactar la respuesta.
     *
     * REGLA DE ORO: la identidad la fija el servidor ($cedula de la sesión); los
     * argumentos del modelo jamás eligen la cédula. Los resultados se resumen.
     *
     * @param array<int,array<string,mixed>> $messages
     * @return array{content:string,model:string,tokens_prompt:int,tokens_completion:int,tokens_cached:int,latencia_ms:int}
     */
    private function procesarConHerramientas(array $messages, string $cedula, int $maxTokens): array
    {
        if (!$this->herramientasHabilitadas()) {
            $r = $this->provider->chat($messages, ['max_tokens' => $maxTokens]);
            return [
                'content'           => (string) $r['content'],
                'model'             => (string) $r['model'],
                'tokens_prompt'     => (int) $r['tokens_prompt'],
                'tokens_completion' => (int) $r['tokens_completion'],
                'tokens_cached'     => (int) ($r['tokens_cached'] ?? 0),
                'latencia_ms'       => (int) ($r['latencia_ms'] ?? 0),
            ];
        }

        $tools = new AsistenteTools($cedula);
        $defs  = $tools->definiciones();

        $agg = [
            'content'           => '',
            'model'             => $this->provider->model(),
            'tokens_prompt'     => 0,
            'tokens_completion' => 0,
            'tokens_cached'     => 0,
            'latencia_ms'       => 0,
        ];
        $msgs = $messages;

        for ($i = 0; $i < 4; $i++) {
            $r = $this->provider->chat($msgs, ['max_tokens' => $maxTokens, 'tools' => $defs]);
            $agg['model']              = (string) $r['model'];
            $agg['tokens_prompt']     += (int) $r['tokens_prompt'];
            $agg['tokens_completion'] += (int) $r['tokens_completion'];
            $agg['tokens_cached']     += (int) ($r['tokens_cached'] ?? 0);
            $agg['latencia_ms']       += (int) ($r['latencia_ms'] ?? 0);

            if (empty($r['tool_calls'])) {
                $agg['content'] = (string) $r['content'];
                return $agg;
            }

            // El modelo pidió herramientas: se ejecutan y se devuelven sus resultados.
            $msgs[] = [
                'role'       => 'assistant',
                'content'    => (string) ($r['content'] ?? ''),
                'tool_calls' => $r['tool_calls'],
            ];
            foreach ($r['tool_calls'] as $tc) {
                $nombre = (string) ($tc['function']['name'] ?? '');
                $args   = json_decode((string) ($tc['function']['arguments'] ?? '{}'), true);
                if (!is_array($args)) {
                    $args = [];
                }
                $out = $tools->ejecutar($nombre, $args);
                Logger::log(
                    'chat_ia', 'portal.chat', $cedula,
                    'Herramienta ' . $nombre . ' → ' . (($out['ok'] ?? false) ? 'ok' : 'error'),
                    null, ['tool' => $nombre, 'ok' => (bool) ($out['ok'] ?? false)]
                );
                $msgs[] = [
                    'role'         => 'tool',
                    'tool_call_id' => (string) ($tc['id'] ?? ''),
                    'content'      => (string) (json_encode($out, JSON_UNESCAPED_UNICODE) ?: '{}'),
                ];
            }
        }

        // Se agotaron las iteraciones: se pide una respuesta final SIN herramientas.
        $r = $this->provider->chat($msgs, ['max_tokens' => $maxTokens]);
        $agg['model']              = (string) $r['model'];
        $agg['tokens_prompt']     += (int) $r['tokens_prompt'];
        $agg['tokens_completion'] += (int) $r['tokens_completion'];
        $agg['tokens_cached']     += (int) ($r['tokens_cached'] ?? 0);
        $agg['latencia_ms']       += (int) ($r['latencia_ms'] ?? 0);
        $agg['content']            = (string) $r['content'];
        return $agg;
    }

    // ── Feedback ─────────────────────────────────────────────────────────
    public function feedback(int $mensajeId, string $cedula, bool $util, ?string $comentario = null): void
    {
        $st = $this->db->prepare(
            'SELECT m.id
               FROM chat_mensajes m
               JOIN chat_sesiones s ON s.id = m.sesion_id
              WHERE m.id = ? AND s.cedula = ?
              LIMIT 1'
        );
        $st->execute([$mensajeId, $cedula]);
        if (!$st->fetch()) {
            throw new DomainException('Mensaje no encontrado.');
        }
        $com = $comentario !== null ? mb_substr(trim($comentario), 0, 500) : null;
        $this->db->prepare(
            'INSERT INTO chat_feedback (mensaje_id, cedula, util, comentario)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE util = VALUES(util), comentario = VALUES(comentario)'
        )->execute([$mensajeId, $cedula, $util ? 1 : 0, $com]);
        Logger::log('editar', 'portal.chat', $cedula,
            "Feedback mensaje #$mensajeId util=" . ($util ? 'sí' : 'no'));
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    private function guardarMensaje(
        int $sesionId,
        string $rol,
        string $contenido,
        ?string $estado = null,
        ?array $res = null,
        ?float $costo = null
    ): int {
        $st = $this->db->prepare(
            'INSERT INTO chat_mensajes
               (sesion_id, rol, contenido, modelo, tokens_prompt, tokens_completion, tokens_cached, latencia_ms, costo_usd, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->execute([
            $sesionId, $rol, $contenido,
            $res['model'] ?? null,
            $res['tokens_prompt'] ?? null,
            $res['tokens_completion'] ?? null,
            $res['tokens_cached'] ?? null,
            $res['latencia_ms'] ?? null,
            $costo,
            $estado,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** Últimos N turnos (user/assistant) en orden cronológico. */
    private function historial(int $sesionId, int $limite): array
    {
        $limite = max(2, min(20, $limite));
        $st = $this->db->prepare(
            "SELECT rol, contenido
               FROM chat_mensajes
              WHERE sesion_id = ? AND rol IN ('user','assistant')
              ORDER BY id DESC
              LIMIT " . $limite
        );
        $st->execute([$sesionId]);
        return array_reverse($st->fetchAll());
    }

    private function verificarCupo(string $cedula): void
    {
        $limite = (int) $this->cfg->get('chat_ia_limite_diario', '50');
        if ($limite <= 0) {
            return;
        }
        $st = $this->db->prepare('SELECT mensajes FROM chat_uso WHERE cedula = ? AND fecha = CURDATE() LIMIT 1');
        $st->execute([$cedula]);
        $usados = (int) ($st->fetchColumn() ?: 0);
        if ($usados >= $limite) {
            throw new ChatLimitException('Alcanzaste el límite diario de mensajes del asistente. Inténtalo mañana.');
        }
    }

    private function registrarUso(string $cedula, array $res, float $costo): void
    {
        $this->db->prepare(
            'INSERT INTO chat_uso (cedula, fecha, mensajes, tokens_prompt, tokens_completion, tokens_cached, costo_usd)
             VALUES (?, CURDATE(), 1, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               mensajes          = mensajes + 1,
               tokens_prompt     = tokens_prompt + VALUES(tokens_prompt),
               tokens_completion = tokens_completion + VALUES(tokens_completion),
               tokens_cached     = tokens_cached + VALUES(tokens_cached),
               costo_usd         = costo_usd + VALUES(costo_usd)'
        )->execute([
            $cedula,
            (int) $res['tokens_prompt'],
            (int) $res['tokens_completion'],
            (int) ($res['tokens_cached'] ?? 0),
            $costo,
        ]);
    }

    private function maxTokens(): int
    {
        $v = (int) $this->cfg->get('chat_ia_max_tokens', (string) env('AI_CHAT_MAX_TOKENS', '800'));
        return max(50, min(2000, $v));
    }

    private function historialLimite(): int
    {
        return max(2, min(20, (int) env('AI_CHAT_HISTORY_LIMIT', '6')));
    }

    private function rateMax(): int
    {
        return max(1, (int) env('AI_CHAT_RATE_MAX', '30'));
    }

    private function rateVentana(): int
    {
        return max(60, (int) env('AI_CHAT_RATE_WINDOW', '3600'));
    }
}
