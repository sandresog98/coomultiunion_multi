<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/env.php';

/**
 * Envío real de correos vía SMTP con PHPMailer.
 *
 * Port de `multi_app/cx2/utils/email_helper.php` (sendEmail) a la infra v2.
 * REGLA: las credenciales se leen SOLO desde .env (nunca hardcodeadas).
 * Un fallo SMTP NUNCA rompe el flujo (try/catch); nunca se loguea la contraseña.
 */
final class Mailer
{
    private static string $lastError = '';

    public static function lastError(): string
    {
        return self::$lastError;
    }

    private const PHPMailer_DIRS = [
        '/opt/lampp/htdocs/projects/multi/multi_app/cx/PHPMailer',
    ];

    /** Carga PHPMailer una sola vez (require_once) y devuelve si está disponible. */
    public static function disponible(): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $dir = self::_phpmailerDir();
        if ($dir === null) {
            $ok = false;
            return false;
        }
        require_once $dir . '/Exception.php';
        require_once $dir . '/PHPMailer.php';
        require_once $dir . '/SMTP.php';
        $ok = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);
        return $ok;
    }

    /** ¿Hay SMTP configurado en .env? (host + user + pass). */
    public static function isConfigured(): bool
    {
        return self::host() !== ''
            && self::username() !== ''
            && self::password() !== '';
    }

    public static function host(): string
    {
        return (string) env('SMTP_HOST', '');
    }

    public static function port(): int
    {
        return (int) env('SMTP_PORT', '465');
    }

    public static function secure(): string
    {
        return (string) env('SMTP_SECURE', 'ssl');
    }

    public static function username(): string
    {
        return (string) env('SMTP_USER', '');
    }

    public static function password(): string
    {
        return (string) env('SMTP_PASS', '');
    }

    public static function from(): string
    {
        return (string) (env('SMTP_FROM') ?? env('MAIL_FROM') ?? 'no-reply@coomultiunion.com');
    }

    /**
     * Envía un correo simple. Devuelve true si PHPMailer aceptó el envío.
     * Nunca lanza: ante cualquier fallo loguea y devuelve false.
     */
    public static function send(string $to, string $subject, string $body, ?string $fromName = null): bool
    {
        return self::sendAdvanced($to, $subject, $body, ['fromName' => $fromName]);
    }

    /**
     * Envío SMTP con HTML, nombre de destinatario, remitente y adjuntos.
     *
     * $opts:
     *   from, fromName, toName, isHtml
     *   attachments: [['path'=>'/ruta/archivo.pdf','name'=>'x.pdf','mime'=>'application/pdf'], ...]
     *   headers: ['List-Unsubscribe' => '<url>', ...]
     *
     * Nunca lanza: devuelve false y deja el detalle en error_log.
     */
    public static function sendAdvanced(string $to, string $subject, string $body, array $opts = []): bool
    {
        self::$lastError = '';
        if (!static::disponible() || !static::isConfigured()) {
            self::$lastError = 'SMTP no configurado o PHPMailer ausente.';
            error_log('[mailer] SMTP no configurado o PHPMailer ausente; envío omitido.');
            return false;
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host       = static::host();
            $mail->SMTPAuth   = true;
            $mail->Username   = static::username();
            $mail->Password   = static::password();
            $mail->SMTPSecure = static::secure();
            $mail->Port       = static::port();
            $mail->CharSet    = 'UTF-8';
            $mail->Encoding   = 'base64';
            // HELO/Message-ID con el dominio del remitente (mejor entregabilidad
            // que usar el hostname interno del servidor web).
            $helo = trim((string) env('SMTP_HELO', '')) ?: static::host();
            if ($helo !== '') {
                $mail->Hostname = $helo;
            }

            $isHtml = (bool) ($opts['isHtml'] ?? (stripos($body, '<html') !== false || stripos($body, '<body') !== false));
            $mail->setFrom((string) ($opts['from'] ?? static::from()), (string) ($opts['fromName'] ?? 'Coomultiunion'));
            $mail->addAddress($to, (string) ($opts['toName'] ?? ''));
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            if (!empty($opts['altBody'])) {
                $mail->AltBody = (string) $opts['altBody'];
            } elseif (!$isHtml) {
                $mail->AltBody = strip_tags($body);
            }

            foreach (($opts['headers'] ?? []) as $name => $value) {
                if (is_string($name) && $name !== '' && is_scalar($value)) {
                    $mail->addCustomHeader($name, (string) $value);
                }
            }

            foreach (($opts['attachments'] ?? []) as $a) {
                if (!is_array($a)) { continue; }
                $name = (string) ($a['name'] ?? 'adjunto');
                $mime = (string) ($a['mime'] ?? 'application/octet-stream');
                if (!empty($a['path']) && is_file((string) $a['path'])) {
                    $mail->addAttachment((string) $a['path'], $name, 'base64', $mime);
                } elseif (isset($a['content'])) {
                    $mail->addStringAttachment((string) $a['content'], $name, 'base64', $mime);
                }
            }

            $mail->send();
            return true;
        } catch (\Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('[mailer] error de envío a ' . $to . ': ' . $e->getMessage());
            return false;
        }
    }

    /** Localiza el directorio de PHPMailer (candidatos fallback). */
    private static function _phpmailerDir(): ?string
    {
        $dirs = self::PHPMailer_DIRS;
        $rel  = dirname(__DIR__, 3) . '/multi_app/cx/PHPMailer';
        if (!in_array($rel, $dirs, true)) {
            $dirs[] = $rel;
        }
        foreach ($dirs as $d) {
            if ($d !== '' && is_file($d . '/PHPMailer.php')) {
                return $d;
            }
        }
        return null;
    }
}
