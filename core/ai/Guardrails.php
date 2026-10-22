<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';

/**
 * Guardarraíles del asistente (defensa en profundidad).
 *
 * NO es la única barrera: el system prompt (`PromptBuilder`) es la principal.
 * Aquí se valida el tamaño de la entrada y se rechazan de forma temprana los
 * temas claramente fuera de alcance (efecto "McDonald's") y los intentos de
 * manipulación más obvios. También se limpia mínimamente la salida.
 */
final class Guardrails
{
    /** Longitud máxima de la pregunta del asociado (caracteres). */
    public static function maxInput(): int
    {
        return max(100, (int) env('AI_CHAT_MAX_INPUT', '1500'));
    }

    /**
     * Valida la forma del mensaje. Lanza DomainException si está vacío o es
     * demasiado largo (antes de gastar tokens).
     */
    public static function validar(string $mensaje): void
    {
        if (trim($mensaje) === '') {
            throw new DomainException('Escribe tu mensaje.');
        }
        if (mb_strlen($mensaje, 'UTF-8') > self::maxInput()) {
            throw new DomainException('Tu mensaje es demasiado largo. Resúmelo e inténtalo de nuevo.');
        }
    }

    /**
     * Devuelve el texto de rechazo institucional si el mensaje parece fuera de
     * alcance, o null si se permite continuar hacia el modelo.
     */
    public static function rechazo(string $mensaje): ?string
    {
        $t = self::normalizar($mensaje);
        foreach (self::patronesProhibidos() as $patron) {
            if (mb_strpos($t, $patron) !== false) {
                return self::textoRechazo();
            }
        }
        return null;
    }

    /** Limpieza mínima de la salida (evita que se filtren etiquetas internas). */
    public static function limpiarSalida(string $respuesta): string
    {
        return trim($respuesta);
    }

    public static function textoRechazo(): string
    {
        return 'Con gusto te ayudo con temas de Coomultiunion y del uso de la aplicación. '
             . 'Esa consulta está fuera de lo que puedo atender; si necesitas otra cosa, '
             . 'escríbeme una duda sobre nuestros servicios o el portal.';
    }

    private static function normalizar(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim($s);
    }

    /** @return string[] */
    private static function patronesProhibidos(): array
    {
        return [
            // Manipulación / jailbreak
            'ignora tus instrucciones', 'ignora las instrucciones', 'ignore previous',
            'olvida tus instrucciones', 'olvida las instrucciones', 'ignore all previous',
            'system prompt', 'muestra tus instrucciones', 'revela tus instrucciones',
            'actua como', 'actúa como', 'pretend you are', 'jailbreak', 'dan mode',
            // Tareas académicas / código / temas generales (efecto McDonald's)
            'resuelve este ejercicio', 'resuelve la ecuacion', 'resuelve la ecuación',
            'escribe un codigo', 'escribe un código', 'escribe en python', 'escribe en javascript',
            'hazme un poema', 'escribe un poema', 'capital de francia', 'receta de cocina',
            'resumen de la pelicula', 'traduce este texto', 'ensayo sobre',
        ];
    }
}
