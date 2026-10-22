<?php
declare(strict_types=1);

/**
 * Contrato del proveedor de IA para el chat del portal (API-first).
 *
 * Permite desacoplar el servicio de chat del proveedor concreto (SiliconFlow
 * hoy; OpenAI/Groq/Together/modelo local mañana) sin tocar `ChatService`.
 */
interface ChatProvider
{
    /**
     * Envía los mensajes al modelo y devuelve la respuesta normalizada.
     *
     * @param array<int,array<string,mixed>> $messages
     * @param array{temperature?:float,max_tokens?:int,tools?:array,tool_choice?:string} $opts
     * @return array{content:string,tool_calls:array,model:string,tokens_prompt:int,tokens_completion:int,tokens_cached:int,latencia_ms:int}
     *
     * @throws DomainException     Configuración ausente/ inválida (p. ej. falta la API key).
     * @throws RuntimeException    Fallo de red o respuesta de error del proveedor.
     */
    public function chat(array $messages, array $opts = []): array;
}
