<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';
require_once dirname(__DIR__) . '/ai/SiliconFlowProvider.php';
require_once dirname(__DIR__) . '/ai/Guardrails.php';
require_once dirname(__DIR__) . '/ai/PricingService.php';
require_once dirname(__DIR__) . '/Email/EmailTemplates.php';

/**
 * Generación asistida de mensajes/HTML para el módulo de comunicaciones.
 * Usa el mismo proveedor/configuración del asistente IA (SILICONFLOW_*).
 */
final class IaRedaccionService
{
    private SiliconFlowProvider $provider;

    public function __construct(?SiliconFlowProvider $provider = null)
    {
        $this->provider = $provider ?? new SiliconFlowProvider();
    }

    /**
     * @return array{texto:string,modelo:string}
     */
    public function generar(string $tipo, string $canal, string $objetivo, string $tono = 'institucional', string $largo = 'medio', string $contexto = ''): array
    {
        if ((string) env('AI_CHAT_ENABLED', '1') === '0') {
            throw new DomainException('La ayuda IA está deshabilitada.');
        }
        if (!in_array($tipo, ['mensaje', 'html', 'asunto', 'titulo'], true)) {
            throw new DomainException('Tipo de generación inválido.');
        }
        if (!in_array($canal, ['whatsapp', 'sms', 'email'], true)) {
            throw new DomainException('Canal inválido.');
        }
        $objetivo = trim($objetivo);
        $contexto = trim($contexto);
        if ($objetivo === '') {
            throw new DomainException('Indique qué debe comunicar el mensaje.');
        }
        if (mb_strlen($objetivo, 'UTF-8') > 1200) {
            throw new DomainException('El objetivo es demasiado largo.');
        }
        Guardrails::validar($objetivo);
        $rechazo = Guardrails::rechazo($objetivo);
        if ($rechazo !== null) {
            throw new DomainException($rechazo);
        }

        $tonos = [
            'institucional' => 'institucional, cordial y claro',
            'cercano' => 'cercano, amable y humano',
            'formal' => 'formal y sobrio',
            'motivador' => 'motivador y positivo, sin exagerar',
        ];
        $tono = $tonos[$tono] ?? $tonos['institucional'];
        $largos = [
            'breve' => 'muy breve (máximo 2 frases)',
            'medio' => 'de extensión media (3 a 5 frases)',
            'amplio' => 'con detalle suficiente (hasta 8 frases)',
        ];
        $largo = $largos[$largo] ?? $largos['medio'];

        $system = <<<TXT
Eres redactor de comunicaciones de Coomultiunion, una cooperativa financiera colombiana.
Escribe en español de Colombia, con ortografía y puntuación impecables.

Reglas:
- Devuelve ÚNICAMENTE el texto solicitado, sin explicaciones, sin markdown de bloque de código y sin comillas envolventes.
- No inventes datos, saldos, fechas, valores, números de teléfono, enlaces ni promesas.
- No incluyas datos personales reales; usa variables cuando necesites personalizar: {{nombre}}, {{cedula}}, {{telefono}}, {{email}}.
- Si el canal es SMS: texto plano, sin HTML, sin saltos de línea innecesarios, máximo 300 caracteres.
- Si el canal es WhatsApp: texto claro y breve; puedes usar como máximo 2 emojis.
- Si el canal es email: puede ser texto plano o HTML, según se solicite.
- Si se pide HTML: entrega solo un fragmento HTML con estilos inline, compatible con clientes de correo; no uses <script>, <style> externo, iframes ni recursos externos.
- Si se pide asunto o título: entrega una sola línea de máximo 80 caracteres, sin comillas, sin punto final innecesario y sin explicaciones.
- No incluyas instrucciones para el operador ni notas como "aquí tienes".
TXT;

        $etiquetaTipo = [
            'html' => 'HTML para email',
            'asunto' => 'asunto de correo',
            'titulo' => 'título breve del plan de comunicación',
            'mensaje' => 'mensaje de texto',
        ][$tipo] ?? 'mensaje de texto';
        $user = "Tipo de contenido: {$etiquetaTipo}\n"
            . "Canal: {$canal}\n"
            . "Tono: {$tono}\n"
            . "Extensión: {$largo}\n"
            . "Objetivo: {$objetivo}\n";
        if ($contexto !== '') {
            $user .= "Contexto o borrador actual (mejóralo, no lo copies literalmente si no aporta):\n{$contexto}\n";
        }
        if ($tipo === 'html') {
            $user .= "Entrega un fragmento HTML listo para pegar en el cuerpo del correo, con estilos inline.\n";
        } elseif ($tipo === 'asunto' || $tipo === 'titulo') {
            $user .= "Entrega solo la línea final, sin comillas ni explicaciones.\n";
        } else {
            $user .= "Entrega solo el mensaje final.\n";
        }

        try {
            $res = $this->provider->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], [
                'temperature' => 0.35,
                'max_tokens' => $tipo === 'html' ? 1600 : (($tipo === 'asunto' || $tipo === 'titulo') ? 350 : 700),
            ]);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('[comunicaciones][ia] ' . $e->getMessage());
            throw new DomainException('No se pudo generar el contenido con IA. Verifique SILICONFLOW_API_KEY.');
        }

        $texto = Guardrails::limpiarSalida((string) ($res['content'] ?? ''));
        $texto = preg_replace('/^```(?:html|texto|text)?\s*/i', '', $texto) ?? $texto;
        $texto = preg_replace('/\s*```$/', '', $texto) ?? $texto;
        $texto = trim($texto);
        if ($tipo === 'html') {
            $texto = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $texto) ?? $texto;
            $texto = preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $texto) ?? $texto;
            if (substr_count($texto, '<') !== substr_count($texto, '>')) {
                throw new DomainException('La IA devolvió un HTML incompleto. Intente generar de nuevo.');
            }
            // Envoltura corporativa similar a los correos del portal CX.
            if (stripos($texto, '<html') === false && stripos($texto, '<body') === false) {
                $texto = EmailTemplates::base('Comunicación Coomultiunion', $texto);
            }
        }
        if ($tipo === 'asunto' || $tipo === 'titulo') {
            $texto = trim(str_replace(["\r", "\n"], ' ', $texto));
            $texto = trim($texto, " \t\n\r\0\x0B\"'“”");
            $texto = mb_substr($texto, 0, 80);
        }
        if ($texto === '') {
            throw new DomainException('La IA devolvió una respuesta vacía. Intente de nuevo.');
        }

        $modelo = (string) ($res['model'] ?? $this->provider->model());
        $tokensPrompt = (int) ($res['tokens_prompt'] ?? 0);
        $tokensCompletion = (int) ($res['tokens_completion'] ?? 0);
        $tokensCached = (int) ($res['tokens_cached'] ?? 0);
        $costo = 0.0;
        try {
            $costo = (new PricingService())->costoUsd($modelo, $tokensPrompt, $tokensCompletion, $tokensCached);
        } catch (Throwable $e) {
            error_log('[comunicaciones][ia] costo: ' . $e->getMessage());
        }

        return [
            'texto' => $texto,
            'modelo' => $modelo,
            'tokens_prompt' => $tokensPrompt,
            'tokens_completion' => $tokensCompletion,
            'tokens_cached' => $tokensCached,
            'costo_usd' => round($costo, 6),
        ];
    }

    /**
     * Revisa coherencia editorial de un borrador.
     * @param array<string,mixed> $data
     * @return array{ok:bool,resumen:string,problemas:array<int,string>,sugerencias:array<int,string>,modelo:string,costo_usd:float,tokens_prompt:int,tokens_completion:int}
     */
    public function validarCoherencia(array $data): array
    {
        if ((string) env('AI_CHAT_ENABLED', '1') === '0') {
            throw new DomainException('La ayuda IA está deshabilitada.');
        }
        $canal = trim((string) ($data['canal'] ?? ''));
        if (!in_array($canal, ['whatsapp', 'sms', 'email'], true)) {
            throw new DomainException('Canal inválido.');
        }
        $asunto = trim((string) ($data['asunto'] ?? ''));
        $cuerpo = trim((string) ($data['cuerpo'] ?? ''));
        $html = trim((string) ($data['cuerpo_html'] ?? ''));
        $formato = trim((string) ($data['formato'] ?? 'texto'));
        $adjuntos = is_array($data['adjuntos'] ?? null) ? $data['adjuntos'] : [];
        $destinatarios = max(0, (int) ($data['destinatarios'] ?? 0));

        $adj = [];
        foreach ($adjuntos as $a) {
            if (is_array($a)) {
                $adj[] = trim((string) ($a['nombre'] ?? $a['name'] ?? '')) . ' (' . trim((string) ($a['mime'] ?? '')) . ')';
            } elseif (is_scalar($a)) {
                $adj[] = (string) $a;
            }
        }

        $system = <<<TXT
Eres revisor editorial de comunicaciones de Coomultiunion, una cooperativa financiera colombiana.
Revisa un borrador antes de enviarlo. Sé concreto, breve y exigente con la claridad y la coherencia.
Responde SOLO en este formato, sin markdown y sin JSON, máximo 120 palabras:
OK: SI o NO
RESUMEN: una frase breve
PROBLEMAS:
- un problema por línea
SUGERENCIAS:
- una sugerencia por línea

Criterios:
- Coherencia entre asunto, texto, HTML y adjuntos.
- El asunto debe describir el contenido; el HTML no debe contradecir el texto.
- Detecta HTML incompleto, etiquetas sin cerrar, variables sin reemplazar, enlaces o datos inventados.
- SMS no admite adjuntos; WhatsApp/email sí, con límites razonables.
- Señala faltantes de información, tono inadecuado, promesas o riesgos.
- Si no hay problemas, OK: SI y deja PROBLEMAS vacío. No inventes datos.
- Máximo 3 problemas y 3 sugerencias; cada uno en una frase corta.
- Las variables {{nombre}}, {{cedula}}, {{telefono}} y {{email}} son válidas; el sistema las reemplaza antes del envío, no las reportes como error.
- Si el formato es HTML, el texto plano alternativo puede estar vacío; eso no es un problema por sí solo.
TXT;

        $user = "Canal: {$canal}\nFormato email: {$formato}\nDestinatarios: {$destinatarios}\n"
            . "Asunto:\n" . mb_substr($asunto, 0, 500) . "\n\n"
            . "Texto:\n" . mb_substr($cuerpo, 0, 2500) . "\n\n"
            . "HTML:\n" . mb_substr($html, 0, 5000) . "\n\n"
            . "Adjuntos:\n" . (implode("\n", $adj) ?: '(ninguno)') . "\n";

        try {
            $res = $this->provider->chat([
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ], ['temperature' => 0.1, 'max_tokens' => 2500]);
        } catch (DomainException $e) {
            throw $e;
        } catch (Throwable $e) {
            error_log('[comunicaciones][ia-validar] ' . $e->getMessage());
            throw new DomainException('No se pudo validar con IA. Verifique SILICONFLOW_API_KEY.');
        }

        $raw = Guardrails::limpiarSalida((string) ($res['content'] ?? ''));
        $ok = false;
        if (preg_match('/OK\s*:\s*(SI|SÍ|NO)/iu', $raw, $m)) {
            $ok = in_array(mb_strtoupper($m[1]), ['SI', 'SÍ'], true);
        }
        $resumen = '';
        if (preg_match('/RESUMEN\s*:\s*(.+)/iu', $raw, $m)) {
            $resumen = mb_substr(trim($m[1]), 0, 500);
        }
        $problemas = $this->extraerLista($raw, 'PROBLEMAS');
        $sugerencias = $this->extraerLista($raw, 'SUGERENCIAS');
        if ($resumen === '') {
            $resumen = $ok ? 'Sin observaciones críticas.' : 'La IA no devolvió un resumen interpretable.';
        }

        $modelo = (string) ($res['model'] ?? $this->provider->model());
        $tokensPrompt = (int) ($res['tokens_prompt'] ?? 0);
        $tokensCompletion = (int) ($res['tokens_completion'] ?? 0);
        $tokensCached = (int) ($res['tokens_cached'] ?? 0);
        $costo = 0.0;
        try {
            $costo = (new PricingService())->costoUsd($modelo, $tokensPrompt, $tokensCompletion, $tokensCached);
        } catch (Throwable $e) {
            error_log('[comunicaciones][ia-validar] costo: ' . $e->getMessage());
        }

        return [
            'ok' => $ok,
            'resumen' => $resumen,
            'problemas' => $problemas,
            'sugerencias' => $sugerencias,
            'modelo' => $modelo,
            'costo_usd' => round($costo, 6),
            'tokens_prompt' => $tokensPrompt,
            'tokens_completion' => $tokensCompletion,
        ];
    }

    /** Extrae viñetas de una sección (PROBLEMAS/SUGERENCIAS). */
    private function extraerLista(string $texto, string $seccion): array
    {
        $out = [];
        $patron = '/' . preg_quote($seccion, '/') . '\s*:\s*(.*?)(?=(?:PROBLEMAS|SUGERENCIAS)\s*:|$)/is';
        if (preg_match($patron, $texto, $m)) {
            foreach (preg_split('/\r?\n/', (string) $m[1]) as $linea) {
                $linea = trim((string) preg_replace('/^[\-\*\x{2022}\d\.\)]+\s*/u', '', $linea));
                if ($linea !== '' && mb_strtoupper($linea) !== 'NINGUNO') {
                    $out[] = mb_substr($linea, 0, 300);
                }
                if (count($out) >= 3) { break; }
            }
        }
        return $out;
    }
}
