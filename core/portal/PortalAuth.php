<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/database.php';
require_once dirname(__DIR__) . '/Http.php';
require_once dirname(__DIR__) . '/Csrf.php';
require_once dirname(__DIR__) . '/Logger.php';
require_once dirname(__DIR__) . '/RateLimit.php';
require_once dirname(__DIR__) . '/Email/Mailer.php';
require_once dirname(__DIR__) . '/Email/EmailTemplates.php';

/**
 * Autenticación del PORTAL de asociados (cx/). Sesión de servidor aparte del staff
 * (`multiappv2_portal`); la identidad SIEMPRE vive en $_SESSION (nunca el cliente).
 *
 * - Login por CÉDULA de `sifone_asociados` (via `vw_asociados`) validando el hash
 *   bcrypt en `asociados_control.password_hash`.
 * - Si `password_hash` es NULL → primer acceso: se guarda la cédula en una clave
 *   temporal (sin sesión autenticada) para redirigir a `password_crear.php` (crear
 *   contraseña con código por email).
 * - Cambiar contraseña estando autenticado (verifica la actual + rotación simple).
 *
 * El actor expuesto al resto del sistema:
 *   ['tipo' => 'portal', 'cedula' => <sifone_asociados.cedula>, 'nombre' => …, 'email' => …]
 */
final class PortalAuth
{
    private const SESSION_NAME = 'multiappv2_portal';

    private static function boot(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        if (!headers_sent()) {
            session_name(self::SESSION_NAME);
            // Hardening de la cookie de sesión (mitiga XSS/CSRF):
            // HttpOnly (no legible por JS), SameSite=Lax, Secure solo bajo HTTPS.
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'secure'   => self::isHttps(),
                'samesite' => 'Lax',
            ]);
            @ini_set('session.use_strict_mode', '1');
        }
        @session_start();
    }

    /** Detecta HTTPS, incluso detrás de un proxy/Cloudflare (X-Forwarded-Proto). */
    private static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            return true;
        }
        if (($_SERVER['SERVER_PORT'] ?? null) == 443) {
            return true;
        }
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /** Busca al asociado por cédula. Normaliza el documento (quita no-dígitos y ceros iniciales). */
    private static function asociadoPorDocumento(string $cedula): ?array
    {
        // La cédula se guarda tal cual en sifone_asociados; comparamos numéricamente
        // para tolerar diferencias de formato (legacy CAST AS UNSIGNED).
        $st = db()->prepare('SELECT cedula, nombre, COALESCE(email,"") AS email, COALESCE(celular,"") AS celular
                             FROM vw_asociados WHERE CAST(cedula AS UNSIGNED) = CAST(? AS UNSIGNED) LIMIT 1');
        $st->execute([trim($cedula)]);
        return $st->fetch() ?: null;
    }

    /** Trae el hash de `asociados_control` para una cédula (o null si no está / NULL). */
    private static function passwordHash(string $cedula): ?string
    {
        $st = db()->prepare('SELECT password_hash FROM asociados_control WHERE cedula = ?');
        $st->execute([trim($cedula)]);
        $h = $st->fetchColumn();
        return ($h === null || $h === false || $h === '') ? null : (string) $h;
    }

    /**
     * Login por cédula + contraseña.
     * - Si no existe la cédula o la contraseña es inválida → null.
     * - Si el asociado no tiene password_hash aún → guarda la cédula temporalmente
     *   en la sesión (clave `cx_pending`) y devuelve ['pending' => true, 'cedula' => …].
     * - Si todo OK → abre la sesión autenticada y devuelve el actor.
     */
    public static function login(string $cedula, string $password): ?array
    {
        self::boot();
        $cedula = trim($cedula);
        $clave = 'portal:' . ($_SERVER['REMOTE_ADDR'] ?? '') . ':' . $cedula;
        if (RateLimit::bloqueado($clave)) {
            Logger::login(null, 'Login portal bloqueado por rate-limit cad=' . $cedula, false, 'portal.auth');
            return null;
        }
        $a = self::asociadoPorDocumento($cedula);
        if (!$a) {
            RateLimit::registrar($clave);
            Logger::login(null, 'Login portal fallido: cédula no existe cad=' . $cedula, false, 'portal.auth');
            return null;
        }
        $canon = (string) $a['cedula'];
        $hash = self::passwordHash($canon);
        if ($hash === null) {
            // Primer acceso: aún no tiene contraseña → pantalla "crear contraseña".
            $_SESSION['cx_pending'] = $canon;
            Logger::login($canon, 'Login portal: sin password, redirige a password_crear (código por email)', false, 'portal.auth');
            return ['pending' => true, 'cedula' => $canon];
        }
        if (!password_verify($password, $hash)) {
            RateLimit::registrar($clave);
            Logger::login($canon, 'Login portal fallido: contraseña inválida cad=' . $canon, false, 'portal.auth');
            return null;
        }
        unset($_SESSION['cx_pending']);
        $_SESSION['cx_cedula']       = $canon;
        $_SESSION['cx_nombre']       = (string) ($a['nombre'] ?? '');
        $_SESSION['cx_email']        = (string) ($a['email'] ?? '');
        $_SESSION['cx_login_at']     = time();
        $_SESSION['cx_last_activity'] = time();
        Logger::login($canon, 'Login portal OK', true, 'portal.auth');
        Csrf::token();
        return self::actor();
    }

    /**
     * Cambia la contraseña estando autenticado: verifica la actual + nueva (rotación simple). */
    public static function changePassword(string $passwordActual, string $passwordNueva): bool
    {
        $actor = self::actor();
        if (!$actor) {
            return false;
        }
        $cedula = (string) $actor['cedula'];
        $hash = self::passwordHash($cedula);
        if ($hash === null || !password_verify($passwordActual, $hash)) {
            return false;
        }
        self::guardarHash($cedula, $passwordNueva);
        return true;
    }

    /**
     * Emite un código de verificación de 6 dígitos (crear/restablecer contraseña) y lo envía
     * por email al correo del asociado. Guarda `reset_token`/`reset_token_expires_at` (+20 min).
     * Devuelve estado (crear/restablecer) y máscara del correo. Lanza DomainException si no existe.
     */
    public static function emitirCodigo(string $cedula): array
    {
        self::boot();
        $cedula = trim($cedula);
        $a = self::asociadoPorDocumento($cedula);
        if (!$a) {
            throw new DomainException('La cédula no está registrada como asociado.');
        }
        $canon   = (string) $a['cedula'];
        $nombre  = (string) ($a['nombre'] ?? '');
        $email   = (string) ($a['email'] ?? '');
        $codigo  = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 20 * 60);

        self::setResetToken($canon, $codigo, $expires);

        $enviado = null;
        if ($email !== '' && Mailer::isConfigured()) {
            $cuerpo = EmailTemplates::cuentaPasswordCodigo($nombre, $canon, $codigo);
            $enviado = Mailer::send($email, 'Código de verificación · Portal de Asociados', $cuerpo);
        }
        Logger::log('password_reset', 'portal.auth', $canon, 'Código de verificación emitido (correo: ' . self::mascara($email) . ')');
        return [
            'estado'  => self::passwordHash($canon) !== null ? 'con_password' : 'sin_password',
            'cedula'  => $canon,
            'correo'  => self::mascara($email),
            'enviado' => $enviado,
        ];
    }

    /** Valida el código + expiración y crea/restringe la contraseña. True si ok. */
    public static function confirmarCodigo(string $cedula, string $codigo, string $passwordNueva): bool
    {
        self::validarPassword($passwordNueva);
        $cedula = trim($cedula);
        $st = db()->prepare('SELECT reset_token, reset_token_expires_at FROM asociados_control WHERE cedula = ?');
        $st->execute([$cedula]);
        $r = $st->fetch();
        if (!$r || empty($r['reset_token'])) {
            throw new DomainException('No hay un código pendiente para esa cédula.');
        }
        if (trim((string) $r['reset_token']) !== trim($codigo)) {
            throw new DomainException('El código de verificación es incorrecto.');
        }
        if (empty($r['reset_token_expires_at']) || strtotime((string) $r['reset_token_expires_at']) < time()) {
            throw new DomainException('El código ha expirado. Solicite uno nuevo.');
        }
        self::guardarHash($cedula, $passwordNueva);
        Logger::log('password_reset', 'portal.auth', $cedula, 'Contraseña creada/restablecida con código');
        return true;
    }

    private static function setResetToken(string $cedula, string $codigo, string $expires): void
    {
        $st = db()->prepare(
            'UPDATE asociados_control SET reset_token = ?, reset_token_expires_at = ?, fecha_actualizacion = NOW() WHERE cedula = ?'
        );
        $st->execute([$codigo, $expires, $cedula]);
        if ($st->rowCount() === 0) {
            db()->prepare('INSERT INTO asociados_control (cedula, reset_token, reset_token_expires_at, estado_activo, fecha_actualizacion)
                           VALUES (?, ?, ?, 1, NOW())')->execute([$cedula, $codigo, $expires]);
        }
    }

    private static function mascara(string $email): string
    {
        if ($email === '') {
            return '—';
        }
        $at = strpos($email, '@');
        if ($at === false) {
            return $email;
        }
        $local = substr($email, 0, $at);
        $dominio = substr($email, $at);
        return ($local !== '' ? $local[0] : '') . '***' . $dominio;
    }

    private static function validarPassword(string $password): void
    {
        if (strlen($password) < 6) {
            throw new DomainException('La contraseña debe tener al menos 6 caracteres.');
        }
    }

    private static function guardarHash(string $cedula, string $password): void
    {
        self::validarPassword($password);
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $st = db()->prepare(
            'UPDATE asociados_control
               SET password_hash = ?, password_set_at = NOW(), reset_token = NULL,
                   reset_token_expires_at = NULL, estado_activo = 1, fecha_actualizacion = NOW()
             WHERE cedula = ?'
        );
        $st->execute([$hash, $cedula]);
        if ($st->rowCount() === 0) {
            // Sede: la fila de control no existe, se crea.
            db()->prepare('INSERT INTO asociados_control (cedula, password_hash, password_set_at, estado_activo, fecha_actualizacion)
                           VALUES (?, ?, NOW(), 1, NOW())')->execute([$cedula, $hash]);
        }
        Logger::log('cambiar_password', 'portal.auth', $cedula, 'Contraseña actualizada');
    }

    /** Sesión del portal válida (o null), con timeout de inactividad 3600s. */
    public static function actor(): ?array
    {
        // Impersonación por token (Fase 3): un token con el scope 'portal.actuar'
        // + header X-Acting-As-Cedula opera como ese asociado (aislado por cédula).
        if (class_exists('TokenAuth', false) && TokenAuth::active()
            && TokenAuth::hasScope('portal.actuar')) {
            $ced = trim((string) ($_SERVER['HTTP_X_ACTING_AS_CEDULA'] ?? ''));
            if ($ced !== '') {
                return ['tipo' => 'portal', 'cedula' => $ced, 'nombre' => '', 'email' => '', 'on_behalf' => true];
            }
        }
        self::boot();
        $cedula = $_SESSION['cx_cedula'] ?? null;
        if ($cedula === null || $cedula === '') {
            return null;
        }
        $lifetime = (int) (getenv('SESSION_LIFETIME') ?: 3600);
        if (time() - (int) ($_SESSION['cx_last_activity'] ?? 0) > $lifetime) {
            self::logout();
            return null;
        }
        $_SESSION['cx_last_activity'] = time();
        return [
            'tipo'   => 'portal',
            'cedula' => (string) $cedula,
            'nombre' => (string) ($_SESSION['cx_nombre'] ?? ''),
            'email'  => (string) ($_SESSION['cx_email'] ?? ''),
        ];
    }

    /** Exige asociado autenticado; corta con 401 JSON si no. */
    public static function requireAsociado(): array
    {
        $a = self::actor();
        if ($a === null) {
            Http::fail('No autenticado', 401);
        }
        Http::csrf();
        return $a;
    }

    public static function logout(): void
    {
        self::boot();
        $cedula = $_SESSION['cx_cedula'] ?? null;
        if ($cedula) {
            Logger::log('logout', 'portal.auth', $cedula, 'Logout portal');
        }
        $_SESSION = [];
        session_destroy();
    }
}