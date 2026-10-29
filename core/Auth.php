<?php
declare(strict_types=1);

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/Http.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/UsuarioRepository.php';
require_once __DIR__ . '/Permisos.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/RateLimit.php';

/**
 * Autenticación de staff interno (ui) por sesión de servidor.
 * La identidad SIEMPRE vive en $_SESSION (nunca se confía en el cliente).
 *
 * Contraseña de un solo uso + rotación (regla 6.5): needsPasswordChange().
 * El portal de asociados (cx2) usa su propia auth por cédula (bloque B13).
 *
 * Actor expuesto al resto del sistema:
 *   ['tipo' => 'interno', 'id' => <control_usuarios.id>, 'rol' => <rol>]
 */
final class Auth
{
    private const SESSION_NAME = 'multiappv2_staff';
    private const ROTATION_DAYS_DEFAULT = 90;

    /**
     * Cache del staff autenticado en la petición actual.
     * Evita volver a abrir la sesión (y recuperar su lock) después de
     * liberarla con releaseSession().
     */
    private static ?array $staffCache = null;
    private static bool $staffResolved = false;
    private static bool $sessionReleased = false;

    private static function boot(): void
    {
        if (self::$sessionReleased) {
            return; // La sesión ya se cerró en esta petición; no reabrir.
        }
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

    /** Verifica credenciales y abre sesión. Devuelve el usuario o null. */
    public static function login(string $usuario, string $password): ?array
    {
        self::$staffCache = null;
        self::$staffResolved = false;
        self::$sessionReleased = false;
        self::boot();
        $clave = 'staff:' . ($_SERVER['REMOTE_ADDR'] ?? '') . ':' . trim($usuario);
        if (RateLimit::bloqueado($clave)) {
            Logger::login(null, "Login bloqueado por rate-limit usuario=$usuario", false);
            return null;
        }
        $repo = new UsuarioRepository(db());
        $u = $repo->porUsuario(trim($usuario));

        if (!$u || !password_verify($password, $u['password'])) {
            RateLimit::registrar($clave);
            Logger::login(isset($u['id']) ? (string) $u['id'] : null, "Login fallido usuario=$usuario", false);
            return null;
        }

        $_SESSION['staff'] = [
            'id'              => (int) $u['id'],
            'usuario'         => $u['usuario'],
            'nombre_completo' => $u['nombre_completo'],
            'rol'             => $u['rol'],
            'must_change'     => self::needsPasswordChange($u),
            'login_at'        => time(),
            'last_activity'   => time(),
        ];
        Logger::login((string) $u['id'], 'Login OK', true);
        Csrf::token();
        return $_SESSION['staff'];
    }

    public static function logout(): void
    {
        self::$staffCache = null;
        self::$staffResolved = false;
        self::$sessionReleased = false;
        self::boot();
        $s = $_SESSION['staff'] ?? null;
        if (is_array($s)) {
            Logger::log('logout', 'auth', (string) ($s['id'] ?? ''),
                'Logout staff' . (($s['usuario'] ?? '') !== '' ? ' usuario=' . $s['usuario'] : ''));
        }
        $_SESSION = [];
        session_destroy();
    }

    /** Sesión de staff válida (o null), aplicando timeout de inactividad. */
    public static function staff(): ?array
    {
        // Autenticación por token (integraciones externas): actor sin sesión.
        if (class_exists('TokenAuth', false) && TokenAuth::active()) {
            return TokenAuth::actor();
        }
        if (self::$staffResolved) {
            return self::$staffCache;
        }
        self::boot();
        $s = $_SESSION['staff'] ?? null;
        if (!is_array($s)) {
            self::$staffResolved = true;
            self::$staffCache = null;
            return null;
        }
        $lifetime = (int) (getenv('SESSION_LIFETIME') ?: 3600);
        if (time() - (int) ($s['last_activity'] ?? 0) > $lifetime) {
            self::logout();
            return null;
        }
        $_SESSION['staff']['last_activity'] = time();
        self::$staffResolved = true;
        self::$staffCache = $s;
        return $s;
    }

    /**
     * Libera el lock del archivo de sesión al terminar la autenticación.
     * Las APIs de solo lectura no necesitan la sesión abierta durante las
     * consultas; mantenerla bloqueada serializa todas las peticiones del
     * navegador (misma cookie) y una API lenta bloquea a las demás.
     */
    public static function releaseSession(): void
    {
        // Solo lecturas: las mutaciones conservan la sesión para que cualquier
        // verificación CSRF posterior siga teniendo el contexto original.
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        self::$sessionReleased = true;
    }

    /** Actor genérico para el resto del sistema (o null). */
    public static function actor(): ?array
    {
        $s = self::staff();
        return $s ? ['tipo' => 'interno', 'id' => (string) $s['id'], 'rol' => $s['rol']] : null;
    }

    public static function requireActor(): array
    {
        $a = self::actor();
        if ($a === null) {
            self::handleUnauthenticated();
        }
        return $a;
    }

    /** Exige un permiso de módulo (por prefijo, ui/config/secciones.php). Corta con 403 si no. */
    public static function requireModulo(string $modulo): array
    {
        $s = self::staff();
        if ($s === null) {
            self::handleUnauthenticated();
        }
        if (!Permisos::puede($s['rol'], $modulo)) {
            Logger::log('acceso_denegado', 'seguridad', (string) ($s['id'] ?? ''),
                'Acceso denegado al módulo ' . $modulo . ' (rol=' . ($s['rol'] ?? '') . ')', null, null, 'warning');
            Http::fail('Acceso denegado', 403);
        }
        // Token: además del rol, exige scope + allowlist global.
        if (class_exists('TokenAuth', false) && TokenAuth::active() && !TokenAuth::scopePermite($modulo)) {
            Logger::log('acceso_denegado', 'seguridad', (string) ($s['id'] ?? ''),
                'Token sin permiso para el módulo ' . $modulo, null, null, 'warning');
            Http::fail('Token sin permiso para este módulo', 403);
        }
        Http::csrf();
        self::releaseSession();
        return $s;
    }

    /**
     * Exige la operación CRUD ('<pagina>.<operacion>', deny-by-default en 'acciones').
     * Refuerza en el API lo que los botones muestran en la UI.
     */
    public static function requireAccion(string $clave): array
    {
        $s = self::staff();
        if ($s === null) {
            self::handleUnauthenticated();
        }
        if (!Permisos::accion($s['rol'], $clave)) {
            Logger::log('acceso_denegado', 'seguridad', (string) ($s['id'] ?? ''),
                'Acceso denegado a la operación ' . $clave . ' (rol=' . ($s['rol'] ?? '') . ')', null, null, 'warning');
            Http::fail('Acceso denegado', 403);
        }
        // Token: además del rol, exige scope + allowlist global.
        if (class_exists('TokenAuth', false) && TokenAuth::active() && !TokenAuth::scopePermite($clave)) {
            Logger::log('acceso_denegado', 'seguridad', (string) ($s['id'] ?? ''),
                'Token sin permiso para la operación ' . $clave, null, null, 'warning');
            Http::fail('Token sin permiso para esta operación', 403);
        }
        Http::csrf();
        self::releaseSession();
        return $s;
    }

    /**
     * Sin sesión de staff: si el endpoint marcó $GLOBALS['AUTH_REDIRECT_UNATH'] (ruta relativa
     * p.ej. '../../'), redirige ahí (comportamiento de los serve_* abiertos en navegador); si no,
     * devuelve JSON 401 (comportamiento API por defecto).
     */
    private static function handleUnauthenticated(): void
    {
        $target = $GLOBALS['AUTH_REDIRECT_UNATH'] ?? null;
        if (is_string($target) && $target !== '') {
            header('Location: ' . $target);
            exit;
        }
        Http::fail('No autenticado', 401);
    }

    /** ¿Debe cambiar la contraseña? (un solo uso o rotación vencida) */
    public static function needsPasswordChange(array $u): bool
    {
        if ((int) $u['password_is_one_use'] === 1) {
            return true;
        }
        if (empty($u['password_changed_at'])) {
            return true;
        }
        $days = (int) (getenv('PASSWORD_ROTATION_DAYS') ?: self::ROTATION_DAYS_DEFAULT);
        return (time() - strtotime((string) $u['password_changed_at'])) > $days * 86400;
    }
}
