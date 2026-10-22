<?php
declare(strict_types=1);

/**
 * Excepción para conversaciones cerradas por inactividad (TTL).
 * El endpoint la mapea a HTTP 409 con code="sesion_cerrada".
 */
final class SesionCerradaException extends RuntimeException
{
}
