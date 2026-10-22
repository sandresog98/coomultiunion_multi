<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/ConfigService.php';

/**
 * Estimación de costos (USD). El costo REAL de Twilio se toma del campo
 * `price` del mensaje; aquí solo se proyecta antes de enviar.
 */
final class CostoService
{
    private ConfigService $cfg;

    public function __construct(?ConfigService $cfg = null)
    {
        $this->cfg = $cfg ?? new ConfigService();
    }

    public function tarifas(): array
    {
        return [
            'sms'      => (float) $this->cfg->get('com_tarifa_sms_usd', '0'),
            'whatsapp' => (float) $this->cfg->get('com_tarifa_whatsapp_usd', '0'),
            'email'    => (float) $this->cfg->get('com_tarifa_email_usd', '0'),
        ];
    }

    /** Estimación por destinatario (USD). */
    public function estimar(string $canal, ?string $texto = null, int $media = 0): float
    {
        $t = $this->tarifas();
        if ($canal === 'sms') {
            return $t['sms'] * $this->segmentosSms((string) $texto);
        }
        if ($canal === 'whatsapp') {
            return $t['whatsapp'];
        }
        if ($canal === 'email') {
            return $t['email'];
        }
        return 0.0;
    }

    public function estimarPlan(string $canal, ?string $texto, int $destinatarios, int $media = 0): float
    {
        return round($this->estimar($canal, $texto, $media) * max(0, $destinatarios), 6);
    }

    /**
     * Segmentos SMS aproximados. GSM-7: 160/153; UCS-2: 70/67.
     * La longitud real la confirma Twilio en `num_segments`.
     */
    public function segmentosSms(string $texto): int
    {
        $len = mb_strlen($texto, 'UTF-8');
        if ($len === 0) {
            return 1;
        }
        $gsm = $this->esGsm7($texto);
        if ($gsm) {
            return $len <= 160 ? 1 : (int) ceil($len / 153);
        }
        return $len <= 70 ? 1 : (int) ceil($len / 67);
    }

    private function esGsm7(string $texto): bool
    {
        // Caracteres GSM-7 básicos + extensión. Si aparece otro, se asume UCS-2.
        $permitidos = "@£\$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
        $extension = "^{}\\[~]|€";
        $chars = preg_split('//u', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $c) {
            if (strpos($permitidos, $c) === false && strpos($extension, $c) === false) {
                return false;
            }
        }
        return true;
    }

    public static function usd($v): string
    {
        return '$' . number_format((float) $v, 4, '.', ',') . ' USD';
    }
}
