<?php
declare(strict_types=1);

/**
 * Protección CSRF: token por sesión, verificado en toda operación mutadora (POST/PUT/PATCH/DELETE).
 *
 * - token():  genera (si no existe) el token y lo guarda en la sesión. Lo llaman el login
 *             y los layouts (meta tag) para exponerlo al frontend.
 * - verify(): devuelve true si el token recibido coincide (cabecera X-CSRF-Token o campo _csrf);
 *             false en caso contrario. Si NO hay sesión activa devuelve true: la petición aún no
 *             está autenticada (el gate de auth del endpoint responderá 401), no hay CSRF que validar.
 *
 * El token se recibe principalmente por la cabecera `X-CSRF-Token`, inyectada por assets/js/common.js
 * en todo fetch POST/PUT/PATCH/DELETE. El campo `_csrf` es un fallback para envíos por formulario.
 */
final class Csrf
{
    public const HEADER = 'X-CSRF-Token';
    public const FIELD  = '_csrf';

    /** Devuelve (generando si hace falta) el token CSRF de la sesión actual. */
    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    /** Verifica el token recibido contra la sesión. true = válido (o sin sesión activa). */
    public static function verify(): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true; // sin sesión: nada autenticado que proteger todavía
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($expected === '') {
            // Sesión creada antes de esta protección: se genera ahora y se exige en la siguiente petición.
            self::token();
            $expected = $_SESSION['csrf_token'];
        }
        $received = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($received === '') {
            $received = trim((string) ($_POST['_csrf'] ?? ''));
        }
        if ($received === '' || !hash_equals((string) $expected, $received)) {
            return false;
        }
        return true;
    }
}
