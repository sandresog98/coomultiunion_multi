<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';

/**
 * Traduce tokens consumidos a costo en USD usando la tarifa vigente del modelo.
 *
 * Soporta:
 *  - Tarifa estándar y tarifa "con descuento" en una ventana horaria UTC
 *    (p. ej. DeepSeek-V4.1-Flash: estándar 00:00-14:00 UTC, descuento 14:00-24:00 UTC).
 *  - Tokens de entrada servidos desde **caché**, facturados a menor tarifa.
 *
 * La tabla `chat_precios_modelo` está versionada por `vigente_desde`, de modo que
 * un cambio de tarifa no altera los costos históricos ya calculados.
 */
final class PricingService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? db();
    }

    /**
     * Costo estimado (USD) de un turno.
     * `$tokensPrompt` incluye los tokens en caché (que se facturan más barato).
     * Si no hay tarifa registrada para el modelo, devuelve 0.0 (nunca rompe el flujo).
     */
    public function costoUsd(
        string $modelo,
        int $tokensPrompt,
        int $tokensCompletion,
        int $tokensCached = 0,
        ?string $fecha = null
    ): float {
        $fecha ??= date('Y-m-d H:i:s');
        try {
            $st = $this->db->prepare(
                'SELECT precio_input_usd, precio_output_usd, precio_cached_usd,
                        precio_input_off_usd, precio_output_off_usd, precio_cached_off_usd,
                        off_desde_utc, off_hasta_utc
                   FROM chat_precios_modelo
                  WHERE modelo = ? AND vigente_desde <= ?
                  ORDER BY vigente_desde DESC LIMIT 1'
            );
            $st->execute([$modelo, $fecha]);
            $r = $st->fetch();
        } catch (Throwable $e) {
            error_log('[chat-ai] pricing: ' . $e->getMessage());
            return 0.0;
        }
        if (!$r) {
            return 0.0;
        }

        $off = $this->enVentanaDescuento($r);
        $pIn  = (float) (($off && $r['precio_input_off_usd'] !== null)  ? $r['precio_input_off_usd']  : $r['precio_input_usd']);
        $pOut = (float) (($off && $r['precio_output_off_usd'] !== null) ? $r['precio_output_off_usd'] : $r['precio_output_usd']);
        $pCac = (float) (($off && $r['precio_cached_off_usd'] !== null) ? $r['precio_cached_off_usd'] : ($r['precio_cached_usd'] ?? 0));

        $cached   = max(0, min($tokensCached, $tokensPrompt));
        $noCached = max(0, $tokensPrompt - $cached);

        return ($noCached / 1_000_000) * $pIn
             + ($cached / 1_000_000) * $pCac
             + ($tokensCompletion / 1_000_000) * $pOut;
    }

    /** ¿La hora UTC actual cae dentro de la ventana con descuento del precio? */
    private function enVentanaDescuento(array $r): bool
    {
        if ($r['off_desde_utc'] === null || $r['off_hasta_utc'] === null) {
            return false;
        }
        if ($r['precio_input_off_usd'] === null && $r['precio_output_off_usd'] === null) {
            return false;
        }
        $h = (int) gmdate('G');
        $d = (int) $r['off_desde_utc'];
        $f = (int) $r['off_hasta_utc'];
        if ($d === $f) {
            return false;
        }
        return $d < $f ? ($h >= $d && $h < $f) : ($h >= $d || $h < $f);
    }
}
