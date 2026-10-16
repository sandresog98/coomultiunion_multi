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
     * Envía un correo. Devuelve true si PHPMailer aceptó el envío.
     * Nunca lanza: ante cualquier fallo loguea y devuelve false.
     */
    public static function send(string $to, string $subject, string $body, ?string $fromName = null): bool
    {
        if (!static::disponible() || !static::isConfigured()) {
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

            $isHtml = stripos((string) $body, '<html') !== false || stripos((string) $body, '<body') !== false;
            $mail->setFrom(static::from(), $fromName ?: 'Coomultiunion');
            $mail->addAddress($to);
            $mail->isHTML($isHtml);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            if (!$isHtml) {
                $mail->AltBody = strip_tags($body);
            }
            $mail->send();
            return true;
        } catch (\Throwable $e) {
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