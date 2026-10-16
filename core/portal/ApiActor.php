<?php
declare(strict_types=1);

/**
 * Resolución del actor en endpoints COMPARTIDOS por el portal (cx/) y el staff (ui/).
 *
 * Contexto: PHP solo admite una sesión por petición y el portal (`multiappv2_portal`)
 * y el staff (`multiappv2_staff`) usan cookies distintas; ambas pueden coexistir en el
 * mismo navegador. Regla:
 *   - Marca explícita de portal (`X-Portal-Auth`) → SIEMPRE 'portal'; si la sesión de
 *     portal no es válida, el endpoint responde 401 (nunca cae a staff).
 *   - Sin marca → 'portal' solo si hay cookie de portal y NO de staff; en otro caso 'staff'.
 *
 * Se aísla aquí para poder probar la regla sin arrancar sesiones.
 */
final class ApiActor
{
    /** @return string 'portal' | 'staff' */
    public static function via(bool $wantsPortal, bool $hasPortal, bool $hasStaff): string
    {
        if ($wantsPortal) {
            return 'portal';
        }
        return ($hasPortal && !$hasStaff) ? 'portal' : 'staff';
    }

    /** ¿La petición actual trae la marca explícita de portal? */
    public static function pidePortal(): bool
    {
        return isset($_SERVER['HTTP_X_PORTAL_AUTH']) && (string) $_SERVER['HTTP_X_PORTAL_AUTH'] !== '';
    }
}
